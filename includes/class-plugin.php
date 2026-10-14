<?php
/**
 * Composition root.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards;

use Asymetria\TeamCards\Admin\Card_Metabox;
use Asymetria\TeamCards\Admin\Import_Page;
use Asymetria\TeamCards\Admin\Qr_Admin;
use Asymetria\TeamCards\Admin\Settings_Page;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Card_Type;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Csv\Exporter;
use Asymetria\TeamCards\Csv\Importer;
use Asymetria\TeamCards\Embed\Block;
use Asymetria\TeamCards\Embed\Embed_Renderer;
use Asymetria\TeamCards\Embed\Shortcode;
use Asymetria\TeamCards\Front\Card_Renderer;
use Asymetria\TeamCards\Front\Language;
use Asymetria\TeamCards\Front\Template_Loader;
use Asymetria\TeamCards\Integrations\Cache_Purge;
use Asymetria\TeamCards\Integrations\Seo_Compat;
use Asymetria\TeamCards\Lifecycle\Inactive_Card;
use Asymetria\TeamCards\Qr\Qr_Output;
use Asymetria\TeamCards\Qr\Qr_Service;
use Asymetria\TeamCards\Stats\Stats_Endpoint;
use Asymetria\TeamCards\Stats\Stats_Report;
use Asymetria\TeamCards\Stats\Stats_Table;
use Asymetria\TeamCards\VCard\VCard_Endpoint;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Plugin' ) ) {

	/**
	 * Builds the services once and registers their hooks. No container: the
	 * graph is small enough to read top to bottom.
	 */
	final class Plugin {

		/**
		 * The Bricks element class, as a string: naming it with ::class would
		 * make the autoloader require a file whose `extends \Bricks\Element`
		 * cannot be met on a site without Bricks.
		 */
		public const BRICKS_ELEMENT = 'Asymetria\TeamCards\Embed\Bricks_Element';

		/**
		 * The single instance.
		 *
		 * @var Plugin|null
		 */
		private static ?Plugin $instance = null;

		/**
		 * Whether boot() has run.
		 *
		 * @var bool
		 */
		private bool $booted = false;

		/**
		 * Plugin settings.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Company profile.
		 *
		 * @var Company_Profile
		 */
		private Company_Profile $company;

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

		/**
		 * Card post type.
		 *
		 * @var Card_Type
		 */
		private Card_Type $type;

		/**
		 * Card renderer.
		 *
		 * @var Card_Renderer
		 */
		private Card_Renderer $renderer;

		/**
		 * Language resolution.
		 *
		 * @var Language
		 */
		private Language $language;

		/**
		 * Shared embed path, see embed().
		 *
		 * @var Embed_Renderer|null
		 */
		private ?Embed_Renderer $embed = null;

		/**
		 * Statistics table, see stats().
		 *
		 * @var Stats_Table|null
		 */
		private ?Stats_Table $stats = null;

		/**
		 * QR files, see qr().
		 *
		 * @var Qr_Output|null
		 */
		private ?Qr_Output $qr = null;

		/**
		 * Builds the service graph. Hooks are registered by boot(), not here.
		 */
		private function __construct() {
			$this->settings = new Settings();
			$this->company  = new Company_Profile( $this->settings );
			$this->cards    = new Card_Repository( $this->settings, $this->company );
			$this->type     = new Card_Type( $this->settings );
			$this->renderer = new Card_Renderer( $this->company );
			$this->language = new Language( $this->settings );
		}

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
		 * Drops the instance. Test suite only.
		 *
		 * @return void
		 */
		public static function reset_for_tests(): void {
			if ( defined( 'TBCARDS_TESTING' ) && TBCARDS_TESTING ) {
				self::$instance = null;
			}
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

			add_action( 'init', array( $this, 'load_textdomain' ), 1 );
			$this->type->hooks();
			( new Settings_Page( $this->settings, $this->company ) )->hooks();
			( new Card_Metabox( $this->settings, $this->company, $this->cards ) )->hooks();
			( new Template_Loader( $this->settings, $this->company, $this->cards, $this->type, $this->renderer, $this->language ) )->hooks();
			( new Cache_Purge() )->hooks();
			( new Seo_Compat() )->hooks();
			( new Qr_Admin( $this->cards, $this->qr() ) )->hooks();
			( new VCard_Endpoint( $this->cards, $this->settings, $this->company, $this->language ) )->hooks();
			$this->stats()->hooks( $this->settings );
			( new Stats_Endpoint( $this->cards, $this->stats() ) )->hooks();
			( new Stats_Report( $this->stats(), $this->cards ) )->hooks();
			( new Inactive_Card( $this->cards, $this->company ) )->hooks();
			( new Import_Page( new Importer( $this->settings, $this->cards ), new Exporter( $this->settings, $this->cards ) ) )->hooks();
			( new Shortcode( $this->embed() ) )->hooks();
			( new Block( $this->embed(), $this->cards ) )->hooks();
			add_action( 'init', array( self::class, 'register_bricks_element' ), 11, 0 );
		}

		/**
		 * Loads the shipped translations. Registered with the path, so
		 * switch_to_locale() on a card page finds them too.
		 *
		 * @return void
		 */
		public function load_textdomain(): void {
			// phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- the plugin ships its own pl_PL and de_DE files, which core does not load from a plugin folder by itself.
			load_plugin_textdomain( 'vizito-team-business-cards', false, dirname( plugin_basename( TBCARDS_FILE ) ) . '/languages' );
		}

		/**
		 * Plugin settings.
		 *
		 * @return Settings
		 */
		public function settings(): Settings {
			return $this->settings;
		}

		/**
		 * Company profile.
		 *
		 * @return Company_Profile
		 */
		public function company(): Company_Profile {
			return $this->company;
		}

		/**
		 * Card repository.
		 *
		 * @return Card_Repository
		 */
		public function cards(): Card_Repository {
			return $this->cards;
		}

		/**
		 * Card post type.
		 *
		 * @return Card_Type
		 */
		public function type(): Card_Type {
			return $this->type;
		}

		/**
		 * Card renderer.
		 *
		 * @return Card_Renderer
		 */
		public function renderer(): Card_Renderer {
			return $this->renderer;
		}

		/**
		 * Language resolution.
		 *
		 * @return Language
		 */
		public function language(): Language {
			return $this->language;
		}

		/**
		 * Shared embed path, built on first use.
		 *
		 * @return Embed_Renderer
		 */
		public function embed(): Embed_Renderer {
			if ( null === $this->embed ) {
				$this->embed = new Embed_Renderer( $this->settings, $this->cards, $this->type, $this->renderer, $this->language );
			}

			return $this->embed;
		}

		/**
		 * Hands the Team Card element to Bricks, when Bricks is there.
		 * Bricks' Elements::register_element( $file, $name, $class ) requires the
		 * file itself, after its own base class is loaded.
		 *
		 * @return void
		 */
		public static function register_bricks_element(): void {
			if ( ! class_exists( '\Bricks\Elements' ) || ! class_exists( '\Bricks\Element' ) || ! defined( 'TBCARDS_DIR' ) ) {
				return;
			}

			$relative = Autoloader::path_for( self::BRICKS_ELEMENT );
			$file     = null === $relative ? '' : TBCARDS_DIR . $relative;

			if ( '' !== $file && is_readable( $file ) ) {
				\Bricks\Elements::register_element( $file, '', self::BRICKS_ELEMENT );
			}
		}

		/**
		 * Statistics table, built on first use.
		 *
		 * @return Stats_Table
		 */
		public function stats(): Stats_Table {
			if ( null === $this->stats ) {
				$this->stats = new Stats_Table();
			}

			return $this->stats;
		}

		/**
		 * QR files, built on first use: the QR library is only loaded when a
		 * code is actually drawn.
		 *
		 * @return Qr_Output
		 */
		public function qr(): Qr_Output {
			if ( null === $this->qr ) {
				$this->qr = new Qr_Output( $this->settings, $this->company, new Qr_Service() );
			}

			return $this->qr;
		}
	}
}
