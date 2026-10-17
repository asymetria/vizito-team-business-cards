# Team Business Cards — design

**Date:** 2026-09-25
**Status:** approved, ready for the implementation plan
**Author:** Asymetria

## 1. Purpose

Digital business cards for a company's staff, served from the company's own
WordPress site. Each person gets a mobile page at a permanent address that a
printed QR code points to: photo, role, contact actions, "save contact"
(vCard), directions and social links, in several languages.

The product replaces per-seat SaaS subscriptions (lead.me, Blinq, HiHello and
similar): the data, the address and the statistics stay on the company's own
domain, with no monthly fee per person.

Written from scratch. It draws on the experience of a single-client plugin
built for one company in 2026-08 and 2026-09, but reuses none of its code and
migrates none of its data.

## 2. Identity

| Item | Value |
|---|---|
| Name | Team Business Cards |
| Slug / text domain | `team-business-cards` (free on WordPress.org, checked 2026-09-24) |
| Namespace | `Asymetria\TeamCards` |
| Prefix | `tbcards_` (functions, options, meta `_tbcards_*`, hooks, REST `tbcards/v1`); CSS keeps `tbcards-` and `--tbcards-*` |
| Post type | `tbcards_card` (renamed from `tbc_` before release: WordPress.org asks for 4+ character prefixes) |
| Requirements | PHP 8.0+, WordPress 6.2+ |
| Licence | GPL-2.0-or-later |
| Dependencies | none (no Composer, no npm, no build step) |

## 3. Decisions taken during design

| # | Question | Decision |
|---|---|---|
| 1 | Languages without Polylang | Plugin-managed language list; translated fields per card; `?lang=`; Polylang/WPML used if present, never required |
| 2 | Shared company data | One company profile in settings; empty card fields inherit, filled ones override |
| 3 | Rendering | Own lightweight full-page template, independent of the theme, plus embedding |
| 4 | Embedding | Shortcode + Gutenberg block + Bricks element, all over one renderer |
| 5 | Statistics | Daily event counters in an own table, recorded by a beacon |
| 6 | QR | Full print set: SVG + PNG, colours, centre logo, bulk ZIP, test sheet, `?src=qr` |
| 7 | Fields | Fixed curated list + up to 3 custom buttons |
| 8 | Bulk entry | CSV import with preview + CSV export |
| 9 | Staff leaving | "Inactive" status with a chosen behaviour; the address keeps working |
| 10 | Architecture | CPT + meta + classes by responsibility; own table only for statistics |
| — | Migration from the earlier client plugin | None |

Rejected alternatives, briefly: Polylang-only or single-language cards (the
dependency is what we are removing); multiple company profiles (v1 scope,
can be added over the same inheritance point later); theme-embedded card
page (slow, theme-dependent); free-form field repeater (vCard cannot map it);
own tables for cards (twice the code for scale no target user has);
block-composed cards (destroys structured data for vCard and CSV).

## 4. Structure

File naming follows the author's earlier plugins (and WordPress coding
standards): `class-*.php`, sub-namespace = directory under `includes/`.
`Asymetria\TeamCards\Card\Card_Repository` →
`includes/card/class-card-repository.php`.

```
team-business-cards.php      bootstrap: constants, autoloader, Plugin::boot()
includes/
  class-autoloader.php
  class-plugin.php           hooks and service wiring (no DI container)
  class-settings.php         languages, URL base, appearance, QR, retention, uninstall
  card/class-card-type.php          CPT, rewrite, noindex, admin list columns
  card/class-card.php               value object, values already resolved
  card/class-card-repository.php    the only reader/writer of card meta
  card/class-fields.php             field definitions: one source for form, save, vCard, CSV
  card/class-field-sanitizer.php
  company/class-company-profile.php
  admin/class-card-metabox.php
  admin/class-settings-page.php
  admin/class-import-page.php
  front/class-card-renderer.php     the card HTML, shared by every output
  front/class-template-loader.php   full page + theme override
  front/class-language.php          language resolution
  front/class-icons.php             inline SVG icons
  embed/class-shortcode.php
  embed/class-block.php
  embed/class-bricks-element.php
  vcard/class-vcard-builder.php
  vcard/class-vcard-endpoint.php
  qr/class-qr-service.php
  qr/class-svg-writer.php
  qr/class-png-writer.php
  qr/class-print-export.php
  stats/class-stats-table.php
  stats/class-stats-endpoint.php
  stats/class-stats-report.php
  lifecycle/class-inactive-card.php
  integrations/class-cache-purge.php
  csv/class-csv-reader.php
  csv/class-importer.php
  csv/class-exporter.php
lib/qrcode/qrcode.php        bundled qrcode-generator (PHP port), namespaced
templates/card.php
assets/                      card.css, card.js, admin.css, admin.js, block.js — no build
languages/                   .pot, pl_PL, de_DE
tests/run.php                + WordPress stubs, as in the author's earlier plugins
```

Rules:

- Every layer reads cards only through `Card_Repository`. Inheritance from the
  company profile happens in exactly one place: when the `Card` object is built.
- The full page, shortcode, block and Bricks element all call `Card_Renderer`,
  so a card looks the same everywhere.
- Assets load only where a card is rendered, once per page however many cards.

## 5. Data model

### Languages

Settings hold an ordered list of language codes (e.g. `pl, de, en`), a default
language, and for each code a WordPress locale (`de` → `de_DE`). Order is the
order of the switcher.

### Company profile

One option, `tbc_company`: company name, logo (attachment ID), address
(street, postcode, city, country), map link, website, switchboard phone,
social links, default intro per language.

### Card fields

Stored as post meta `_tbc_*`, defined once in `Fields`.

| Group | Fields | Per language | Inherits |
|---|---|---|---|
| Person | first name, last name, photo (featured image) | no | — |
| Role | job title, intro | yes | intro |
| Contact | mobile, landline, email, WhatsApp | no | landline (switchboard) |
| Address | street, postcode, city, country, map link | no | yes |
| Links | website, LinkedIn, Facebook, Instagram, X, YouTube, TikTok, GitHub, Xing | no | yes |
| Custom | up to 3 × label (per language) + URL | label yes | no |
| State | active / inactive + inactive behaviour | no | — |

Translated fields are stored as one array per field
(`_tbc_role = ['pl' => …, 'de' => …]`), never as `role_pl`-style keys, so adding
a language needs no code change.

### Resolution order

Current language → default language → company profile (if the field inherits)
→ omitted. An empty value never reaches the page or the vCard.

### Validation on save

Phones: kept as typed for display, plus an E.164 form for `tel:` and vCard.
Email: `sanitize_email`. URLs: `esc_url_raw` with an explicit scheme allow-list.

## 6. Front end

### Full page — `/card/{slug}`

- Own HTML template; does not call the theme's header or footer.
- Only our CSS (inline, ~5 KB) and one small script, no jQuery.
- `noindex`; excluded from core, Yoast and Rank Math sitemaps; no `/card/` archive.
- The URL base is configurable in settings (e.g. `/wizytowka/`).
- Theme override: `your-theme/team-business-cards/card.php`.

Card content, top to bottom: company logo; photo, name, job title, intro;
primary actions (**Save contact**, Call, Email, WhatsApp); labelled contact
details; address with "Directions"; social icons and custom buttons;
**Share** (Web Share API, fallback: copy link); language switcher. Icons are
inline SVG — no icon fonts, no CDN.

### Languages

- `?lang=xx` selects the language.
- The QR link carries no language. The page renders in the default language,
  and a small client-side script switches to the browser's language when it is
  on the list. This is client-side on purpose, so page caches never mix
  languages. It can be turned off in settings.
- UI labels are translated through `switch_to_locale()` using the mapped locale.
  The package ships `.pot`, `pl_PL` and `de_DE`.

### Appearance

Accent colour; light, dark or automatic (system) scheme; round or square photo.
Everything else through `--tbcards-*` CSS custom properties.

### Embedding

- Shortcode `[team_card id="…"]` or `slug="…"`.
- Block "Team Card": dynamic, PHP-rendered, no build step, card picked from a list.
- Bricks element with the same options.

All three call `Card_Renderer`. An embedded card takes the width of its
container.

### Cache

After a card or the company profile is saved, purge page caches through their
public hooks when present: LiteSpeed, WP Rocket, W3 Total Cache, WP Super
Cache, and core. This is the problem the earlier client plugin only discovered
in production (a 30-day LiteSpeed TTL hid every edit).

## 7. vCard

- Version 3.0 (most reliable on iOS and Android).
- CRLF line endings; lines folded at 75 octets without splitting a UTF-8 sequence;
  `,` `;` `\` escaped.
- Properties: `N`, `FN`, `ORG`, `TITLE` (card language), typed `TEL`s, `EMAIL`,
  `ADR`, `URL` (company website and the card's address), social profiles as
  `X-SOCIALPROFILE` plus `URL`.
- `PHOTO` embedded as base64 JPEG (~256 px, from the Media Library). It makes
  the saved contact show a face; it can be disabled because it enlarges the file.
- Address: `/card/{slug}/vcard/?lang=xx`, filename `first-last.vcf`,
  `no-cache` headers.
- An inactive card answers **410** and discloses nothing.

## 8. QR

### Library

qrcode-generator by Kazuhiko Arase, PHP port (`php/qrcode.php`, MIT, verified
2026-09-25). It runs on PHP 8.5 without notices. For
`https://example.com/card/jane-doe/?src=qr` at level H it
produces 41 modules, the same as the JavaScript build used in production.

**It must be vendored, not dropped in as is.** The file declares global
classes (`QRCode`, `QRUtil`, `QRMath`…) and unprefixed global constants
(`QR_PAD0`, `QR_ERROR_CORRECT_LEVEL_H`…). WordPress.org rejects that, and a
second plugin shipping the same file would cause a fatal redeclaration.
Vendoring means: move it into `Asymetria\TeamCards\Vendor\Qr`, turn the
`define()`s into class constants, drop its GD image helper (we have our own
writer), keep the MIT header, and list the library in `readme.txt`.

### Output

- `Qr_Service` encodes the card address plus `?src=qr` and returns the module matrix.
- `Svg_Writer`: one `<path>`, `shape-rendering="crispEdges"`, 4-module quiet zone.
- `Png_Writer`: GD, 2000 px by default. Without GD only SVG is offered, with a
  notice in the admin.
- Module and background colours are set in settings. Saving is refused when
  contrast is too low or the colours are inverted (light modules on dark),
  because some readers fail on those.
- Centre logo: enabling it switches correction to level H; the logo covers at
  most ~20% of the symbol on a white pad. SVG embeds it as an image, PNG
  composites it with GD.
- Card screen: QR preview and SVG/PNG downloads, with and without logo.

The `?src=qr` parameter lives in every printed code forever. It breaks
nothing; it only needs to stay supported.

### Print export

An admin page: select cards → a ZIP (`ZipArchive`) with SVG + PNG per person,
plus a **test sheet** — a print-ready A4 HTML page with every code and the
name, role and address under it, in logo and plain variants. HTML rather than
PDF, so no PDF library is needed.

## 9. Statistics

- **Table** `{prefix}tbc_stats`: `card_id`, `day`, `event`, `source`
  (`qr` / `link`), `count`. Primary key on the first four. Writes use
  `INSERT … ON DUPLICATE KEY UPDATE count = count + 1`, so one row per
  card, day, event and source, not per visit.
- **Events:** `view`, `vcard`, `call`, `email`, `whatsapp`, `directions`,
  `share`, `link`.
- **Recording:** `POST /tbc/v1/hit` via `navigator.sendBeacon`. No nonce,
  because cached pages carry stale nonces. Guarded instead by: the card must
  exist and be active; the event must be on the list; bot user agents are
  dropped; a short-lived per-IP throttle keyed by a salted hash in a transient
  (nothing persisted). vCard downloads are counted server-side.
- **No cookies, no IP addresses, no personal data stored**, so no consent
  banner is needed. The figures are indicative; a determined actor can inflate
  them. The readme says so.
- **Reports:** "Views 30d" and "vCard 30d" columns in the card list; a 30-day
  inline-SVG chart on the card screen, split QR / link; a Statistics page with
  all cards, a date range and CSV export. Retention is configurable (default:
  keep, since rows are aggregates only).

## 10. Inactive cards

- A switch on the card, with one of three behaviours: a notice with the
  company's details; a redirect to a chosen card (a successor); a redirect to a URL.
- Redirects are **302**, not 301. Browsers cache 301s indefinitely, and the
  successor may change.
- Personal data disappears at once from the page, the vCard, embeds and QR
  export. Statistics are kept.
- Changing the slug of a published card shows a warning that printed QR codes
  will stop pointing here. WordPress's own `_wp_old_slug` handling redirects the
  old address; we must not interfere with it.

## 11. CSV import and export

- **Input:** an uploaded file or pasted text. Delimiter `,` `;` or tab detected
  automatically (Polish Excel writes `;`). UTF-8 with or without BOM; Windows-1250
  detected and converted.
- **Columns:** the `Fields` keys; translated fields take a language suffix
  (`role_de`). A photo is referenced by attachment ID or by the filename of an
  image already in the Media Library. No remote sideloading in v1.
- **Matching:** by email, then by slug. An empty cell overwrites nothing; `-`
  clears a value explicitly.
- **Two steps:** a preview ("creates / updates / errors per row") stored in a
  per-user transient, then a commit. At most 500 rows per import.
- **Export** in the same format, so an exported file re-imports as is.

## 12. Admin

- Menu **Team Cards**: Cards, Add card, Company profile, Appearance & QR,
  Import / Export, Statistics, Settings (languages, URL base, cache,
  retention, data on uninstall).
- The card form uses language tabs for translated fields. An empty inheriting
  field shows what will appear instead (e.g. "from company profile: Twarda 18").
- Capabilities: `edit_posts` for cards and `manage_options` for settings, both
  filterable.

## 13. Security

- Nonces and `current_user_can` on every write; sanitise on input, escape on output.
- All queries through `$wpdb->prepare`; CSV uploads checked for type and size.
- Public surface: the card page, the vCard and the beacon only. The card list is
  not exposed through REST; `show_in_rest` is limited to logged-in editors, for
  the block's picker.

## 14. Uninstall

`uninstall.php` removes cards, meta, options and the table **only when that
setting is on**. The default keeps everything: deleting the plugin by mistake
must not wipe thirty business cards.

## 15. Testing

The author's usual setup: `php tests/run.php` against WordPress stubs, and
`node --test` for the scripts. No dependencies.

Priorities:

- resolution and inheritance order
- vCard: escaping, UTF-8-safe folding, omission of empty values
- QR matrices against reference vectors
- CSV parser: delimiters, BOM, Windows-1250, matching, `-`
- stats writes and filtering
- inactive-card behaviours

Beyond the suite:

- a developer script outside the package that decodes generated PNGs,
  including the logo variant
- a manual phone checklist on iOS and Android: scan, save contact, photo in the
  contact, language switch, share, directions

## 16. Release

- Plugin Check clean.
- `readme.txt` credits the bundled library and states that the plugin contacts
  **no external service**.
- `.distignore`, `LICENSE`, and `README.md` for GitHub.
- Submission to WordPress.org waits until the author's first submission
  (`product-sheet-importer-for-woocommerce`) has passed review; the directory
  does not accept a second plugin while the first is pending. GitHub can go
  public earlier.

## 17. Stages

1. Core: CPT, fields, repository, company profile, full card page, languages.
2. vCard and QR (single codes, logo, export, test sheet).
3. Statistics and inactive cards.
4. CSV import and export.
5. Embedding: shortcode, block, Bricks.
6. Translations, accessibility, readme, Plugin Check, manual tests, repository.

## 18. Out of scope for v1

Multiple company profiles; a public REST API for HR or CRM integrations;
remote photo sideloading in the importer; Apple/Google Wallet passes; NFC
programming; PDF output; per-card visual editor; migration from any other plugin.
