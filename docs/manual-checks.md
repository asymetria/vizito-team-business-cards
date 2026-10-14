# Manual checks

What the stub-based suite cannot see. Run on a real WordPress (staging, never a
client's production site), with `WP_DEBUG` and `WP_DEBUG_LOG` on.

## Stage 1 — card page (build 0.1.0-dev)

Setup: install `vizito-team-business-cards-0.1.0-dev.zip`, activate.

- [ ] Activation: no notice, no error in `debug.log`. Menu "Team Cards" appears.
- [ ] Team Cards → Settings: languages `pl pl_PL` / `de de_DE` / `en en_US`,
      default `pl`, save. Values come back as saved.
- [ ] Untick "Browser language", save, reload: stays unticked.
- [ ] Team Cards → Company profile: name, logo via "Select logo" (media library
      opens, id lands in the field), address, website, LinkedIn, intro in pl + de. Save.
- [ ] Add card: first/last name, job title in pl and de (tabs switch, arrow keys
      move between tabs), mobile `+48 …`, email. City left empty: the field shows the
      company city as a grey hint. Photo as featured image. Publish.
- [ ] Title and slug are "First Last" / `first-last` although the title box is hidden.
- [ ] Open `/card/first-last/`:
  - [ ] no theme header, footer, admin bar CSS or theme styles
  - [ ] response header `X-Robots-Tag: noindex, noarchive`; page source has the robots meta
  - [ ] city shows the company value; intro shows the company intro
  - [ ] Call opens the dialler with `+48…`, Email opens mail, WhatsApp opens wa.me
  - [ ] Directions opens a map search for the address
  - [ ] Share: system sheet on a phone; "Link copied" on desktop
  - [ ] Save contact links to `/card/first-last/vcard/` (404 is expected until stage 2)
- [ ] `?lang=de`: German job title, labels still English until translations ship (Task 29).
- [ ] Phone with browser language German, bare address → lands on `?lang=de`.
      Clicking PL in the switcher stays on PL.
- [ ] Colour scheme dark / auto (phone in dark mode) / light.
- [ ] Settings → change "Card address" to `wizytowka`: warning about printed codes
      appears once; `/wizytowka/first-last/` works without visiting Permalinks.
- [ ] With LiteSpeed Cache active: edit the job title, reload the card as a logged-out
      visitor: the change is visible without purging by hand.
- [ ] Sitemaps: `/wp-sitemap.xml` (and Yoast/Rank Math sitemap if installed) lists no cards.
- [ ] `debug.log` has no entry from the plugin after the whole pass.

## Stage 2 — QR and vCard (build 0.2.0-dev)

- [ ] Card screen, published card: "QR code" box shows the code; SVG and PNG download
      with names `slug-qr.svg` / `slug-qr.png`. Draft card: "Publish the card…" text only.
- [ ] Settings → QR codes: set module colour `#012169`, save. Try `#ffffff` on `#000000`:
      refused with a message, previous colours kept.
- [ ] Tick "Logo in the code" with a company logo set: box offers "SVG + logo" / "PNG + logo".
- [ ] **Scan every variant with two phones (iOS camera + Android), from the screen and
      from a paper print at 2.5 cm:** plain SVG, plain PNG, logo SVG, logo PNG.
      Each opens `/card/slug/?src=qr`.
- [ ] Team Cards → Print codes: ZIP contains `slug/slug.svg`, `slug.png` and the `-logo`
      pair per active card; inactive cards are absent.
- [ ] "Open test sheet": A4 print preview, 3 per row, name + role + address under each.
- [ ] Save contact on iPhone: contact opens with name, company, title, mobile, email,
      address, website, LinkedIn **and the photo**. Same on Android (Contacts app).
- [ ] `?lang=de` → Save contact gives the German job title.
- [ ] Polish characters (ą, ę, ł, ż) in name and title are intact in the saved contact.
- [ ] Mark a card inactive (post meta `_tbcards_inactive` = 1 until stage 3 adds the switch):
      `/card/slug/vcard/` answers 410 with no data.
- [ ] Server without GD (if available to test): only SVG buttons, no PHP errors.
- [ ] `debug.log` clean after the pass.

## Stage 3 — statistics and inactive cards (build 0.3.0-dev)

- [ ] Activation (or first admin load after update) creates `wp_tbcards_stats`; no dbDelta errors.
- [ ] Scan a printed code on a phone: card list shows +1 view; card box chart shows a
      blue (code) bar today. Address bar on the phone no longer shows `src=qr`.
- [ ] Open the plain link in a desktop browser: +1 view, grey (link) bar.
- [ ] Tap Call, Email, WhatsApp, Directions, Share, a social icon: Statistics page counts each.
- [ ] Save contact: "Contacts saved" +1 (counted once, not twice).
- [ ] Paste the card link into WhatsApp/Slack/Facebook: the link preview does **not** add a view.
- [ ] Reload a card 40 times quickly: views stop growing after 30 within the minute.
- [ ] Statistics page: date range, reversed range, "Download CSV" opens in Excel (PL locale)
      with Polish characters intact; a name starting with `=` shows as text.
- [ ] Settings → keep statistics 30 days: WP-Cron has `tbcards_stats_purge`; back to 0 removes it.
- [ ] Status box → "no longer works here" + notice: card shows company name, phone, website;
      no name, photo, email or mobile anywhere in the page source. vCard → 410.
- [ ] Successor mode → 302 to the successor (check the status code in dev tools, not 301).
- [ ] URL mode with an outside domain → notice instead of redirect.
- [ ] Inactive card is missing from Print codes and its ZIP.
- [ ] Change the slug of a published card: warning appears once; the old address
      redirects to the new one (WordPress old-slug handling).
- [ ] `debug.log` clean after the pass.

## Stage 4 — CSV (build 0.4.0-dev)

- [ ] Export with three cards → open in Excel (PL locale): columns split, Polish letters intact,
      phones as `+48 …` (not a formula, not an error), a multi-line intro in one cell.
- [ ] Change one German job title in Excel, save as CSV (semicolon) → import → preview says
      "update" for every row → Import → only that title changed; Polish titles untouched.
- [ ] Paste 5 rows copied straight from Excel/Google Sheets (tab separated) → preview works.
- [ ] Save as "CSV (MS-DOS)" / Windows-1250 in an old Excel → import → no mojibake.
- [ ] Row with a bad email, a missing photo, `status = retired`: shown as errors with their
      line numbers; the rest imports.
- [ ] Photo column with the file name of an image in the Media Library → becomes the card photo.
- [ ] 120 rows → imports in three batches without a timeout; "120 cards imported".
- [ ] A cell with `-` clears the field; an empty cell leaves it.
- [ ] Editor role (no `publish_posts`): no Import / Export menu.

## Stage 5 — embeds (build 0.5.0-dev)

- [ ] `[team_business_card slug="jan"]` in a page: card renders in the theme's content width,
      theme fonts do not break it; two shortcodes on one page load card.css/card.js once.
- [ ] `[team_business_card id="…" lang="de"]` → German title; on a Polylang/WPML page without `lang`
      the card follows the page language.
- [ ] Inactive card: nothing by default; `inactive="notice"` shows company details.
- [ ] Block editor: "Team Card" block, pick a card from the list, preview renders; saving
      the post stores only `<!-- wp:vizito-team-business-cards/card {...} /-->`.
- [ ] Logged-out `GET /wp-json/tbcards/v1/cards` → 401/403.
- [ ] **Bricks 2.x:** element "Team Card" in the panel; choosing a card re-renders it;
      check on the **published page**, not only in the builder; inside a Bricks
      *template* too (the Store Locator lost elements there silently).
- [ ] Clicks on an embedded card count on the Statistics page; the page view does not.

## Stage 6 — release candidate (build 1.0.0)

- [ ] **Plugin Check** (plugin "Plugin Check" by the WordPress.org team) on the installed ZIP:
      no errors. Warnings reviewed one by one.
- [ ] Site language Polski: admin screens, card labels ("Zapisz kontakt", "Zadzwoń") and the
      block editor strings are Polish. Same with Deutsch.
- [ ] Card page with `?lang=de` on a Polish site: German labels (switch_to_locale finds the
      plugin's own .mo).
- [ ] Screen reader (NVDA or VoiceOver) on a card: name as heading, every button and icon
      link announced with a name, new-tab links say so, language switcher announces languages.
- [ ] Keyboard only on a card: every control reachable, visible focus.
- [ ] Settings → accent `#ffd400`: saved with a warning about contrast.
- [ ] Delete the plugin with "Delete all …" off: cards and settings still there after
      reinstalling. With it on: cards, options and `wp_tbcards_stats` gone.
- [ ] Multisite (if available): network-activated plugin deleted with the option on per site.
- [ ] `debug.log` clean through the whole pass, with `WP_DEBUG` on, on PHP 8.0 and 8.3+.
