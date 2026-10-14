# Vizito Team Business Cards

Digital business cards for a whole team, served from the company's own
WordPress site. Each person gets a permanent mobile card that a printed QR code
points to: photo, role, one-tap **save contact** (vCard), call, email,
WhatsApp, directions, several languages. No per-seat subscription, no
third-party service.

**Status:** feature-complete, pre-release. Not yet in the WordPress.org
directory.

## What it does

- Card page independent of the theme: one small document, `noindex`, no public
  staff directory
- Company profile with per-field inheritance: shared data entered once
- Languages managed by the plugin (no Polylang needed), browser-language switch
  for scanned cards
- QR codes as SVG and PNG, custom colours with a contrast check, optional centre
  logo, a ZIP for the printer and an A4 test sheet
- vCard 3.0 with photo, UTF-8-safe line folding
- Statistics without cookies or personal data: daily counters per card, event
  and source (printed code or link)
- Leavers: notice, successor redirect (302) or URL — printed codes keep working
- CSV import (preview, batches, Windows-1250 and semicolon files from Excel)
  and export that imports back unchanged
- Shortcode, block (no build step) and Bricks Builder element over one renderer
- Polish and German translations included

## How it is built

- PHP 8.0+, WordPress 6.2+, **no dependencies and no build step**
- One QR library, vendored into the plugin's namespace by a script and tested
  module for module against the original — see `lib/qrcode/README.md` for three
  upstream limits the plugin works around
- Tests without PHPUnit or npm:

```bash
php -n tests/run.php
php -n -d extension=gd -d extension=zip tests/run.php
node --test --test-concurrency=1 "tests/js/*.test.js"
```

## Licence

GPL-2.0-or-later. The bundled qrcode-generator is MIT.
