# Team Business Cards Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans (or superpowers:subagent-driven-development) to implement this plan task-by-task.

**Goal:** A dependency-free WordPress plugin that gives every member of a company's staff a multilingual mobile business card at a permanent address, with vCard download, print-ready QR codes, privacy-safe statistics, CSV import and embedding (shortcode, block, Bricks).

**Architecture:** A non-public but publicly queryable custom post type `tbc_card` with post meta, read and written only through `Card_Repository`, which resolves translated and inherited values (card → default language → company profile) into an immutable `Card`. One `Card_Renderer` produces the card HTML for the theme-independent full page and for every embed. Statistics live in one own table of daily counters.

**Tech stack:** PHP 8.0+, WordPress 6.2+, vanilla JS (no build), bundled qrcode-generator PHP port (MIT, vendored into our namespace), GD for PNG. Tests: the author's own zero-dependency harness (`php tests/run.php`, `node --test`).

**Design:** `docs/plans/2026-09-25-team-business-cards-design.md` — read it first. Section numbers below (§) refer to it.

---

## How this plan is written

- **Tasks 1–7 are written out in full:** every test and every line of production code.
- **Tasks 8–13 (rest of stage 1) and all of stages 2–6 are task briefs:** files,
  the test cases that define "done", and every decision that is not obvious.
  Before a brief is executed it is expanded into the same full form as tasks 1–7,
  in a commit of its own, because earlier code will have moved the details.
  Do not execute a brief unexpanded.
- Every task is TDD: write the failing test, run it and see it fail for the
  stated reason, implement, run the whole suite, commit.

## Execution batches and usage control

Work runs in batches. Each batch ends at a working, committed, green state, and
nothing starts without a usage check (`get_usage`). Estimates are in percentage
points of the weekly limit, from the Store Locator's measured ~1 pp per task.

| Batch | Tasks | What exists afterwards | Estimate |
|---|---|---|---|
| A | 1–7 | Scaffold, settings, fields, company profile, repository, post type | 4–6 pp |
| B | 8–13 | Admin screens + a working card page (manual test on real WP) | 8–10 pp |
| C | 14–18 | QR (SVG/PNG/logo/ZIP/test sheet) and vCard | 7–9 pp |
| D | 19–22 | Statistics and inactive cards | 6–8 pp |
| E | 23–25 | CSV import and export | 4–5 pp |
| F | 26–28 | Shortcode, block, Bricks element | 4–5 pp |
| G | 29–32 | Translations, a11y, packaging, verification | 4–6 pp |

Rules:
- Check usage before a batch and after every task; report it in one line.
- Hard stop: weekly above 85 % or the 5-hour window above 80 %. Stop at the last green commit.
- Tasks with full code (1–7) are typed in directly; briefs are expanded, then run by
  subagents on the cheaper model, with review by the main session.
- A batch may be split mid-way at any task boundary; the plan and commits are the
  hand-off, nothing lives only in the conversation.

## Conventions (all tasks)

- **Paths** are relative to the repo root `G:\Projekty\up-work\team-business-cards`.
- **PHP binary:** `php` (portable 8.5, NTS). Always run with `-n`
  (no php.ini → no mbstring; production code must guard `mb_*` with `function_exists()`).
- **Run PHP tests:** `php -n tests/run.php`
- **Run JS tests:** `node --test --test-concurrency=1 "tests/js/*.test.js"` — always a glob, never a directory.
- **Lint one file:** `php -n -l path/to/file.php`
- **Namespace** `Asymetria\TeamCards`; sub-namespace = directory under `includes/`;
  file `class-<name-with-dashes>.php`. Example: `Asymetria\TeamCards\Card\Card_Repository` →
  `includes/card/class-card-repository.php`.
- **Guards:** every class file starts with `if ( ! defined( 'ABSPATH' ) ) { exit; }` and wraps its
  declaration in `if ( ! class_exists( ... ) )`, like the author's earlier plugins (see the header
  comment of `team-business-cards.php` for why).
- **Prefixes:** functions/options/hooks `tbc_`, meta `_tbc_`, CSS classes `tbcards-`, custom properties `--tbcards-`.
- **Text domain** `team-business-cards` on every user-facing string.
- **Commits:** author is already configured locally (`Asymetria`). Never add a `Co-Authored-By` line.
  Commit messages in English, imperative.
- **Stub state** lives in `$GLOBALS['tbc_stub']`, reset by `it()` before and after every case.
  Fixtures go in `before_each()`, never at file or group scope (the harness fails the case if you do).

---

# Stage 1 — Core

End state: cards can be created in the admin with translated and inherited fields, and
`/card/{slug}` renders a complete, theme-independent, multilingual card page. No vCard,
QR or statistics yet.

---

### Task 1: Scaffold, autoloader and test harness

**Files:**
- Create: `team-business-cards.php`
- Create: `includes/class-autoloader.php`
- Create: `includes/class-plugin.php`
- Create: `tests/framework.php`, `tests/run.php`, `tests/bootstrap.php` (adapted copies)
- Create: `tests/stubs-extra.php`
- Create: `tests/test-autoloader.php`

**Step 1: Copy the harness from the author's Store Locator and rename its prefixes**

The three files are the author's own code. Copy, then rename every prefix:

```bash
SRC="../store-locator-for-openstreetmap/tests"
mkdir -p tests/js
for f in framework.php run.php bootstrap.php; do
  sed -e 's/slosm_/tbc_/g' \
      -e 's/SLOSM_/TBC_/g' \
      -e 's/Store_Locator_For_OpenStreetMap/Team_Business_Cards/g' \
      -e 's#store-locator-for-openstreetmap#team-business-cards#g' \
      "$SRC/$f" > "tests/$f"
done
grep -n -i "slosm\|storelocator\|store_locator\|openstreetmap\|leaflet" tests/*.php
```

Expected: the final `grep` prints only comment lines (history notes about the Store Locator's own
tasks). Leave those comments or trim them; no code line may still say `slosm`.

**Step 2: Add a stub file for functions this plugin needs and the Store Locator never did**

Append one line to the very end of `tests/bootstrap.php`:

```php
require_once __DIR__ . '/stubs-extra.php';
```

Add these keys to the array returned by `tbc_stub_defaults()` in `tests/bootstrap.php`
(before its closing `);`):

```php
			// Team Business Cards additions; see tests/stubs-extra.php.
			'thumbnails'        => array(), // Featured image id per post id.
			'permalinks'        => array(), // Permalink per post id; default is home_url( '?p=ID' ).
			'meta_deleted'      => array(), // Every delete_post_meta() call: id and key.
			'rewrite_rules'     => array(), // Calls to add_rewrite_rule().
			'rewrite_tags'      => array(), // Calls to add_rewrite_tag().
			'rewrite_flushes'   => 0, // How many times flush_rewrite_rules() ran.
			'is_singular'       => array(), // Post types is_singular() answers true for.
			'query_vars'        => array(), // What get_query_var() answers, keyed by var.
			'located_templates' => array(), // Path locate_template() answers per requested name.
			'locale_switches'   => array(), // Every switch_to_locale() call.
			'locale_restores'   => 0, // How many times restore_previous_locale() ran.
			'attachment_urls'   => array(), // Url per attachment id, for wp_get_attachment_image_url().
			'headers_sent_list' => array(), // Headers the code under test asked to send through tbc_send_header().
			'bloginfo'          => array( 'name' => 'Example Site', 'charset' => 'UTF-8' ),
```

Create `tests/stubs-extra.php`:

```php
<?php
/**
 * WordPress functions Team Business Cards calls that the Store Locator's stubs
 * never needed. Same rules as bootstrap.php: dumb, recording, reset per case.
 *
 * @package Vizito_Team_Business_Cards
 */

if ( ! function_exists( 'sanitize_email' ) ) {
	function sanitize_email( $email ) {
		$email = trim( (string) $email );
		return is_email( $email ) ? $email : '';
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title ) {
		$title = strtolower( (string) $title );
		$title = preg_replace( '/[^a-z0-9\s-]/', '', $title );
		$title = preg_replace( '/[\s-]+/', '-', $title );
		return trim( $title, '-' );
	}
}

if ( ! function_exists( 'delete_post_meta' ) ) {
	function delete_post_meta( $post_id, $key, $value = '' ) {
		$GLOBALS['tbc_stub']['meta_deleted'][] = array( 'id' => (int) $post_id, 'key' => $key );
		unset( $GLOBALS['tbc_stub']['post_meta'][ $post_id ][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	function get_post_thumbnail_id( $post = null ) {
		$id = is_object( $post ) ? ( $post->ID ?? 0 ) : (int) $post;
		return (int) ( $GLOBALS['tbc_stub']['thumbnails'][ $id ] ?? 0 );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ) {
		$id = is_object( $post ) ? ( $post->ID ?? 0 ) : (int) $post;
		return $GLOBALS['tbc_stub']['permalinks'][ $id ] ?? home_url( '?p=' . $id );
	}
}

if ( ! function_exists( 'add_rewrite_rule' ) ) {
	function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
		$GLOBALS['tbc_stub']['rewrite_rules'][] = compact( 'regex', 'query', 'after' );
	}
}

if ( ! function_exists( 'add_rewrite_tag' ) ) {
	function add_rewrite_tag( $tag, $regex, $query = '' ) {
		$GLOBALS['tbc_stub']['rewrite_tags'][] = compact( 'tag', 'regex', 'query' );
	}
}

if ( ! function_exists( 'flush_rewrite_rules' ) ) {
	function flush_rewrite_rules( $hard = true ) {
		$GLOBALS['tbc_stub']['rewrite_flushes']++;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $post_types = '' ) {
		$types = (array) $post_types;
		foreach ( $types as $type ) {
			if ( in_array( $type, $GLOBALS['tbc_stub']['is_singular'], true ) ) {
				return true;
			}
		}
		return array() === $types || '' === $post_types ? ! empty( $GLOBALS['tbc_stub']['is_singular'] ) : false;
	}
}

if ( ! function_exists( 'get_query_var' ) ) {
	function get_query_var( $var, $default = '' ) {
		return $GLOBALS['tbc_stub']['query_vars'][ $var ] ?? $default;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id() {
		return (int) ( $GLOBALS['tbc_stub']['query_vars']['__queried_id'] ?? 0 );
	}
}

if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $names, $load = false, $once = true ) {
		foreach ( (array) $names as $name ) {
			if ( isset( $GLOBALS['tbc_stub']['located_templates'][ $name ] ) ) {
				return $GLOBALS['tbc_stub']['located_templates'][ $name ];
			}
		}
		return '';
	}
}

if ( ! function_exists( 'switch_to_locale' ) ) {
	function switch_to_locale( $locale ) {
		$GLOBALS['tbc_stub']['locale_switches'][] = $locale;
		return true;
	}
}

if ( ! function_exists( 'restore_previous_locale' ) ) {
	function restore_previous_locale() {
		$GLOBALS['tbc_stub']['locale_restores']++;
		return true;
	}
}

if ( ! function_exists( 'wp_get_attachment_image_url' ) ) {
	function wp_get_attachment_image_url( $id, $size = 'thumbnail', $icon = false ) {
		return $GLOBALS['tbc_stub']['attachment_urls'][ (int) $id ] ?? false;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '' ) {
		return $GLOBALS['tbc_stub']['bloginfo'][ $show ] ?? '';
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $text ) {
		return addslashes( (string) $text );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
}
```

If any of these already exists in the copied `bootstrap.php`, the `function_exists` guard makes the
extra copy dead; delete the duplicate from `stubs-extra.php` so there is one definition.

**Step 3: Write the failing autoloader test**

`tests/test-autoloader.php`:

```php
<?php
/**
 * Pins the class → file mapping every other file relies on.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-autoloader.php';

use Asymetria\TeamCards\Autoloader;

describe(
	'autoloader',
	function () {

		it(
			'maps a top-level class into includes/',
			function () {
				assert_same( 'includes/class-plugin.php', Autoloader::path_for( 'Asymetria\\TeamCards\\Plugin' ) );
			}
		);

		it(
			'maps a sub-namespace to a lowercase directory under includes/',
			function () {
				assert_same(
					'includes/card/class-card-repository.php',
					Autoloader::path_for( 'Asymetria\\TeamCards\\Card\\Card_Repository' )
				);
			}
		);

		it(
			'maps an underscored sub-namespace to a dashed directory',
			function () {
				assert_same(
					'includes/some-area/class-thing.php',
					Autoloader::path_for( 'Asymetria\\TeamCards\\Some_Area\\Thing' )
				);
			}
		);

		it(
			'ignores classes outside its namespace',
			function () {
				assert_same( null, Autoloader::path_for( 'Asymetria\\StoreLocator\\Plugin' ) );
				assert_same( null, Autoloader::path_for( 'Plugin' ) );
			}
		);

		it(
			'loads a real class file from disk',
			function () {
				Autoloader::register();
				assert_true( class_exists( 'Asymetria\\TeamCards\\Plugin' ) );
			}
		);
	}
);
```

**Step 4: Run it and see it fail**

Run: `php -n tests/run.php`
Expected: `FAIL  stopped while loading test-autoloader.php` — `Failed opening required …class-autoloader.php`.

**Step 5: Implement**

`includes/class-autoloader.php`:

```php
<?php
/**
 * Class autoloader.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Autoloader' ) ) {

	/**
	 * Maps Asymetria\TeamCards\Sub_Area\Class_Name to includes/sub-area/class-class-name.php.
	 */
	final class Autoloader {

		private const PREFIX = 'Asymetria\\TeamCards\\';

		/**
		 * Registers the loader with SPL.
		 *
		 * @return void
		 */
		public static function register(): void {
			spl_autoload_register( array( __CLASS__, 'load' ) );
		}

		/**
		 * Loads the file for a class, if it is one of ours and exists.
		 *
		 * @param string $class Fully qualified class name.
		 * @return void
		 */
		public static function load( string $class ): void {
			if ( ! defined( 'TBC_DIR' ) ) {
				return;
			}

			$relative = self::path_for( $class );

			if ( null !== $relative && is_readable( TBC_DIR . $relative ) ) {
				require_once TBC_DIR . $relative;
			}
		}

		/**
		 * The file a class lives in, relative to the plugin root.
		 *
		 * @param string $class Fully qualified class name.
		 * @return string|null Null for classes outside this plugin.
		 */
		public static function path_for( string $class ): ?string {
			if ( ! str_starts_with( $class, self::PREFIX ) ) {
				return null;
			}

			$parts = explode( '\\', substr( $class, strlen( self::PREFIX ) ) );
			$name  = array_pop( $parts );
			$dir   = 'includes';

			if ( array() !== $parts ) {
				$dir .= '/' . str_replace( '_', '-', strtolower( implode( '/', $parts ) ) );
			}

			return $dir . '/class-' . str_replace( '_', '-', strtolower( $name ) ) . '.php';
		}
	}
}
```

`includes/class-plugin.php` (grows task by task; each later task adds its lines to `boot()`):

```php
<?php
/**
 * Composition root.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Plugin' ) ) {

	/**
	 * Builds the services once and registers their hooks. No container: the
	 * graph is small enough to read top to bottom.
	 */
	final class Plugin {

		private static ?Plugin $instance = null;

		private bool $booted = false;

		/**
		 * The single instance.
		 *
		 * @return Plugin
		 */
		public static function instance(): Plugin {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Registers every hook. Safe to call twice.
		 *
		 * @return void
		 */
		public function boot(): void {
			if ( $this->booted ) {
				return;
			}

			$this->booted = true;
		}
	}
}
```

`team-business-cards.php`:

```php
<?php
/**
 * Plugin Name:       Team Business Cards
 * Description:       Digital business cards for your whole team, on your own domain: QR codes for print, vCard download, several languages, privacy-friendly statistics.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Asymetria
 * Author URI:        https://asymetria.com.pl
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       team-business-cards
 * Domain Path:       /languages
 *
 * Every declaration below is guarded — defined() for constants, class_exists()
 * for classes, function_exists() for functions. PHP binds unconditional class
 * and function declarations at compile time, so a second copy of this code
 * loaded from elsewhere would otherwise be a fatal error on a live site.
 *
 * There is deliberately no Plugin URI header: WordPress.org rejects a
 * submission whose Plugin URI equals its Author URI.
 *
 * @package Vizito_Team_Business_Cards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TBC_VERSION' ) ) {
	define( 'TBC_VERSION', '0.1.0' );
	define( 'TBC_FILE', __FILE__ );
	define( 'TBC_DIR', plugin_dir_path( __FILE__ ) );
	define( 'TBC_URL', plugin_dir_url( __FILE__ ) );
}

require_once __DIR__ . '/includes/class-autoloader.php';

if ( ! function_exists( 'tbc_bootstrap' ) ) {

	/**
	 * Registers the autoloader and boots the plugin.
	 *
	 * @return void
	 */
	function tbc_bootstrap(): void {
		\Asymetria\TeamCards\Autoloader::register();
		\Asymetria\TeamCards\Plugin::instance()->boot();
	}
}

tbc_bootstrap();
```

**Step 6: Run the suite**

Run: `php -n tests/run.php`
Expected: `5 passed, 0 failed`. Lint all three PHP files with `-l`.

**Step 7: Commit**

```bash
git add team-business-cards.php includes tests
git commit -m "Scaffold plugin, autoloader and test harness"
```

---

### Task 2: Settings

The plugin-wide option `tbc_settings` (§5 Languages, §6 Appearance, §9 retention, §14 uninstall).
Language rows are entered in a textarea, one per line, `code locale` (e.g. `de de_DE`) — no JS
needed for a repeatable row UI.

**Files:**
- Create: `includes/class-settings.php`
- Test: `tests/test-settings.php`

**Step 1: Write the failing test**

`tests/test-settings.php`:

```php
<?php
/**
 * Pins defaults, reading and sanitising of the plugin settings.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-settings.php';

use Asymetria\TeamCards\Settings;

describe(
	'settings: defaults and reading',
	function () {

		it(
			'derives the first language from the site locale',
			function () {
				$GLOBALS['tbc_stub']['options']['locale'] = 'pl_PL';
				$settings = new Settings();

				assert_same( array( 'pl' ), $settings->languages() );
				assert_same( 'pl', $settings->default_language() );
				assert_same( 'pl_PL', $settings->locale_for( 'pl' ) );
			}
		);

		it(
			'reads saved values over defaults and ignores unknown keys',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_settings'] = array(
					'languages'        => array(
						array( 'code' => 'pl', 'locale' => 'pl_PL' ),
						array( 'code' => 'de', 'locale' => 'de_DE' ),
					),
					'default_language' => 'pl',
					'url_base'         => 'wizytowka',
					'junk'             => 'x',
				);
				$settings = new Settings();

				assert_same( array( 'pl', 'de' ), $settings->languages() );
				assert_same( 'wizytowka', $settings->get( 'url_base' ) );
				assert_same( null, $settings->get( 'junk' ) );
				assert_same( 'auto', $settings->get( 'scheme' ) );
			}
		);

		it(
			'answers an empty locale for a language it does not have',
			function () {
				assert_same( '', ( new Settings() )->locale_for( 'xx' ) );
			}
		);

		it(
			'survives a non-array option',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_settings'] = 'garbage';
				assert_same( 'card', ( new Settings() )->get( 'url_base' ) );
			}
		);
	}
);

describe(
	'settings: sanitise',
	function () {

		it(
			'parses language lines from a textarea',
			function () {
				$clean = ( new Settings() )->sanitize(
					array( 'languages' => "pl pl_PL\nDE  de_DE\r\n\nen" )
				);

				assert_same(
					array(
						array( 'code' => 'pl', 'locale' => 'pl_PL' ),
						array( 'code' => 'de', 'locale' => 'de_DE' ),
						array( 'code' => 'en', 'locale' => '' ),
					),
					$clean['languages']
				);
			}
		);

		it(
			'drops invalid codes, invalid locales and duplicates',
			function () {
				$clean = ( new Settings() )->sanitize(
					array( 'languages' => "pl pl_PL\npolski pl_PL\npl de_DE\nde <script>" )
				);

				assert_same(
					array(
						array( 'code' => 'pl', 'locale' => 'pl_PL' ),
						array( 'code' => 'de', 'locale' => '' ),
					),
					$clean['languages']
				);
			}
		);

		it(
			'falls back to the default language list when nothing valid is left',
			function () {
				$GLOBALS['tbc_stub']['options']['locale'] = 'en_GB';
				$clean = ( new Settings() )->sanitize( array( 'languages' => "!!\n" ) );

				assert_same( array( array( 'code' => 'en', 'locale' => 'en_GB' ) ), $clean['languages'] );
			}
		);

		it(
			'keeps the default language only when it is on the list',
			function () {
				$settings = new Settings();

				$kept = $settings->sanitize( array( 'languages' => "pl pl_PL\nde de_DE", 'default_language' => 'de' ) );
				$lost = $settings->sanitize( array( 'languages' => "pl pl_PL\nde de_DE", 'default_language' => 'fr' ) );

				assert_same( 'de', $kept['default_language'] );
				assert_same( 'pl', $lost['default_language'] );
			}
		);

		it(
			'normalises the url base and never lets it be empty',
			function () {
				$settings = new Settings();

				assert_same( 'wizytowka', $settings->sanitize( array( 'url_base' => ' Wizytowka/ ' ) )['url_base'] );
				assert_same( 'card', $settings->sanitize( array( 'url_base' => '///' ) )['url_base'] );
			}
		);

		it(
			'accepts only listed schemes, shapes and valid colours',
			function () {
				$clean = ( new Settings() )->sanitize(
					array(
						'scheme'      => 'neon',
						'photo_shape' => 'square',
						'accent'      => 'red',
					)
				);

				assert_same( 'auto', $clean['scheme'] );
				assert_same( 'square', $clean['photo_shape'] );
				assert_same( '#1d4ed8', $clean['accent'] );
			}
		);

		it(
			'casts booleans and bounds retention',
			function () {
				$clean = ( new Settings() )->sanitize(
					array(
						'auto_language'  => '',
						'delete_data'    => '1',
						'retention_days' => '99999',
					)
				);

				assert_false( $clean['auto_language'] );
				assert_true( $clean['delete_data'] );
				assert_same( 3650, $clean['retention_days'] );
			}
		);

		it(
			'asks for a rewrite flush when the url base changes, and only then',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_settings'] = array( 'url_base' => 'card' );
				$settings = new Settings();

				$settings->sanitize( array( 'url_base' => 'card' ) );
				assert_false( isset( $GLOBALS['tbc_stub']['options']['tbc_flush_rewrite'] ) );

				$settings->sanitize( array( 'url_base' => 'wizytowka' ) );
				assert_same( 1, $GLOBALS['tbc_stub']['options']['tbc_flush_rewrite'] );
			}
		);
	}
);
```

**Step 2: Run and see it fail** — `stopped while loading test-settings.php`, missing file.

**Step 3: Implement** `includes/class-settings.php`:

```php
<?php
/**
 * Plugin-wide settings.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Settings' ) ) {

	/**
	 * Reads and sanitises the `tbc_settings` option. Values are sanitised on the
	 * way in (register_setting), so reading only merges over defaults.
	 */
	final class Settings {

		public const OPTION        = 'tbc_settings';
		public const FLUSH_OPTION  = 'tbc_flush_rewrite';
		public const SCHEMES       = array( 'auto', 'light', 'dark' );
		public const SHAPES        = array( 'round', 'square' );
		public const MAX_LANGUAGES = 10;
		public const MAX_RETENTION = 3650;

		private const CODE_PATTERN   = '/^[a-z]{2,3}$/';
		private const LOCALE_PATTERN = '/^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?$/';

		/**
		 * Default values. The first language comes from the site locale.
		 *
		 * @return array
		 */
		public function defaults(): array {
			$locale = function_exists( 'determine_locale' ) ? (string) determine_locale() : 'en_US';
			$code   = strtolower( (string) strtok( $locale, '_' ) );

			if ( ! preg_match( self::CODE_PATTERN, $code ) ) {
				$code   = 'en';
				$locale = 'en_US';
			}

			return array(
				'languages'        => array( array( 'code' => $code, 'locale' => $locale ) ),
				'default_language' => $code,
				'url_base'         => 'card',
				'auto_language'    => true,
				'accent'           => '#1d4ed8',
				'scheme'           => 'auto',
				'photo_shape'      => 'round',
				'vcard_photo'      => true,
				'retention_days'   => 0,
				'delete_data'      => false,
			);
		}

		/**
		 * All settings, saved over defaults, unknown keys dropped.
		 *
		 * @return array
		 */
		public function all(): array {
			$defaults = $this->defaults();
			$saved    = get_option( self::OPTION, array() );

			if ( ! is_array( $saved ) ) {
				$saved = array();
			}

			return array_replace( $defaults, array_intersect_key( $saved, $defaults ) );
		}

		/**
		 * One setting.
		 *
		 * @param string $key Setting key.
		 * @return mixed Null for an unknown key.
		 */
		public function get( string $key ) {
			return $this->all()[ $key ] ?? null;
		}

		/**
		 * Language codes, in switcher order.
		 *
		 * @return string[]
		 */
		public function languages(): array {
			return array_values( array_map( static fn( $row ) => $row['code'], $this->get( 'languages' ) ) );
		}

		/**
		 * The default language code.
		 *
		 * @return string
		 */
		public function default_language(): string {
			return (string) $this->get( 'default_language' );
		}

		/**
		 * The WordPress locale mapped to a language code.
		 *
		 * @param string $code Language code.
		 * @return string Empty when unknown or not mapped.
		 */
		public function locale_for( string $code ): string {
			foreach ( $this->get( 'languages' ) as $row ) {
				if ( $row['code'] === $code ) {
					return (string) $row['locale'];
				}
			}

			return '';
		}

		/**
		 * The register_setting() sanitize callback.
		 *
		 * @param mixed $input Raw form input.
		 * @return array
		 */
		public function sanitize( $input ): array {
			$input    = is_array( $input ) ? $input : array();
			$current  = $this->all();
			$defaults = $this->defaults();
			$clean    = $current;

			if ( array_key_exists( 'languages', $input ) ) {
				$clean['languages'] = $this->sanitize_languages( $input['languages'] );

				if ( array() === $clean['languages'] ) {
					$clean['languages'] = $defaults['languages'];
				}
			}

			$codes = array_map( static fn( $row ) => $row['code'], $clean['languages'] );
			$want  = isset( $input['default_language'] ) ? sanitize_key( (string) $input['default_language'] ) : $clean['default_language'];

			$clean['default_language'] = in_array( $want, $codes, true ) ? $want : $codes[0];

			if ( array_key_exists( 'url_base', $input ) ) {
				$base              = sanitize_title( (string) $input['url_base'] );
				$clean['url_base'] = '' === $base ? $defaults['url_base'] : $base;
			}

			if ( array_key_exists( 'accent', $input ) ) {
				$clean['accent'] = sanitize_hex_color( (string) $input['accent'] ) ?: $defaults['accent'];
			}

			if ( array_key_exists( 'scheme', $input ) ) {
				$clean['scheme'] = in_array( $input['scheme'], self::SCHEMES, true ) ? $input['scheme'] : $defaults['scheme'];
			}

			if ( array_key_exists( 'photo_shape', $input ) ) {
				$clean['photo_shape'] = in_array( $input['photo_shape'], self::SHAPES, true ) ? $input['photo_shape'] : $defaults['photo_shape'];
			}

			foreach ( array( 'auto_language', 'vcard_photo', 'delete_data' ) as $flag ) {
				if ( array_key_exists( $flag, $input ) ) {
					$clean[ $flag ] = ! empty( $input[ $flag ] );
				}
			}

			if ( array_key_exists( 'retention_days', $input ) ) {
				$clean['retention_days'] = min( self::MAX_RETENTION, absint( $input['retention_days'] ) );
			}

			if ( $clean['url_base'] !== $current['url_base'] ) {
				update_option( self::FLUSH_OPTION, 1 );
			}

			return $clean;
		}

		/**
		 * Language rows from a textarea string or an array of rows.
		 *
		 * @param mixed $raw Raw input.
		 * @return array<int, array{code: string, locale: string}>
		 */
		private function sanitize_languages( $raw ): array {
			if ( is_string( $raw ) ) {
				$rows = array();

				foreach ( preg_split( '/\R/', $raw ) as $line ) {
					$bits   = preg_split( '/\s+/', trim( $line ) );
					$rows[] = array(
						'code'   => $bits[0] ?? '',
						'locale' => $bits[1] ?? '',
					);
				}

				$raw = $rows;
			}

			$clean = array();

			foreach ( is_array( $raw ) ? $raw : array() as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$code   = strtolower( trim( (string) ( $row['code'] ?? '' ) ) );
				$locale = trim( (string) ( $row['locale'] ?? '' ) );

				if ( ! preg_match( self::CODE_PATTERN, $code ) || isset( $clean[ $code ] ) ) {
					continue;
				}

				$clean[ $code ] = array(
					'code'   => $code,
					'locale' => preg_match( self::LOCALE_PATTERN, $locale ) ? $locale : '',
				);

				if ( count( $clean ) >= self::MAX_LANGUAGES ) {
					break;
				}
			}

			return array_values( $clean );
		}
	}
}
```

Note: in the "drops invalid" test, line `polski pl_PL` fails the code pattern and `pl de_DE` is a
duplicate code, so only the first `pl` survives; `de <script>` keeps `de` with an empty locale.

**Step 4: Run the suite** — all pass. **Step 5: Commit** `git commit -m "Add plugin settings with language list and sanitising"`.

---

### Task 3: Field definitions

One source of truth for the form, the save, the vCard and CSV (§5 Card fields).

**Files:**
- Create: `includes/card/class-fields.php`
- Test: `tests/test-fields.php`

**Step 1: Write the failing test**

```php
<?php
/**
 * Pins the field catalogue: which fields exist, which are translated, which inherit.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/card/class-fields.php';

use Asymetria\TeamCards\Card\Fields;

describe(
	'fields',
	function () {

		it(
			'defines every field with label, type, group and two flags',
			function () {
				foreach ( Fields::all() as $key => $def ) {
					assert_true( is_string( $def['label'] ) && '' !== $def['label'], "$key label" );
					assert_true( in_array( $def['type'], Fields::TYPES, true ), "$key type" );
					assert_true( is_string( $def['group'] ), "$key group" );
					assert_true( is_bool( $def['translated'] ), "$key translated" );
					assert_true( is_bool( $def['inherits'] ), "$key inherits" );
				}
			}
		);

		it(
			'translates exactly the job title, the intro and the custom labels',
			function () {
				assert_same(
					array( 'job_title', 'intro', 'custom_1_label', 'custom_2_label', 'custom_3_label' ),
					Fields::translated()
				);
			}
		);

		it(
			'lets the company profile supply the intro, landline, address and links',
			function () {
				assert_same(
					array( 'intro', 'landline', 'street', 'postcode', 'city', 'country', 'map_url', 'website', 'linkedin', 'facebook', 'instagram', 'x', 'youtube', 'tiktok', 'github', 'xing' ),
					Fields::inheritable()
				);
			}
		);

		it(
			'never lets personal fields inherit',
			function () {
				foreach ( array( 'first_name', 'last_name', 'job_title', 'mobile', 'email', 'whatsapp' ) as $key ) {
					assert_false( Fields::get( $key )['inherits'], $key );
				}
			}
		);

		it(
			'lists social networks in display order',
			function () {
				assert_same( array( 'linkedin', 'facebook', 'instagram', 'x', 'youtube', 'tiktok', 'github', 'xing' ), Fields::social() );
			}
		);

		it(
			'answers null for an unknown field',
			function () {
				assert_same( null, Fields::get( 'nope' ) );
			}
		);
	}
);
```

**Step 2: Run, see it fail.**

**Step 3: Implement** `includes/card/class-fields.php`:

```php
<?php
/**
 * Card field catalogue.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Fields' ) ) {

	/**
	 * Every card field, defined once. Order here is the order of the form,
	 * the CSV columns and the card.
	 */
	final class Fields {

		public const TYPES      = array( 'text', 'textarea', 'tel', 'email', 'url' );
		public const MAX_CUSTOM = 3;

		private const SOCIAL = array( 'linkedin', 'facebook', 'instagram', 'x', 'youtube', 'tiktok', 'github', 'xing' );

		/**
		 * All fields, keyed by field key.
		 *
		 * @return array<string, array{label: string, type: string, group: string, translated: bool, inherits: bool}>
		 */
		public static function all(): array {
			$d = 'team-business-cards';

			$fields = array(
				'first_name' => self::def( __( 'First name', $d ), 'text', 'person' ),
				'last_name'  => self::def( __( 'Last name', $d ), 'text', 'person' ),
				'job_title'  => self::def( __( 'Job title', $d ), 'text', 'role', true ),
				'intro'      => self::def( __( 'Intro', $d ), 'textarea', 'role', true, true ),
				'mobile'     => self::def( __( 'Mobile phone', $d ), 'tel', 'contact' ),
				'landline'   => self::def( __( 'Landline', $d ), 'tel', 'contact', false, true ),
				'email'      => self::def( __( 'Email', $d ), 'email', 'contact' ),
				'whatsapp'   => self::def( __( 'WhatsApp number', $d ), 'tel', 'contact' ),
				'street'     => self::def( __( 'Street and number', $d ), 'text', 'address', false, true ),
				'postcode'   => self::def( __( 'Postcode', $d ), 'text', 'address', false, true ),
				'city'       => self::def( __( 'City', $d ), 'text', 'address', false, true ),
				'country'    => self::def( __( 'Country', $d ), 'text', 'address', false, true ),
				'map_url'    => self::def( __( 'Map link', $d ), 'url', 'address', false, true ),
				'website'    => self::def( __( 'Website', $d ), 'url', 'links', false, true ),
				'linkedin'   => self::def( 'LinkedIn', 'url', 'links', false, true ),
				'facebook'   => self::def( 'Facebook', 'url', 'links', false, true ),
				'instagram'  => self::def( 'Instagram', 'url', 'links', false, true ),
				'x'          => self::def( 'X', 'url', 'links', false, true ),
				'youtube'    => self::def( 'YouTube', 'url', 'links', false, true ),
				'tiktok'     => self::def( 'TikTok', 'url', 'links', false, true ),
				'github'     => self::def( 'GitHub', 'url', 'links', false, true ),
				'xing'       => self::def( 'XING', 'url', 'links', false, true ),
			);

			for ( $i = 1; $i <= self::MAX_CUSTOM; $i++ ) {
				/* translators: %d: button number, 1 to 3. */
				$fields[ "custom_{$i}_label" ] = self::def( sprintf( __( 'Button %d label', $d ), $i ), 'text', 'custom', true );
				/* translators: %d: button number, 1 to 3. */
				$fields[ "custom_{$i}_url" ] = self::def( sprintf( __( 'Button %d link', $d ), $i ), 'url', 'custom' );
			}

			return $fields;
		}

		/**
		 * One field definition.
		 *
		 * @param string $key Field key.
		 * @return array|null
		 */
		public static function get( string $key ): ?array {
			return self::all()[ $key ] ?? null;
		}

		/**
		 * Keys of translated fields, in catalogue order.
		 *
		 * @return string[]
		 */
		public static function translated(): array {
			return array_keys( array_filter( self::all(), static fn( $f ) => $f['translated'] ) );
		}

		/**
		 * Keys of fields the company profile can supply, in catalogue order.
		 *
		 * @return string[]
		 */
		public static function inheritable(): array {
			return array_keys( array_filter( self::all(), static fn( $f ) => $f['inherits'] ) );
		}

		/**
		 * Social network keys, in display order.
		 *
		 * @return string[]
		 */
		public static function social(): array {
			return self::SOCIAL;
		}

		/**
		 * Builds one definition.
		 */
		private static function def( string $label, string $type, string $group, bool $translated = false, bool $inherits = false ): array {
			return array(
				'label'      => $label,
				'type'       => $type,
				'group'      => $group,
				'translated' => $translated,
				'inherits'   => $inherits,
			);
		}
	}
}
```

Note for the implementer: `__( 'First name', $d )` with a variable domain is flagged by Plugin
Check / i18n tooling. Before committing, replace every `$d` in `__()` calls with the literal
`'team-business-cards'` (the variable is shown above only to keep the plan readable).

**Step 4: Run suite, pass. Step 5: Commit** `git commit -m "Add card field catalogue"`.

---

### Task 4: Field sanitiser

**Files:**
- Create: `includes/card/class-field-sanitizer.php`
- Test: `tests/test-field-sanitizer.php`

Decision recorded here (refines design §5 "Validation on save"): phones are **stored as typed**
(cleaned of anything that is not a digit, space, `+ ( ) - . /`); the E.164-style form used in
`tel:`, `wa.me` and the vCard is **computed on output** by `Field_Sanitizer::dial()`. One stored
value, no second meta key to drift out of sync. A number without `+` or `00` has no country code
and is dialled as digits only — the plugin never guesses a country.

**Step 1: Failing test** `tests/test-field-sanitizer.php`:

```php
<?php
/**
 * Pins sanitising per field type and the dialable phone form.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/card/class-fields.php';
require_once dirname( __DIR__ ) . '/includes/card/class-field-sanitizer.php';

use Asymetria\TeamCards\Card\Field_Sanitizer;

describe(
	'field sanitizer: values',
	function () {

		it(
			'strips tags from text and keeps line breaks in textareas',
			function () {
				assert_same( 'Jan', Field_Sanitizer::value( 'text', ' <b>Jan</b> ' ) );
				assert_same( "Line one\nLine two", Field_Sanitizer::value( 'textarea', "Line one\nLine two" ) );
			}
		);

		it(
			'empties an invalid email',
			function () {
				assert_same( 'jan@example.com', Field_Sanitizer::value( 'email', ' jan@example.com ' ) );
				assert_same( '', Field_Sanitizer::value( 'email', 'not-an-email' ) );
			}
		);

		it(
			'allows only http and https links',
			function () {
				assert_same( 'https://example.com/a', Field_Sanitizer::value( 'url', 'https://example.com/a' ) );
				assert_same( '', Field_Sanitizer::value( 'url', 'javascript:alert(1)' ) );
				assert_same( '', Field_Sanitizer::value( 'url', 'ftp://example.com' ) );
			}
		);

		it(
			'keeps a phone as typed, minus anything that is not part of a number',
			function () {
				assert_same( '+48 609 909 828', Field_Sanitizer::value( 'tel', '+48 609 909 828' ) );
				assert_same( '(22) 123-45-67', Field_Sanitizer::value( 'tel', '(22) 123-45-67 <x>' ) );
			}
		);

		it(
			'returns an empty string for non-scalars',
			function () {
				assert_same( '', Field_Sanitizer::value( 'text', array( 'a' ) ) );
			}
		);
	}
);

describe(
	'field sanitizer: translated values',
	function () {

		it(
			'keeps only configured languages and drops empty ones',
			function () {
				assert_same(
					array( 'pl' => 'Prezes', 'de' => 'Geschäftsführer' ),
					Field_Sanitizer::translated( 'text', array( 'pl' => 'Prezes', 'de' => 'Geschäftsführer', 'en' => '  ', 'fr' => 'PDG' ), array( 'pl', 'de', 'en' ) )
				);
			}
		);

		it(
			'answers an empty array for non-array input',
			function () {
				assert_same( array(), Field_Sanitizer::translated( 'text', 'Prezes', array( 'pl' ) ) );
			}
		);
	}
);

describe(
	'field sanitizer: dial',
	function () {

		it(
			'turns an international number into plus and digits',
			function () {
				assert_same( '+48609909828', Field_Sanitizer::dial( '+48 609 909 828' ) );
				assert_same( '+48609909828', Field_Sanitizer::dial( '0048 609 909 828' ) );
			}
		);

		it(
			'dials a local number as digits and never invents a country',
			function () {
				assert_same( '221234567', Field_Sanitizer::dial( '(22) 123-45-67' ) );
			}
		);

		it(
			'answers empty for something with no digits',
			function () {
				assert_same( '', Field_Sanitizer::dial( '+ ( )' ) );
			}
		);
	}
);
```

**Step 2: Run, fail. Step 3: Implement** `includes/card/class-field-sanitizer.php`:

```php
<?php
/**
 * Field sanitising.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Field_Sanitizer' ) ) {

	/**
	 * Sanitises one value by field type. Callers unslash request data first.
	 */
	final class Field_Sanitizer {

		public const URL_SCHEMES = array( 'http', 'https' );

		/**
		 * Sanitises a single value.
		 *
		 * @param string $type Field type from Fields::TYPES.
		 * @param mixed  $raw  Raw value.
		 * @return string
		 */
		public static function value( string $type, $raw ): string {
			if ( ! is_scalar( $raw ) ) {
				return '';
			}

			$raw = (string) $raw;

			switch ( $type ) {
				case 'textarea':
					return sanitize_textarea_field( $raw );
				case 'email':
					return (string) sanitize_email( $raw );
				case 'url':
					return (string) esc_url_raw( trim( $raw ), self::URL_SCHEMES );
				case 'tel':
					$tel = preg_replace( '/[^0-9+()\-.\/ ]/', '', $raw );
					return trim( (string) preg_replace( '/\s+/', ' ', (string) $tel ) );
				default:
					return sanitize_text_field( $raw );
			}
		}

		/**
		 * Sanitises a per-language value, keeping configured, non-empty languages only.
		 *
		 * @param string   $type  Field type.
		 * @param mixed    $raw   Raw value keyed by language code.
		 * @param string[] $codes Configured language codes.
		 * @return array<string, string>
		 */
		public static function translated( string $type, $raw, array $codes ): array {
			if ( ! is_array( $raw ) ) {
				return array();
			}

			$clean = array();

			foreach ( $codes as $code ) {
				$value = self::value( $type, $raw[ $code ] ?? '' );

				if ( '' !== trim( $value ) ) {
					$clean[ $code ] = $value;
				}
			}

			return $clean;
		}

		/**
		 * The dialable form of a stored phone number.
		 *
		 * @param string $phone Phone as stored.
		 * @return string "+" and digits for international numbers, digits otherwise, "" when none.
		 */
		public static function dial( string $phone ): string {
			$phone  = trim( $phone );
			$digits = (string) preg_replace( '/\D/', '', $phone );

			if ( '' === $digits ) {
				return '';
			}

			if ( str_starts_with( $phone, '+' ) ) {
				return '+' . $digits;
			}

			if ( str_starts_with( $digits, '00' ) ) {
				return '+' . substr( $digits, 2 );
			}

			return $digits;
		}
	}
}
```

Check before running: the copied `sanitize_text_field` stub must strip tags (`<b>Jan</b>` → `Jan`)
and trim. If it does not, fix the stub to match core behaviour, not the test.

**Step 4: Run, pass. Step 5: Commit** `git commit -m "Add field sanitiser and dialable phone form"`.

---

### Task 5: Company profile

**Files:**
- Create: `includes/company/class-company-profile.php`
- Test: `tests/test-company-profile.php`

**Step 1: Failing test**

```php
<?php
/**
 * Pins the company profile: its own fields, per-language resolution and sanitising.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/card/class-fields.php';
require_once dirname( __DIR__ ) . '/includes/card/class-field-sanitizer.php';
require_once dirname( __DIR__ ) . '/includes/company/class-company-profile.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Company\Company_Profile;

describe(
	'company profile',
	function () {

		before_each(
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_settings'] = array(
					'languages'        => array(
						array( 'code' => 'pl', 'locale' => 'pl_PL' ),
						array( 'code' => 'de', 'locale' => 'de_DE' ),
					),
					'default_language' => 'pl',
				);
			}
		);

		it(
			'has the company name, the logo and every inheritable card field',
			function () {
				$keys = array_keys( Company_Profile::fields() );

				assert_same( 'company_name', $keys[0] );
				assert_same( 'logo_id', $keys[1] );
				assert_same( array_merge( array( 'company_name', 'logo_id' ), \Asymetria\TeamCards\Card\Fields::inheritable() ), $keys );
			}
		);

		it(
			'resolves a translated value in the asked language, then the default',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_company'] = array(
					'intro' => array( 'pl' => 'Tworzymy automatyzacje.' ),
				);
				$company = new Company_Profile( new Settings() );

				assert_same( 'Tworzymy automatyzacje.', $company->value( 'intro', 'de' ) );
			}
		);

		it(
			'answers plain values regardless of language, and empty for unknown keys',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_company'] = array( 'city' => 'Warszawa' );
				$company = new Company_Profile( new Settings() );

				assert_same( 'Warszawa', $company->value( 'city', 'de' ) );
				assert_same( '', $company->value( 'nope', 'pl' ) );
			}
		);

		it(
			'reads the logo as an integer',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_company'] = array( 'logo_id' => '42' );
				assert_same( 42, ( new Company_Profile( new Settings() ) )->logo_id() );
			}
		);

		it(
			'sanitises by field type and drops unknown keys',
			function () {
				$clean = ( new Company_Profile( new Settings() ) )->sanitize(
					array(
						'company_name' => '<i>Example</i> Ltd',
						'logo_id'      => '7x',
						'website'      => 'javascript:x',
						'intro'        => array( 'pl' => 'Cześć', 'fr' => 'Salut' ),
						'hack'         => 'x',
					)
				);

				assert_same( 'Example Ltd', $clean['company_name'] );
				assert_same( 7, $clean['logo_id'] );
				assert_same( '', $clean['website'] );
				assert_same( array( 'pl' => 'Cześć' ), $clean['intro'] );
				assert_false( isset( $clean['hack'] ) );
			}
		);
	}
);
```

**Step 2: Run, fail. Step 3: Implement** `includes/company/class-company-profile.php`:

```php
<?php
/**
 * Company profile: shared values cards inherit.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Company;

use Asymetria\TeamCards\Card\Field_Sanitizer;
use Asymetria\TeamCards\Card\Fields;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Company_Profile' ) ) {

	/**
	 * The `tbc_company` option.
	 */
	final class Company_Profile {

		public const OPTION = 'tbc_company';

		public function __construct( private Settings $settings ) {
		}

		/**
		 * Company fields: name, logo, then every inheritable card field.
		 *
		 * @return array<string, array>
		 */
		public static function fields(): array {
			$fields = array(
				'company_name' => array(
					'label'      => __( 'Company name', 'team-business-cards' ),
					'type'       => 'text',
					'group'      => 'company',
					'translated' => false,
					'inherits'   => false,
				),
				'logo_id'      => array(
					'label'      => __( 'Logo', 'team-business-cards' ),
					'type'       => 'attachment',
					'group'      => 'company',
					'translated' => false,
					'inherits'   => false,
				),
			);

			foreach ( Fields::inheritable() as $key ) {
				$fields[ $key ] = Fields::get( $key );
			}

			return $fields;
		}

		/**
		 * The raw option.
		 *
		 * @return array
		 */
		public function raw(): array {
			$saved = get_option( self::OPTION, array() );
			return is_array( $saved ) ? $saved : array();
		}

		/**
		 * A value in a language, falling back to the default language.
		 *
		 * @param string $key  Field key.
		 * @param string $lang Language code.
		 * @return string
		 */
		public function value( string $key, string $lang ): string {
			$raw = $this->raw()[ $key ] ?? '';

			if ( is_array( $raw ) ) {
				return self::pick( $raw, $lang, $this->settings->default_language() );
			}

			return is_scalar( $raw ) ? trim( (string) $raw ) : '';
		}

		/**
		 * The logo attachment id, 0 when none.
		 *
		 * @return int
		 */
		public function logo_id(): int {
			return absint( $this->raw()['logo_id'] ?? 0 );
		}

		/**
		 * The register_setting() sanitize callback.
		 *
		 * @param mixed $input Raw input.
		 * @return array
		 */
		public function sanitize( $input ): array {
			$input = is_array( $input ) ? $input : array();
			$codes = $this->settings->languages();
			$clean = array();

			foreach ( self::fields() as $key => $def ) {
				if ( ! array_key_exists( $key, $input ) ) {
					continue;
				}

				if ( 'attachment' === $def['type'] ) {
					$clean[ $key ] = absint( $input[ $key ] );
				} elseif ( $def['translated'] ) {
					$clean[ $key ] = Field_Sanitizer::translated( $def['type'], $input[ $key ], $codes );
				} else {
					$clean[ $key ] = Field_Sanitizer::value( $def['type'], $input[ $key ] );
				}
			}

			return $clean;
		}

		/**
		 * First non-empty value among the asked and the default language.
		 *
		 * @param array  $values  Values keyed by language.
		 * @param string $lang    Asked language.
		 * @param string $default Default language.
		 * @return string
		 */
		public static function pick( array $values, string $lang, string $default ): string {
			foreach ( array( $lang, $default ) as $code ) {
				if ( isset( $values[ $code ] ) && is_scalar( $values[ $code ] ) && '' !== trim( (string) $values[ $code ] ) ) {
					return trim( (string) $values[ $code ] );
				}
			}

			return '';
		}
	}
}
```

`absint( '7x' )` in the copied stub must behave like core (`abs( (int) '7x' )` = 7). Check it.

**Step 4: Run, pass. Step 5: Commit** `git commit -m "Add company profile with per-language resolution"`.

---

### Task 6: Card value object and repository

The single place where inheritance happens (§5 Resolution order).

**Files:**
- Create: `includes/card/class-card.php`
- Create: `includes/card/class-card-repository.php`
- Test: `tests/test-card.php`, `tests/test-card-repository.php`

**Step 1: Failing tests**

`tests/test-card.php`:

```php
<?php
/**
 * Pins the Card value object.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/card/class-card.php';

use Asymetria\TeamCards\Card\Card;

describe(
	'card',
	function () {

		it(
			'exposes identity and values, and answers empty for unknown keys',
			function () {
				$card = new Card( 7, 'jan-kowalski', 'pl', array( 'first_name' => 'Jan', 'last_name' => 'Kowalski' ), 12, 'https://example.test/card/jan-kowalski/' );

				assert_same( 7, $card->id() );
				assert_same( 'jan-kowalski', $card->slug() );
				assert_same( 'pl', $card->lang() );
				assert_same( 12, $card->photo_id() );
				assert_same( 'https://example.test/card/jan-kowalski/', $card->url() );
				assert_same( 'Jan', $card->get( 'first_name' ) );
				assert_same( '', $card->get( 'nope' ) );
				assert_true( $card->has( 'first_name' ) );
				assert_false( $card->has( 'email' ) );
			}
		);

		it(
			'builds a full name without stray spaces',
			function () {
				assert_same( 'Jan Kowalski', ( new Card( 1, 's', 'pl', array( 'first_name' => 'Jan', 'last_name' => 'Kowalski' ), 0, '' ) )->full_name() );
				assert_same( 'Kowalski', ( new Card( 1, 's', 'pl', array( 'last_name' => 'Kowalski' ), 0, '' ) )->full_name() );
			}
		);

		it(
			'is active unless told otherwise',
			function () {
				assert_true( ( new Card( 1, 's', 'pl', array(), 0, '' ) )->is_active() );
				assert_false( ( new Card( 1, 's', 'pl', array(), 0, '', false ) )->is_active() );
			}
		);
	}
);
```

`tests/test-card-repository.php`:

```php
<?php
/**
 * Pins how raw meta and the company profile become a Card.
 *
 * @package Vizito_Team_Business_Cards
 */

$root = dirname( __DIR__ );
require_once $root . '/includes/class-settings.php';
require_once $root . '/includes/card/class-fields.php';
require_once $root . '/includes/card/class-field-sanitizer.php';
require_once $root . '/includes/card/class-card.php';
require_once $root . '/includes/company/class-company-profile.php';
require_once $root . '/includes/card/class-card-repository.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;

/**
 * A repository over fresh settings and company profile.
 */
function tbc_test_repository(): Card_Repository {
	$settings = new Settings();
	return new Card_Repository( $settings, new Company_Profile( $settings ) );
}

describe(
	'card repository: find',
	function () {

		before_each(
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_settings'] = array(
					'languages'        => array(
						array( 'code' => 'pl', 'locale' => 'pl_PL' ),
						array( 'code' => 'de', 'locale' => 'de_DE' ),
					),
					'default_language' => 'pl',
				);
				$GLOBALS['tbc_stub']['options']['tbc_company'] = array(
					'intro'   => array( 'pl' => 'Intro firmy', 'de' => 'Firmen-Intro' ),
					'city'    => 'Warszawa',
					'website' => 'https://firma.example',
				);
				$GLOBALS['tbc_stub']['posts_by_id'][5] = (object) array(
					'ID'          => 5,
					'post_type'   => 'tbc_card',
					'post_name'   => 'jan-kowalski',
					'post_status' => 'publish',
				);
				$GLOBALS['tbc_stub']['post_meta'][5] = array(
					'_tbc_first_name' => 'Jan',
					'_tbc_last_name'  => 'Kowalski',
					'_tbc_job_title'  => array( 'pl' => 'Prezes', 'de' => 'Geschäftsführer' ),
					'_tbc_city'       => 'Kraków',
				);
				$GLOBALS['tbc_stub']['thumbnails'][5] = 99;
				$GLOBALS['tbc_stub']['permalinks'][5] = 'https://example.test/card/jan-kowalski/';
			}
		);

		it(
			'answers null for a missing post or another post type',
			function () {
				$GLOBALS['tbc_stub']['posts_by_id'][6] = (object) array( 'ID' => 6, 'post_type' => 'post', 'post_name' => 'x' );

				assert_same( null, tbc_test_repository()->find( 404 ) );
				assert_same( null, tbc_test_repository()->find( 6 ) );
			}
		);

		it(
			'builds the card in the asked language',
			function () {
				$card = tbc_test_repository()->find( 5, 'de' );

				assert_same( 'de', $card->lang() );
				assert_same( 'Geschäftsführer', $card->get( 'job_title' ) );
				assert_same( 'jan-kowalski', $card->slug() );
				assert_same( 99, $card->photo_id() );
				assert_same( 'https://example.test/card/jan-kowalski/', $card->url() );
			}
		);

		it(
			'falls back to the default language for an unknown or missing language',
			function () {
				assert_same( 'pl', tbc_test_repository()->find( 5, 'fr' )->lang() );
				assert_same( 'pl', tbc_test_repository()->find( 5 )->lang() );
			}
		);

		it(
			'prefers the card value over the company value',
			function () {
				assert_same( 'Kraków', tbc_test_repository()->find( 5 )->get( 'city' ) );
			}
		);

		it(
			'inherits empty inheritable fields from the company, per language',
			function () {
				assert_same( 'Firmen-Intro', tbc_test_repository()->find( 5, 'de' )->get( 'intro' ) );
				assert_same( 'https://firma.example', tbc_test_repository()->find( 5 )->get( 'website' ) );
			}
		);

		it(
			'uses the card default-language value before the company value',
			function () {
				$GLOBALS['tbc_stub']['post_meta'][5]['_tbc_intro'] = array( 'pl' => 'Moje intro' );

				assert_same( 'Moje intro', tbc_test_repository()->find( 5, 'de' )->get( 'intro' ) );
			}
		);

		it(
			'never inherits personal fields',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_company']['email'] = 'biuro@firma.example';

				assert_same( '', tbc_test_repository()->find( 5 )->get( 'email' ) );
			}
		);

		it(
			'treats a whitespace-only value as empty',
			function () {
				$GLOBALS['tbc_stub']['post_meta'][5]['_tbc_city'] = '   ';

				assert_same( 'Warszawa', tbc_test_repository()->find( 5 )->get( 'city' ) );
			}
		);

		it(
			'reads the inactive flag',
			function () {
				assert_true( tbc_test_repository()->find( 5 )->is_active() );

				$GLOBALS['tbc_stub']['post_meta'][5]['_tbc_inactive'] = '1';
				assert_false( tbc_test_repository()->find( 5 )->is_active() );
			}
		);
	}
);

describe(
	'card repository: save',
	function () {

		it(
			'writes known fields and deletes emptied ones',
			function () {
				$GLOBALS['tbc_stub']['post_meta'][5] = array( '_tbc_city' => 'Kraków' );

				tbc_test_repository()->save(
					5,
					array(
						'first_name' => 'Jan',
						'job_title'  => array( 'pl' => 'Prezes' ),
						'city'       => '',
						'hack'       => 'x',
					)
				);

				$meta = $GLOBALS['tbc_stub']['post_meta'][5];
				assert_same( 'Jan', $meta['_tbc_first_name'] );
				assert_same( array( 'pl' => 'Prezes' ), $meta['_tbc_job_title'] );
				assert_false( isset( $meta['_tbc_city'] ) );
				assert_false( isset( $meta['_tbc_hack'] ) );
			}
		);

		it(
			'leaves fields that were not passed untouched',
			function () {
				$GLOBALS['tbc_stub']['post_meta'][5] = array( '_tbc_city' => 'Kraków' );

				tbc_test_repository()->save( 5, array( 'first_name' => 'Jan' ) );

				assert_same( 'Kraków', $GLOBALS['tbc_stub']['post_meta'][5]['_tbc_city'] );
			}
		);
	}
);
```

**Step 2: Run, fail. Step 3: Implement**

`includes/card/class-card.php`:

```php
<?php
/**
 * Card value object.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Card' ) ) {

	/**
	 * One card in one language, every value already resolved. Immutable.
	 */
	final class Card {

		/**
		 * @param int                   $id       Post id.
		 * @param string                $slug     Post slug.
		 * @param string                $lang     Language the values are in.
		 * @param array<string, string> $values   Resolved values by field key.
		 * @param int                   $photo_id Featured image id, 0 when none.
		 * @param string                $url      Permalink.
		 * @param bool                  $active   False once the person has left.
		 */
		public function __construct(
			private int $id,
			private string $slug,
			private string $lang,
			private array $values,
			private int $photo_id,
			private string $url,
			private bool $active = true
		) {
		}

		public function id(): int {
			return $this->id;
		}

		public function slug(): string {
			return $this->slug;
		}

		public function lang(): string {
			return $this->lang;
		}

		public function photo_id(): int {
			return $this->photo_id;
		}

		public function url(): string {
			return $this->url;
		}

		public function is_active(): bool {
			return $this->active;
		}

		/**
		 * A resolved value, "" when empty or unknown.
		 */
		public function get( string $key ): string {
			return $this->values[ $key ] ?? '';
		}

		public function has( string $key ): bool {
			return '' !== $this->get( $key );
		}

		/**
		 * First and last name joined by one space.
		 */
		public function full_name(): string {
			return trim( $this->get( 'first_name' ) . ' ' . $this->get( 'last_name' ) );
		}
	}
}
```

Add a one-line docblock to each accessor when implementing (Plugin Check's PHPCS rules want them).

`includes/card/class-card-repository.php`:

```php
<?php
/**
 * Card storage.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Card_Repository' ) ) {

	/**
	 * The only reader and writer of card meta. Resolution order (design §5):
	 * card value in the asked language → card value in the default language →
	 * company profile (inheritable fields only) → "".
	 */
	final class Card_Repository {

		public const POST_TYPE     = 'tbc_card';
		public const META_PREFIX   = '_tbc_';
		public const INACTIVE_META = '_tbc_inactive';

		public function __construct( private Settings $settings, private Company_Profile $company ) {
		}

		/**
		 * A card by post id.
		 *
		 * @param int         $id   Post id.
		 * @param string|null $lang Language code; the default when null or unknown.
		 * @return Card|null
		 */
		public function find( int $id, ?string $lang = null ): ?Card {
			$post = get_post( $id );

			if ( ! $post || self::POST_TYPE !== ( $post->post_type ?? '' ) ) {
				return null;
			}

			$lang   = $this->language( $lang );
			$raw    = $this->raw( $id );
			$values = array();

			foreach ( Fields::all() as $key => $def ) {
				$values[ $key ] = $this->resolve( $key, $def, $raw[ $key ] ?? '', $lang );
			}

			return new Card(
				(int) $post->ID,
				(string) $post->post_name,
				$lang,
				$values,
				(int) get_post_thumbnail_id( $id ),
				(string) get_permalink( $id ),
				'' === (string) get_post_meta( $id, self::INACTIVE_META, true )
			);
		}

		/**
		 * Raw stored values, keyed by field key, "" for missing ones.
		 *
		 * @param int $id Post id.
		 * @return array<string, mixed>
		 */
		public function raw( int $id ): array {
			$raw = array();

			foreach ( array_keys( Fields::all() ) as $key ) {
				$raw[ $key ] = get_post_meta( $id, self::META_PREFIX . $key, true );
			}

			return $raw;
		}

		/**
		 * Stores already-sanitised values. Only keys passed are touched; an
		 * empty string or empty array deletes the meta.
		 *
		 * @param int   $id     Post id.
		 * @param array $values Values keyed by field key.
		 * @return void
		 */
		public function save( int $id, array $values ): void {
			foreach ( array_keys( Fields::all() ) as $key ) {
				if ( ! array_key_exists( $key, $values ) ) {
					continue;
				}

				$value = $values[ $key ];

				if ( '' === $value || array() === $value ) {
					delete_post_meta( $id, self::META_PREFIX . $key );
				} else {
					update_post_meta( $id, self::META_PREFIX . $key, $value );
				}
			}
		}

		/**
		 * A configured language code; the default for anything else.
		 */
		private function language( ?string $lang ): string {
			return null !== $lang && in_array( $lang, $this->settings->languages(), true )
				? $lang
				: $this->settings->default_language();
		}

		/**
		 * One resolved value.
		 */
		private function resolve( string $key, array $def, $own, string $lang ): string {
			if ( $def['translated'] ) {
				$value = is_array( $own ) ? Company_Profile::pick( $own, $lang, $this->settings->default_language() ) : '';
			} else {
				$value = is_scalar( $own ) ? trim( (string) $own ) : '';
			}

			if ( '' === $value && $def['inherits'] ) {
				$value = $this->company->value( $key, $lang );
			}

			return $value;
		}
	}
}
```

Note: `find()` issues one `get_post_meta` per field. WordPress primes the whole meta cache for a
post on the first call, so this is one query, not thirty.

**Step 4: Run, pass. Step 5: Commit** `git commit -m "Add Card value object and repository with inheritance"`.

---

### Task 7: Card post type, rewrite and indexing rules

**Files:**
- Create: `includes/card/class-card-type.php`
- Modify: `includes/class-plugin.php` (register hooks), `team-business-cards.php` (activation hook)
- Test: `tests/test-card-type.php`

Decisions:
- `public => false`, `publicly_queryable => true`, `show_ui => true`, `show_in_menu => true`
  (Task 8 moves it under our menu), `has_archive => false`, `exclude_from_search => true`,
  `show_in_nav_menus => false`, `show_in_rest => false`, `supports => array( 'title', 'thumbnail' )`,
  `rewrite => array( 'slug' => <url_base>, 'with_front' => false )`, `menu_icon => 'dashicons-id'`.
  Not public, so core sitemaps skip it on their own; Yoast and Rank Math treat publicly queryable
  types as viewable, so both get an explicit exclusion filter.
- The vCard address `/{base}/{slug}/vcard/` is registered **now** (rule + `tbc_vcard` query var)
  so the renderer can link to it; Task 18 serves it.
- Every card response sends `X-Robots-Tag: noindex, noarchive` (the full-page template does not
  call `wp_head()`, so `wp_robots` never runs there; the template also prints the meta tag).
- Flushing: on activation, and on `init` (priority 99) when `tbc_flush_rewrite` is set.

**Step 1: Failing test** `tests/test-card-type.php`:

```php
<?php
/**
 * Pins the post type registration, the vCard rewrite and the indexing rules.
 *
 * @package Vizito_Team_Business_Cards
 */

$root = dirname( __DIR__ );
require_once $root . '/includes/class-settings.php';
require_once $root . '/includes/card/class-card-repository.php';
require_once $root . '/includes/card/class-card-type.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Type;

describe(
	'card type',
	function () {

		it(
			'registers a non-public but queryable post type under the configured base',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_settings'] = array( 'url_base' => 'wizytowka' );
				( new Card_Type( new Settings() ) )->register();

				$registered = $GLOBALS['tbc_stub']['post_types'][0];
				$args       = $registered['args'];

				assert_same( 'tbc_card', $registered['name'] );
				assert_false( $args['public'] );
				assert_true( $args['publicly_queryable'] );
				assert_true( $args['show_ui'] );
				assert_false( $args['has_archive'] );
				assert_true( $args['exclude_from_search'] );
				assert_false( $args['show_in_rest'] );
				assert_same( array( 'slug' => 'wizytowka', 'with_front' => false ), $args['rewrite'] );
				assert_same( array( 'title', 'thumbnail' ), $args['supports'] );
			}
		);

		it(
			'registers the vcard endpoint under the same base',
			function () {
				( new Card_Type( new Settings() ) )->register();

				$rule = $GLOBALS['tbc_stub']['rewrite_rules'][0];
				assert_same( '^card/([^/]+)/vcard/?$', $rule['regex'] );
				assert_same( 'index.php?tbc_card=$matches[1]&tbc_vcard=1', $rule['query'] );
				assert_same( 'top', $rule['after'] );
				assert_same( '%tbc_vcard%', $GLOBALS['tbc_stub']['rewrite_tags'][0]['tag'] );
			}
		);

		it(
			'builds the vcard url with an optional language',
			function () {
				$GLOBALS['tbc_stub']['permalinks'][5] = 'https://example.test/card/jan/';
				$type = new Card_Type( new Settings() );

				assert_same( 'https://example.test/card/jan/vcard/', $type->vcard_url( 5 ) );
				assert_same( 'https://example.test/card/jan/vcard/?lang=de', $type->vcard_url( 5, 'de' ) );
			}
		);

		it(
			'flushes rewrite rules once when asked to, and clears the request',
			function () {
				$GLOBALS['tbc_stub']['options']['tbc_flush_rewrite'] = 1;
				$type = new Card_Type( new Settings() );

				$type->maybe_flush();
				$type->maybe_flush();

				assert_same( 1, $GLOBALS['tbc_stub']['rewrite_flushes'] );
				assert_same( array( 'tbc_flush_rewrite' ), $GLOBALS['tbc_stub']['options_deleted'] );
			}
		);

		it(
			'excludes cards from Yoast and Rank Math sitemaps and leaves other types alone',
			function () {
				$type = new Card_Type( new Settings() );

				assert_true( $type->exclude_from_sitemap( false, 'tbc_card' ) );
				assert_false( $type->exclude_from_sitemap( false, 'post' ) );
			}
		);

		it(
			'removes cards from core sitemaps',
			function () {
				$kept = ( new Card_Type( new Settings() ) )->core_sitemap_types( array( 'post' => 1, 'tbc_card' => 2 ) );

				assert_same( array( 'post' => 1 ), $kept );
			}
		);
	}
);
```

Check first: the copied `delete_option` stub records into `options_deleted` (it does in the Store
Locator's uninstall block). If not, adjust the assertion to the stub's real recorder.

**Step 2: Run, fail. Step 3: Implement** `includes/card/class-card-type.php`:

```php
<?php
/**
 * The card post type.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Card_Type' ) ) {

	/**
	 * Registers `tbc_card`, its vCard endpoint, and keeps cards out of indexes.
	 */
	final class Card_Type {

		public const QUERY_VAR = 'tbc_vcard';

		public function __construct( private Settings $settings ) {
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'init', array( $this, 'register' ) );
			add_action( 'init', array( $this, 'maybe_flush' ), 99 );
			add_action( 'template_redirect', array( $this, 'send_noindex' ), 1 );
			add_filter( 'wp_sitemaps_post_types', array( $this, 'core_sitemap_types' ) );
			add_filter( 'wpseo_sitemap_exclude_post_type', array( $this, 'exclude_from_sitemap' ), 10, 2 );
			add_filter( 'rank_math/sitemap/exclude_post_type', array( $this, 'exclude_from_sitemap' ), 10, 2 );
		}

		/**
		 * Registers the post type and the vCard rewrite.
		 *
		 * @return void
		 */
		public function register(): void {
			$base = (string) $this->settings->get( 'url_base' );

			register_post_type(
				Card_Repository::POST_TYPE,
				array(
					'labels'              => array(
						'name'          => __( 'Business cards', 'team-business-cards' ),
						'singular_name' => __( 'Business card', 'team-business-cards' ),
						'add_new_item'  => __( 'Add business card', 'team-business-cards' ),
						'edit_item'     => __( 'Edit business card', 'team-business-cards' ),
						'all_items'     => __( 'All cards', 'team-business-cards' ),
						'menu_name'     => __( 'Team Cards', 'team-business-cards' ),
					),
					'public'              => false,
					'publicly_queryable'  => true,
					'show_ui'             => true,
					'show_in_menu'        => true,
					'show_in_nav_menus'   => false,
					'show_in_rest'        => false,
					'exclude_from_search' => true,
					'has_archive'         => false,
					'query_var'           => Card_Repository::POST_TYPE,
					'rewrite'             => array(
						'slug'       => $base,
						'with_front' => false,
					),
					'supports'            => array( 'title', 'thumbnail' ),
					'menu_icon'           => 'dashicons-id',
				)
			);

			add_rewrite_tag( '%' . self::QUERY_VAR . '%', '1' );
			add_rewrite_rule(
				'^' . preg_quote( $base, '/' ) . '/([^/]+)/vcard/?$',
				'index.php?' . Card_Repository::POST_TYPE . '=$matches[1]&' . self::QUERY_VAR . '=1',
				'top'
			);
		}

		/**
		 * The vCard address of a card.
		 *
		 * @param int    $id   Post id.
		 * @param string $lang Language code, "" for none.
		 * @return string
		 */
		public function vcard_url( int $id, string $lang = '' ): string {
			$url = trailingslashit( (string) get_permalink( $id ) ) . 'vcard/';

			return '' === $lang ? $url : add_query_arg( 'lang', $lang, $url );
		}

		/**
		 * Flushes rewrite rules once after the URL base changed.
		 *
		 * @return void
		 */
		public function maybe_flush(): void {
			if ( ! get_option( Settings::FLUSH_OPTION ) ) {
				return;
			}

			delete_option( Settings::FLUSH_OPTION );
			flush_rewrite_rules( false );
		}

		/**
		 * Sends X-Robots-Tag on every card response.
		 *
		 * @return void
		 */
		public function send_noindex(): void {
			if ( is_singular( Card_Repository::POST_TYPE ) && ! headers_sent() ) {
				header( 'X-Robots-Tag: noindex, noarchive', true );
			}
		}

		/**
		 * Core sitemaps filter.
		 *
		 * @param array $types Post types keyed by name.
		 * @return array
		 */
		public function core_sitemap_types( array $types ): array {
			unset( $types[ Card_Repository::POST_TYPE ] );
			return $types;
		}

		/**
		 * Yoast and Rank Math exclusion filter.
		 *
		 * @param bool   $excluded  Current decision.
		 * @param string $post_type Post type.
		 * @return bool
		 */
		public function exclude_from_sitemap( $excluded, $post_type ) {
			return Card_Repository::POST_TYPE === $post_type ? true : $excluded;
		}
	}
}
```

`trailingslashit` — add to `tests/stubs-extra.php` if the copied bootstrap lacks it:

```php
if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( (string) $value, '/\\' ) . '/';
	}
}
```

The Store Locator's `add_query_arg` stub takes exactly three arguments (key, value, url), which is
how it is called here.

Wire it in `Plugin::boot()` (replace the body after the `booted` guard):

```php
			$this->booted = true;

			$this->settings = new Settings();
			$this->company  = new Company\Company_Profile( $this->settings );
			$this->cards    = new Card\Card_Repository( $this->settings, $this->company );
			$this->type     = new Card\Card_Type( $this->settings );

			$this->type->hooks();
```

with properties `private Settings $settings; private Company\Company_Profile $company;
private Card\Card_Repository $cards; private Card\Card_Type $type;` and public getters
`settings()`, `company()`, `cards()`, `type()` (later tasks and embeds need them).

Activation, in `team-business-cards.php` after `tbc_bootstrap();`:

```php
if ( ! function_exists( 'tbc_activate' ) ) {

	/**
	 * Registers the post type and flushes rewrite rules on activation.
	 *
	 * @return void
	 */
	function tbc_activate(): void {
		\Asymetria\TeamCards\Autoloader::register();
		\Asymetria\TeamCards\Plugin::instance()->type()->register();
		flush_rewrite_rules( false );
	}
}

register_activation_hook( __FILE__, 'tbc_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
```

Add a `tests/test-plugin.php` case: "boot() is idempotent and registers the card type's init hook
once" — boot twice, assert `count( $GLOBALS['tbc_stub']['actions']['init'] ) === 2`
(register + maybe_flush). Note `Plugin` is a singleton: give it a test-only `reset_for_tests()`
static guarded by `defined( 'TBC_TESTING' )`, and define `TBC_TESTING` in `tests/bootstrap.php`.

**Step 4: Run, pass. Step 5: Commit** `git commit -m "Register card post type, vCard rewrite and noindex rules"`.

---

### Task 8: Admin menu, settings page and company profile page

**Files:**
- Create: `includes/admin/class-settings-page.php`
- Modify: `includes/card/class-card-type.php` (`show_in_menu` → `false`; our menu owns it)
- Modify: `includes/class-plugin.php`
- Test: `tests/test-settings-page.php`

Decisions:
- Top-level menu **Team Cards** (`dashicons-id`, capability `edit_posts`) whose first item is the
  post type list (`edit.php?post_type=tbc_card`), then "Add card", then **Company profile** and
  **Settings** (both `manage_options`). Later stages append Import/Export and Statistics.
- Two `register_setting()` groups: `tbc_company` (Company_Profile::sanitize) and `tbc_settings`
  (Settings::sanitize). Both `autoload` yes (read on every card view).
- Settings page sections: *Languages* (textarea `code locale` per line + default language select +
  auto-switch checkbox + URL base), *Appearance* (accent colour input `type=color`, scheme select,
  photo shape select, vCard photo checkbox), *Data* (retention days, delete data on uninstall).
- Company page: every `Company_Profile::fields()` entry; translated fields render one input per
  language, labelled with the code; `logo_id` is a number input plus a "Select logo" button using
  `wp.media` (enqueue `wp_enqueue_media()` on that screen only; `assets/admin.js` handles it,
  and without JS the number input still works).
- Every field name is `tbc_company[key]` or `tbc_company[key][code]`; every value escaped.
- Changing the URL base shows an admin notice on the next page load: "Printed QR codes point to
  the old address. Links to /old-base/… will stop working." (read the old value in
  `Settings::sanitize` and store it in a transient `tbc_base_changed` for the notice).

Test cases (write them first, full code in the same style as Task 7):
1. `hooks()` adds `admin_menu` and `admin_init`.
2. `register_menu()` adds the top-level page with `edit_posts` and submenus with the expected slugs
   and capabilities (`tbcards-company` → `manage_options`, `tbcards-settings` → `manage_options`).
   Add an `add_menu_page` stub to `stubs-extra.php` recording into a new `menu_pages` key.
3. `register_settings()` registers both option names with the right sanitize callbacks
   (assert the callback arrays point at the two objects' `sanitize` methods).
4. `render_company()` escapes a stored value containing `"><script>` (assert the output contains
   `&quot;&gt;&lt;script&gt;` and not `<script>`).
5. `render_company()` renders one intro input per configured language with name
   `tbc_company[intro][de]`.
6. `render_settings()` prints the language rows as `pl pl_PL\nde de_DE` in the textarea.
7. Both renderers refuse to print for users without `manage_options` (call `wp_die`, which the stub
   records and throws).
8. The base-changed notice renders once and deletes its transient.

**Commit:** `git commit -m "Add admin menu, settings and company profile pages"`.

---

### Task 9: Card edit screen (meta box and save)

**Files:**
- Create: `includes/admin/class-card-metabox.php`
- Create: `assets/admin.js`, `assets/admin.css`
- Modify: `includes/class-plugin.php`
- Test: `tests/test-card-metabox.php`, `tests/js/admin-tabs.test.js`

Decisions:
- One meta box "Card details" (`normal`, `high`) with fieldsets per `Fields` group. The WordPress
  title field is hidden with CSS on this screen; the title is **derived**: a `wp_insert_post_data`
  filter sets `post_title` to "First Last" when our nonce is present and valid. WordPress then
  builds the slug from it on first publish, as usual.
- Translated fields: a row of language tab buttons above the fieldset (`role="tablist"`), one panel
  per language (`role="tabpanel"`). Without JS every panel is visible, each labelled with its code.
  `admin.js` toggles `hidden` and `aria-selected`, arrow keys move between tabs.
- Inherited fields: when the company profile has a value, the input gets
  `placeholder="{value}"` and a description "Leave empty to use the company profile." For
  translated inherited fields (intro) the placeholder is per language.
- Save (`save_post_tbc_card`): return early on autosave, on revisions, without a valid nonce
  (`tbc_card_nonce`, action `tbc_save_card_{post_id}`), or without `current_user_can( 'edit_post', $id )`.
  Read `wp_unslash( $_POST['tbcards'] )`, sanitise every field with `Field_Sanitizer` (translated ones
  with the configured codes), pass to `Card_Repository::save()`, then `do_action( 'tbc_card_saved', $id )`.
- Missing keys in `$_POST['tbcards']` mean "not on the form" → not touched (repository contract).
  Every field is always on the form, so in practice an emptied input arrives as `""` and deletes.

Test cases:
1. Renders a text input `tbc[first_name]` with the stored, escaped value.
2. Renders `tbc[job_title][pl]` and `tbc[job_title][de]` for two languages.
3. Shows the company value as placeholder on an empty inheritable field; no placeholder on
   non-inheritable fields.
4. Prints the nonce field with the per-post action.
5. Save with a valid nonce and capability writes sanitised values (a `javascript:` website becomes
   empty and is deleted).
6. Save without a nonce, with a wrong nonce, without capability, on autosave (`DOING_AUTOSAVE`
   cannot be undefined once defined — use an injected `callable $is_autosave` defaulting to
   `wp_is_post_autosave`-style check, see Store Locator's approach) and on a revision does nothing.
7. Save fires `tbc_card_saved` exactly once with the id.
8. `derive_title()` sets "Jan Kowalski" from posted names; leaves data untouched for other post
   types or without our nonce; falls back to the existing title when both names are empty.
9. JS: clicking a tab shows its panel and hides the others; ArrowRight moves focus and selection;
   without `admin.js` all panels have no `hidden` attribute (assert on the PHP output).

**Commit:** `git commit -m "Add card edit screen with language tabs and inherited placeholders"`.

---

### Task 10: Language resolution

**Files:**
- Create: `includes/front/class-language.php`
- Test: `tests/test-language.php`

Decisions:
- `current( string $context = 'page' ): string`:
  1. `?lang=` when it is a configured code (read with `sanitize_key( wp_unslash( $_GET['lang'] ) )`);
  2. context `embed` only: Polylang (`pll_current_language( 'slug' )`) or WPML
     (`apply_filters( 'wpml_current_language', null )`) when that code is configured;
  3. the default language.
  The full page deliberately ignores Polylang/WPML: a card address has no language prefix and
  the page is not a translated post.
- `with_locale( string $code, callable $fn )`: switches to the mapped locale when it is set and
  differs from `determine_locale()`, runs `$fn`, restores in `finally`. Returns `$fn()`'s value.
- `switch_url( string $url, string $code ): string` — always adds `?lang=code`, **including for the
  default language**, so a click on the default language is explicit and the browser-language
  auto-switch (Task 12) never overrides it.

Test cases: `?lang=de` configured → `de`; `?lang=fr` unconfigured → default; `?lang=<script>` →
default; embed + Polylang `de` → `de` (define `pll_current_language` inside the test file behind a
`function_exists` guard driven by a stub key `pll_language`); page context ignores Polylang;
`with_locale` switches and restores exactly once and returns the callback's value; restores even
when the callback throws; does not switch when the locale is empty or already current;
`switch_url` for default and non-default codes. Remember to `unset( $_GET['lang'] )` in a
`before_each` — `$_GET` is not part of stub state.

**Commit:** `git commit -m "Add language resolution and locale switching"`.

---

### Task 11: Icons and card renderer

**Files:**
- Create: `includes/front/class-icons.php`
- Create: `includes/front/class-card-renderer.php`
- Test: `tests/test-icons.php`, `tests/test-card-renderer.php`

Decisions:
- `Icons::svg( string $name ): string` returns one inline `<svg aria-hidden="true" focusable="false"
  viewBox="0 0 24 24" class="tbcards-icon">` with a single `<path>`. Names: `phone`, `mobile`, `mail`,
  `whatsapp`, `pin`, `globe`, `share`, `download`, `link`, plus the eight networks. Draw simple
  original paths; do **not** copy icon sets (licence). Unknown name → `link`.
- `Card_Renderer::render( Card $card, array $args = array() ): string` with
  `$args = array( 'context' => 'page'|'embed', 'languages' => string[], 'vcard_url' => string )`.
  Output, top to bottom (§6): root `<article class="tbcards-card" data-tbcards-card="{id}" lang="{lang}">`;
  logo (company, from `Company_Profile::logo_id()` → `wp_get_attachment_image_url( id, 'medium' )`);
  photo (`wp_get_attachment_image_url( photo_id, 'medium' )`, `alt` = full name) or initials
  in a circle when there is none; `<h1>` (page) / `<h2>` (embed) name; job title; intro (`nl2br`
  of escaped text); primary actions; details list (`<dl>`); address block with "Directions";
  social icon links; custom buttons (only when both label and URL are set); footer with Share and,
  on the page only, the language switcher.
- Primary actions and their `data-tbcards-event`: Save contact (`vcard`, `href` = vcard URL, `download`
  attribute), Call (`call`, `tel:` + `Field_Sanitizer::dial( mobile )`), Email (`email`, `mailto:`),
  WhatsApp (`whatsapp`, `https://wa.me/` + digits of `dial()` without `+`). An action whose value is
  empty is omitted, never rendered disabled.
- Directions (`directions`): `map_url` when set, otherwise
  `https://www.google.com/maps/search/?api=1&query=` + `rawurlencode( street, postcode city, country )`.
  It is only a link the visitor chooses to open; the plugin itself contacts no service.
- External links get `rel="noopener"` and `target="_blank"`; social links also
  `aria-label="{Network}: {full name}"`.
- Share: `<button type="button" class="tbcards-share" data-tbcards-event="share" data-tbcards-url="{url}">`
  plus an empty `<p class="tbcards-status" role="status" aria-live="polite">` for "Link copied".
- Everything is escaped at the point of output (`esc_html`, `esc_attr`, `esc_url`).
- Labels come from `__()`; callers wrap `render()` in `Language::with_locale()`.

Test cases (build a `Card` directly — no repository needed):
1. Name in `<h1>` on page, `<h2>` in embed.
2. Values are escaped: a job title `<script>x</script>` appears as `&lt;script&gt;`.
3. Call link uses the dial form: mobile `+48 609 909 828` → `href="tel:+48609909828"`.
4. WhatsApp link: `https://wa.me/48609909828`.
5. Omits Call, Email, WhatsApp when empty; omits Save contact when `vcard_url` is "".
6. Directions uses `map_url` when present, otherwise the encoded search URL.
7. Renders only social networks that have a URL, in `Fields::social()` order.
8. Custom button needs both label and URL.
9. Initials fallback "JK" when there is no photo; `<img alt="Jan Kowalski">` when there is.
10. Language switcher: page context with two languages renders two links with `?lang=`, the
    current one with `aria-current="true"`; one language or embed context renders none.
11. Every `data-tbcards-event` value is one of the §9 event names.
12. Icons: every declared name returns an `<svg` with `aria-hidden="true"`; unknown → the link icon.

**Commit:** `git commit -m "Add card renderer and inline icons"`.

---

### Task 12: Full-page template, assets and the card script

**Files:**
- Create: `includes/front/class-template-loader.php`
- Create: `templates/card.php`
- Create: `assets/card.css`, `assets/card.js`
- Modify: `includes/class-plugin.php`
- Test: `tests/test-template-loader.php`, `tests/js/card.test.js`

Decisions:
- `template_include` (priority 99): on `is_singular( 'tbc_card' )` and **not** the vCard query var,
  return `locate_template( 'team-business-cards/card.php' )` or `TBC_DIR . 'templates/card.php'`.
  Before returning, build the view model into a property the template reads through
  `Template_Loader::view()`: `card`, `html` (renderer output inside `with_locale`), `lang`,
  `title` ("Full name — Company"), `css` (contents of `assets/card.css`, read once),
  `vars` (the `--tbcards-*` declarations from settings), `scheme`, `script_url`
  (`TBC_URL . 'assets/card.js?ver=' . TBC_VERSION`), `config` (JSON for the script: languages,
  default, auto switch on/off; stats endpoint arrives in Task 20).
- `templates/card.php` prints a complete document: `<!doctype html>`, `<html lang>`, charset,
  `viewport`, `<meta name="robots" content="noindex, noarchive">`, `theme-color` = accent,
  `<title>`, `<style>` with vars + css, the card HTML, `<script>` config
  (`wp_json_encode` with `JSON_HEX_TAG | JSON_HEX_AMP`), `<script src defer>`. No `wp_head()`,
  no `wp_footer()`: nothing from the theme or other plugins reaches the card. Document this
  in the template header, including that a theme override may call them if it wants to.
- Scheme: `auto` → `color-scheme: light dark` with a `prefers-color-scheme: dark` block;
  `light`/`dark` force one. Photo shape → `--tbcards-photo-radius: 50%` or `12px`.
- `card.css` target: under 6 KB unminified, mobile first, max-width 480px card centred,
  `:focus-visible` outlines, reduced-motion respected, no web fonts (system stack).
- `card.js` (no dependencies, ES2017, wrapped IIFE; pure functions also exported through
  `module.exports` when it exists so `node --test` can require them):
  - `pickLanguage( browserLangs, available, fallback )` → first `navigator.languages` entry whose
    primary subtag is available, else fallback.
  - On load: when auto switch is on, the URL has no `lang` parameter, and `pickLanguage(...)`
    differs from the page's language → `location.replace( url + ?lang= )`. Never loops, because the
    replaced URL has a `lang` parameter.
  - Share: `navigator.share( { title, url } )` when available (ignore `AbortError`), otherwise
    `navigator.clipboard.writeText( url )`, then write the translated "Link copied" (from config)
    into `.tbcards-status`.

Test cases, PHP: template chosen only for singular cards; theme override wins; vCard requests are
left alone; the view model's title, lang and escaped HTML; `vars` reflect settings (accent,
radius); config JSON contains languages and the auto flag; the template file itself renders a
document containing the robots meta, `<html lang="de">` and no `wp_head` call (render it with a
fake view model through output buffering).
Test cases, JS: `pickLanguage` with `['de-DE','en']` and `['pl','de']` → `de`; with no match →
fallback; auto switch builds the right URL and does nothing when `lang` is present or auto is off
(inject a fake `location`); share falls back to clipboard and fills the status text; `AbortError`
from `navigator.share` is swallowed.

Manual check to add to the release checklist (Task 32): open a card on a phone in the default
browser language and in another one; share sheet appears on iOS and Android.

**Commit:** `git commit -m "Add theme-independent card page, stylesheet and card script"`.

---

### Task 13: Cache purge

**Files:**
- Create: `includes/integrations/class-cache-purge.php`
- Modify: `includes/class-plugin.php`
- Test: `tests/test-cache-purge.php`

Decisions:
- On `tbc_card_saved` (id): `clean_post_cache( id )`; LiteSpeed `do_action( 'litespeed_purge_post', id )`;
  WP Rocket `rocket_clean_post( id )`; W3TC `w3tc_flush_post( id )`; WP Super Cache
  `wpsc_delete_post_cache( id )` — each only when its function exists (LiteSpeed's is an action,
  always safe to fire).
- On `update_option_tbc_company` and `update_option_tbc_settings` (every card changes):
  LiteSpeed `do_action( 'litespeed_purge_all' )`; `rocket_clean_domain()`; `w3tc_flush_all()`;
  `wp_cache_clear_cache()`.
- `apply_filters( 'tbc_purge_cache', true, $id_or_null )` lets a site opt out.

Test cases: define the four plugins' functions **inside the test file** behind `function_exists`
guards that record into a stub key; assert each is called once per event with the right id; nothing
is called when the filter returns false; no error when none of them exist (run a case before
defining them — or split into two files so one runs with none defined: `test-cache-purge-none.php`
must sort before the other; name them `test-cache-purge-a-none.php` and `test-cache-purge-b-all.php`).

**Commit:** `git commit -m "Purge page caches when a card or the company profile changes"`.

**End of stage 1.** Manual check on a real WordPress (LocalWP or a staging site): create two
languages, a company profile, one card; open `/card/{slug}/` and `?lang=de`; confirm no theme CSS,
no admin bar interference, `X-Robots-Tag` in the response headers, and that editing the card is
visible immediately with LiteSpeed active.

---

# Stage 2 — vCard and QR (briefs; expand before executing)

### Task 14: Vendor the QR library

- Download `php/qrcode.php` from `github.com/kazuhikoarase/qrcode-generator` at a pinned commit;
  record the commit hash and SHA-256 in `lib/qrcode/README.md` (provenance, as the Store Locator did
  for Leaflet).
- Write `tools/vendor-qrcode.php` (dev only, `.distignore`d) that transforms the upstream file:
  add `namespace Asymetria\TeamCards\Vendor\Qr;`, convert every `define("QR_X", v)` into a constant
  on a new `final class QR_Const`, rewrite every `QR_X` reference to `QR_Const::QR_X`, delete the
  GD `createImage()` helper, keep the MIT header verbatim, add a note on what was changed.
  Generating instead of hand-editing makes re-vendoring a newer upstream a one-command job.
- Tests: the transformed file declares no global class or constant (load it, compare
  `get_declared_classes()` before/after for anything outside the namespace); `getMinimumQRCode`
  for `https://example.com/card/jane-doe/?src=qr` at level H has 41 modules
  (the known production value); matrices for three reference strings at levels L/M/Q/H match
  fixtures generated once with the untouched upstream file and stored in `tests/fixtures/qr/*.txt`
  (one row of `0`/`1` per line).

### Task 15: Qr_Service and Svg_Writer

- `Qr_Service::matrix( string $data, string $level ): bool[][]`, `Qr_Service::card_url( Card ): string`
  (`add_query_arg( 'src', 'qr', permalink )`), level `M` by default, `H` when the logo is on.
- `Svg_Writer::write( array $matrix, array $opts ): string` — one `<path>` built from horizontal runs
  (`M{x} {y}h{n}v1h-{n}z`), `viewBox` including a 4-module quiet zone, `shape-rendering="crispEdges"`,
  background `<rect>`, colours from options, optional logo `<image href="data:…base64">` centred on a
  white pad covering ≤ 20 % of the symbol area.
- Tests: quiet zone in the viewBox; module count × path runs reproduce the matrix (parse the path
  back into a grid); colours escaped; logo size bound; no logo → no `<image>`.

### Task 16: Png_Writer and colour rules

- GD: `imagecreatetruecolor`, module size = `floor( 2000 / (n + 8) )`, logo via
  `imagecopyresampled` from the attachment file (JPEG/PNG/WebP only). Missing GD → `is_available()`
  false; the admin offers SVG only and shows a notice.
- `Qr_Colors::validate( string $dark, string $light ): ?string` (error message or null): contrast
  ratio (WCAG relative luminance) must be ≥ 4.5 and the dark colour must be darker than the light one.
  Settings gain `qr_dark` (#000000), `qr_light` (#ffffff), `qr_logo` (false); `Settings::sanitize`
  refuses a failing pair with `add_settings_error` and keeps the previous pair.
- Tests: contrast maths against known pairs (black/white 21, #777/#fff ≈ 4.48 fails); inverted pair
  rejected; PNG dimensions and a sampled module colour (skip the case with a clear message when the
  test PHP has no GD — `-n` loads no extensions, so run this file with GD enabled via
  `-d extension=gd` and document the command).

### Task 17: QR meta box, downloads, print export and test sheet

- Side meta box "QR code" on published cards: inline SVG preview; download links to
  `admin-post.php?action=tbc_qr&card=ID&format=svg|png&logo=0|1&_wpnonce=…` (capability
  `edit_post`, filename `{slug}-qr.{ext}`, `Content-Disposition: attachment`).
- Print export page (Team Cards → Print codes): checkbox list of published active cards, "Download
  ZIP" (`ZipArchive`, temp file via `wp_tempnam`, streamed, deleted) with `{slug}/{slug}.svg`,
  `{slug}.png`, `{slug}-logo.svg`, `{slug}-logo.png`; "Open test sheet" → an admin-post page printing
  an A4 grid (print CSS, 3 × 3 per page, name/role/URL under each code, both variants).
- Tests: capability and nonce on every handler; ZIP entries list (open the produced archive in the
  test); inactive cards excluded; test sheet escapes names.
- Dev tool `tools/decode-check.mjs` (not shipped): decodes every PNG in a folder with a JS QR
  decoder installed in the tool's own folder, prints PASS/FAIL per file; run it on the ZIP from a
  real site before any release.

### Task 18: vCard builder and endpoint

- `VCard_Builder::build( Card $card, ?string $photo_jpeg ): string` — vCard 3.0, CRLF, folding at
  75 octets without splitting UTF-8 sequences (fold on byte count, back off to a lead byte),
  escaping `\ , ; \n`. Properties per design §7. Empty values produce no line.
- `VCard_Endpoint` on `template_redirect`: when `get_query_var( 'tbc_vcard' )` and the card is found:
  inactive → `status_header( 410 )` and a short plain-text body, no data; otherwise
  `nocache_headers()`, `Content-Type: text/vcard; charset=utf-8`, filename `first-last.vcf`
  (ASCII-folded slug), body, `exit` through an injectable terminator for tests; fires
  `tbc_vcard_downloaded` (card id) for stats.
- Photo: when `vcard_photo` is on and there is a featured image, `wp_get_image_editor` resizes to
  256 px JPEG in memory; cache the base64 in post meta `_tbc_vcard_photo` keyed by attachment id +
  modified time, invalidated on save.
- Tests: exact output for a full card (golden string); folding at 75 octets with Polish and German
  characters never splits a character; escaping; `TEL;TYPE=CELL:+48…` uses `dial()`; empty fields
  absent; 410 path discloses nothing; filename for "Józef Wężycki" → `jozef-wezycki.vcf`.

---

# Stage 3 — Statistics and inactive cards (briefs)

### Task 19: Stats table

- `Stats_Table::install()` with `dbDelta` (`card_id BIGINT UNSIGNED, day DATE, event VARCHAR(16),
  source VARCHAR(8), count INT UNSIGNED, PRIMARY KEY (card_id, day, event, source), KEY day (day)`),
  schema version in option `tbc_db_version`, run on activation and on `plugins_loaded` when the
  version is behind. `increment( card_id, event, source )` with one prepared
  `INSERT … ON DUPLICATE KEY UPDATE count = count + 1`, day from `current_time( 'Y-m-d' )`.
  `purge_older_than( days )` via daily cron when retention > 0.
- Tests: the stub `$wpdb` records prepared SQL; assert the statement shape and placeholders; version
  gating; cron scheduled only when retention > 0 and unscheduled when it goes back to 0.

### Task 20: Beacon endpoint and card.js tracking

- REST `POST /tbc/v1/hit`, `permission_callback => '__return_true'` (public by design, documented),
  args `card` (int), `event` (enum), `src` (`qr`|`link`). Rejects: unknown or inactive card, bot UA
  (small list: `bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp` — note WhatsApp's link
  preview fetcher must not count as a view), more than 30 hits per minute per salted IP hash
  (transient, 60 s). Always answers 204 so abusers learn nothing.
- `card.js`: `view` on load (source `qr` when the URL has `src=qr`, then `history.replaceState`
  removes `src` so a shared link does not count as QR), clicks on `[data-tbcards-event]` via one
  delegated listener, `navigator.sendBeacon` with a `Blob` of JSON; no-op when `sendBeacon` is missing.
- Tests: each rejection path; hash never stored beyond the transient; JS: view source detection,
  replaceState, delegated clicks, no beacon when unsupported.

### Task 21: Reports

- Card list columns "Views (30 days)" and "vCard (30 days)" (one grouped query for the listed ids,
  not N queries); card screen side box with a 30-day inline SVG bar chart (QR vs link, two tones,
  accessible `<title>` + table fallback in `<details>`); Statistics page with date range, per-card
  table (views, vCard, calls, emails, WhatsApp, directions, shares, links; QR share %), CSV export
  (`admin-post`, nonce, capability).
- Tests: the grouped query; zero-filled 30-day series; CSV header and escaping (formula injection:
  prefix cells starting with `= + - @` with `'`).

### Task 22: Inactive cards

- Meta box section "Status": active / inactive; behaviour `notice` | `card` (select another card) |
  `url`; stored in `_tbc_inactive` ("1"), `_tbc_inactive_mode`, `_tbc_inactive_target`.
- `Inactive_Card` on `template_redirect` (before the template loader): `card` / `url` → `wp_redirect`
  **302** (target validated: an active published card, or an http(s) URL through
  `wp_validate_redirect` with the external host allowed only when it is the company website's host);
  `notice` → the template renders a notice view with company data only.
- Renderer, vCard, embeds and print export all check `is_active()`.
- Slug-change warning: when a published card's slug differs from the saved one on the edit screen,
  `admin.js` shows an inline warning before submit; the server side leaves `_wp_old_slug` handling to core.
- Tests: all three behaviours; invalid targets fall back to `notice`; no personal field appears in the
  notice view; 302 not 301.

---

# Stage 4 — CSV import and export (briefs)

### Task 23: CSV reader

- `Csv_Reader::parse( string $bytes ): array{ header: string[], rows: string[][], delimiter: string, encoding: string }`.
  Strip UTF-8 BOM; if the bytes are not valid UTF-8, convert from Windows-1250 (`mb_convert_encoding`
  when available, else `iconv`, else an internal 1250→UTF-8 table — the test runner has no mbstring);
  delimiter = the one of `, ; \t` that splits the header into the most fields; `str_getcsv` per record
  with quoted-newline support (parse with a stream on `php://temp`, not by splitting lines).
- Tests: comma, semicolon, tab; BOM; a 1250-encoded "Łódź"; quoted fields with delimiters and newlines;
  empty trailing lines; ragged rows reported, not fatal.

### Task 24: Importer and import page

- Columns: `Fields` keys, translated ones suffixed `_{code}`, plus `photo` (attachment id or filename
  in the Media Library, matched by `_wp_attached_file` basename) and `status` (`active`/`inactive`).
  Unknown columns reported as warnings.
- Matching: `email` first (meta query), then `slug`; neither → create. Empty cell = leave; `-` = clear.
- Two steps: upload/paste → preview table (create / update / error per row, with messages) saved in
  transient `tbc_import_{user_id}` (1 hour) → "Import" commits in batches of 50 per request
  (progress via redirect loop, no JS required). Max 500 rows, max 2 MB.
- Tests: plan building for create/update/error; `-` clears; emails normalised before matching; photo
  lookup; transient scoped per user; capability + nonce on both steps; nothing written in preview.

### Task 25: Exporter

- Same columns as the importer, one row per card (all statuses), translated fields expanded per
  configured language, UTF-8 with BOM (Excel), semicolon when the site locale is `pl_PL`/`de_DE`
  (Excel in those locales expects it), formula-injection guard as in Task 21.
- Round-trip test: export → parse → import plan → every row "update", no changes.

---

# Stage 5 — Embedding (briefs)

### Task 26: Shortcode

- `[team_card id="12"]` or `[team_card slug="jan-kowalski"]`, optional `lang`. Renders through
  `Card_Renderer` with `context => embed` inside `with_locale`; enqueues `card.css` as a real stylesheet
  (handle `tbcards-card`) once, `card.js` deferred. Inactive → notice view or nothing (attribute
  `inactive="notice|hide"`, default `hide`). Unknown card → HTML comment for editors only.

### Task 27: Block

- `block.json` (apiVersion 3, `render` → PHP callback, no build), editor script `assets/block.js` using
  `wp.element.createElement` directly (no JSX), `ServerSideRender` preview, a `SelectControl` fed by a
  private REST route `GET /tbc/v1/cards` (`edit_posts` capability) returning id + name + slug.
- Tests: route permission; route payload; render callback delegates to the shortcode path.

### Task 28: Bricks element

- Same pattern as the Store Locator's element (read that code, it is the author's own):
  registered on `init` priority 11 when `\Bricks\Elements` exists; controls: card (select from the
  same list), language, inactive behaviour; `render()` delegates to the shared path. Reuse the Store
  Locator's Bricks registry stubs for tests.
- Remember the Store Locator lessons (its plan, "Bricks" sections): elements inside Bricks *content
  templates* can disappear silently; the builder re-renders on every control change; verify on the
  published page, not in the builder.

---

# Stage 6 — Release (briefs)

### Task 29: Internationalisation

- Generate `languages/team-business-cards.pot` (`wp i18n make-pot` if WP-CLI is available on the
  machine, otherwise a small `tools/make-pot.php` scanning `__()`/`_x()`/`_n()`/`esc_html__()` calls);
  translate `pl_PL` and `de_DE`; compile `.mo` (msgfmt, or `tools/po2mo.php`).
- `load_plugin_textdomain( 'team-business-cards', false, dirname( plugin_basename( TBC_FILE ) ) . '/languages' )`
  on `init`, so `switch_to_locale()` in `Language::with_locale()` picks up the shipped files.
- Test: every translatable call uses the literal domain; every placeholder string has a translators
  comment (port the Store Locator's i18n test).

### Task 30: Accessibility pass

- Card page: landmarks, heading order, link purpose (icon links have names), focus order, 44 px
  touch targets, contrast of the accent on both schemes (warn in settings when the accent fails 4.5:1
  against the card background), language switcher `lang` + `hreflang` on each link.
- Admin: tabs keyboard model, labels on every input, error summary on import preview.

### Task 31: Packaging

- `uninstall.php` (honours `delete_data`: posts of `tbc_card` with meta, the two options,
  `tbc_db_version`, the stats table, transients `tbc_%`, the cron event; multisite loop as in the Store
  Locator), `readme.txt` (description, FAQ incl. "Does it contact any external service?" → no;
  "What happens to printed QR codes when someone leaves?"; bundled library credit), `LICENSE`,
  `.distignore` (tests, docs, tools, README.md anchored), `README.md` for GitHub.
- Tests: uninstall with the flag off leaves everything; with it on removes exactly the listed items.

### Task 32: Verification and publication

- Plugin Check with no errors; `debug.log` clean with `WP_DEBUG` on through a full manual pass.
- Manual checklist file `docs/manual-checks.md`: iOS + Android scan of plain and logo QR from paper;
  save contact with photo; language switch and auto-switch; share; directions; inactive behaviours;
  LiteSpeed purge; Bricks element on a published page; block in the editor; CSV round trip in Excel
  (PL locale).
- Public GitHub repository: the author pushes it (commands prepared, not run).
- WordPress.org submission waits until `product-sheet-importer-for-woocommerce` has passed review.
