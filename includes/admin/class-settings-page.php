<?php
/**
 * Company profile and settings screens.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Admin;

use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Settings_Page' ) ) {

	/**
	 * Two submenu pages under the card post type's own menu. Both post to
	 * options.php; sanitising lives in Settings and Company_Profile.
	 */
	final class Settings_Page {

		public const PARENT       = 'edit.php?post_type=' . Card_Repository::POST_TYPE;
		public const COMPANY_SLUG = 'tbcards-company';
		public const SETTINGS_SLUG = 'tbcards-settings';

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
		 * Constructor.
		 *
		 * @param Settings        $settings Plugin settings.
		 * @param Company_Profile $company  Company profile.
		 */
		public function __construct( Settings $settings, Company_Profile $company ) {
			$this->settings = $settings;
			$this->company  = $company;
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_action( 'admin_notices', array( $this, 'base_changed_notice' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}

		/**
		 * Adds the two pages.
		 *
		 * @return void
		 */
		public function register_menu(): void {
			add_submenu_page(
				self::PARENT,
				__( 'Company profile', 'vizito-team-business-cards' ),
				__( 'Company profile', 'vizito-team-business-cards' ),
				\Asymetria\TeamCards\Capabilities::settings(),
				self::COMPANY_SLUG,
				array( $this, 'render_company' )
			);

			add_submenu_page(
				self::PARENT,
				__( 'Team Cards settings', 'vizito-team-business-cards' ),
				__( 'Settings', 'vizito-team-business-cards' ),
				\Asymetria\TeamCards\Capabilities::settings(),
				self::SETTINGS_SLUG,
				array( $this, 'render_settings' )
			);
		}

		/**
		 * Registers both options.
		 *
		 * @return void
		 */
		public function register_settings(): void {
			register_setting(
				Company_Profile::OPTION,
				Company_Profile::OPTION,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this->company, 'sanitize' ),
					'default'           => array(),
				)
			);

			register_setting(
				Settings::OPTION,
				Settings::OPTION,
				array(
					'type'              => 'array',
					'sanitize_callback' => array( $this->settings, 'sanitize' ),
					'default'           => array(),
				)
			);
		}

		/**
		 * Loads the media picker on the company screen.
		 *
		 * @param string $hook Admin page hook.
		 * @return void
		 */
		public function enqueue( $hook ): void {
			if ( ! is_string( $hook ) || ! str_ends_with( $hook, self::COMPANY_SLUG ) ) {
				return;
			}

			if ( function_exists( 'wp_enqueue_media' ) ) {
				wp_enqueue_media();
			}

			wp_enqueue_script( 'tbcards-admin', TBCARDS_URL . 'assets/admin.js', array(), TBCARDS_VERSION, true );
		}

		/**
		 * The company profile form.
		 *
		 * @return void
		 */
		public function render_company(): void {
			$this->require_capability();

			$raw   = $this->company->raw();
			$codes = $this->settings->languages();

			echo '<div class="wrap"><h1>' . esc_html__( 'Company profile', 'vizito-team-business-cards' ) . '</h1>';

			// WordPress prints these by itself only under the Settings menu.
			settings_errors();
			echo '<p>' . esc_html__( 'Cards use these values wherever their own field is left empty.', 'vizito-team-business-cards' ) . '</p>';
			echo '<form method="post" action="options.php">';
			settings_fields( Company_Profile::OPTION );
			echo '<table class="form-table" role="presentation">';

			foreach ( Company_Profile::fields() as $key => $def ) {
				$value = $raw[ $key ] ?? '';
				$id    = 'tbcards-company-' . $key;

				echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label></th><td>';

				if ( 'attachment' === $def['type'] ) {
					echo '<input type="number" min="0" class="small-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( Company_Profile::OPTION . '[' . $key . ']' ) . '" value="' . esc_attr( (string) absint( $value ) ) . '">';
					echo ' <button type="button" class="button" data-tbcards-media="' . esc_attr( $id ) . '">' . esc_html__( 'Select logo', 'vizito-team-business-cards' ) . '</button>';
				} elseif ( $def['translated'] ) {
					foreach ( $codes as $code ) {
						$lang_id = $id . '-' . $code;
						$current = is_array( $value ) ? (string) ( $value[ $code ] ?? '' ) : '';
						echo '<p><label for="' . esc_attr( $lang_id ) . '">' . esc_html( $def['label'] ) . ' (<code>' . esc_html( $code ) . '</code>)</label><br>';
						self::input( $def['type'], $lang_id, Company_Profile::OPTION . '[' . $key . '][' . $code . ']', $current );
						echo '</p>';
					}
				} else {
					self::input( $def['type'], $id, Company_Profile::OPTION . '[' . $key . ']', is_scalar( $value ) ? (string) $value : '' );
				}

				echo '</td></tr>';
			}

			echo '</table>';
			submit_button();
			echo '</form></div>';
		}

		/**
		 * The settings form.
		 *
		 * @return void
		 */
		public function render_settings(): void {
			$this->require_capability();

			$all   = $this->settings->all();
			$name  = static function ( string $key ): string {
				return Settings::OPTION . '[' . $key . ']';
			};
			$lines = array();

			foreach ( $all['languages'] as $row ) {
				$lines[] = trim( $row['code'] . ' ' . $row['locale'] );
			}

			echo '<div class="wrap"><h1>' . esc_html__( 'Team Cards settings', 'vizito-team-business-cards' ) . '</h1>';

			// WordPress prints these by itself only under the Settings menu, and
			// a refused QR colour pair would otherwise go unexplained.
			settings_errors();
			echo '<form method="post" action="options.php">';
			settings_fields( Settings::OPTION );

			echo '<h2>' . esc_html__( 'Languages and address', 'vizito-team-business-cards' ) . '</h2><table class="form-table" role="presentation">';

			echo '<tr><th scope="row"><label for="tbcards-languages">' . esc_html__( 'Languages', 'vizito-team-business-cards' ) . '</label></th><td>';
			echo '<textarea id="tbcards-languages" class="regular-text code" rows="4" name="' . esc_attr( $name( 'languages' ) ) . '">' . esc_textarea( implode( "\n", $lines ) ) . '</textarea>';
			echo '<p class="description">' . esc_html__( 'One per line: a language code and, optionally, a WordPress locale. Example: "de de_DE". The order is the order of the language switcher.', 'vizito-team-business-cards' ) . '</p></td></tr>';

			echo '<tr><th scope="row"><label for="tbcards-default-language">' . esc_html__( 'Default language', 'vizito-team-business-cards' ) . '</label></th><td>';
			echo '<select id="tbcards-default-language" name="' . esc_attr( $name( 'default_language' ) ) . '">';
			foreach ( $all['languages'] as $row ) {
				echo '<option value="' . esc_attr( $row['code'] ) . '"' . selected( $row['code'], $all['default_language'], false ) . '>' . esc_html( $row['code'] ) . '</option>';
			}
			echo '</select></td></tr>';

			self::checkbox_row( $name( 'auto_language' ), 'tbcards-auto-language', (bool) $all['auto_language'], __( 'Browser language', 'vizito-team-business-cards' ), __( 'Open a scanned card in the visitor\'s browser language when it is on the list.', 'vizito-team-business-cards' ) );

			echo '<tr><th scope="row"><label for="tbcards-url-base">' . esc_html__( 'Card address', 'vizito-team-business-cards' ) . '</label></th><td>';
			echo '<code>' . esc_html( home_url( '/' ) ) . '</code><input type="text" id="tbcards-url-base" class="regular-text" name="' . esc_attr( $name( 'url_base' ) ) . '" value="' . esc_attr( $all['url_base'] ) . '"><code>/first-last/</code>';
			echo '<p class="description">' . esc_html__( 'Printed QR codes contain this address. Changing it breaks every code already printed.', 'vizito-team-business-cards' ) . '</p></td></tr>';
			echo '</table>';

			echo '<h2>' . esc_html__( 'Appearance', 'vizito-team-business-cards' ) . '</h2><table class="form-table" role="presentation">';

			echo '<tr><th scope="row"><label for="tbcards-accent">' . esc_html__( 'Accent colour', 'vizito-team-business-cards' ) . '</label></th><td>';
			echo '<input type="color" id="tbcards-accent" name="' . esc_attr( $name( 'accent' ) ) . '" value="' . esc_attr( $all['accent'] ) . '"></td></tr>';

			self::select_row(
				$name( 'scheme' ),
				'tbcards-scheme',
				$all['scheme'],
				__( 'Colour scheme', 'vizito-team-business-cards' ),
				array(
					'auto'  => __( 'Follow the visitor\'s device', 'vizito-team-business-cards' ),
					'light' => __( 'Light', 'vizito-team-business-cards' ),
					'dark'  => __( 'Dark', 'vizito-team-business-cards' ),
				)
			);

			self::select_row(
				$name( 'photo_shape' ),
				'tbcards-photo-shape',
				$all['photo_shape'],
				__( 'Photo shape', 'vizito-team-business-cards' ),
				array(
					'round'  => __( 'Round', 'vizito-team-business-cards' ),
					'square' => __( 'Square', 'vizito-team-business-cards' ),
				)
			);

			self::checkbox_row( $name( 'vcard_photo' ), 'tbcards-vcard-photo', (bool) $all['vcard_photo'], __( 'Photo in contact file', 'vizito-team-business-cards' ), __( 'Include the photo in the downloaded contact, so it shows in the phone\'s address book.', 'vizito-team-business-cards' ) );
			echo '</table>';

			echo '<h2>' . esc_html__( 'QR codes', 'vizito-team-business-cards' ) . '</h2><table class="form-table" role="presentation">';

			echo '<tr><th scope="row"><label for="tbcards-qr-dark">' . esc_html__( 'Module colour', 'vizito-team-business-cards' ) . '</label></th><td>';
			echo '<input type="color" id="tbcards-qr-dark" name="' . esc_attr( $name( 'qr_dark' ) ) . '" value="' . esc_attr( $all['qr_dark'] ) . '"></td></tr>';

			echo '<tr><th scope="row"><label for="tbcards-qr-light">' . esc_html__( 'Background colour', 'vizito-team-business-cards' ) . '</label></th><td>';
			echo '<input type="color" id="tbcards-qr-light" name="' . esc_attr( $name( 'qr_light' ) ) . '" value="' . esc_attr( $all['qr_light'] ) . '">';
			echo '<p class="description">' . esc_html__( 'Keep modules dark on a light background. Pairs that would not scan reliably are refused.', 'vizito-team-business-cards' ) . '</p></td></tr>';

			self::checkbox_row( $name( 'qr_logo' ), 'tbcards-qr-logo', (bool) $all['qr_logo'], __( 'Logo in the code', 'vizito-team-business-cards' ), __( 'Put the company logo in the middle of printed codes. Codes then use the highest error correction; test a paper proof before printing.', 'vizito-team-business-cards' ) );
			echo '</table>';

			echo '<h2>' . esc_html__( 'Data', 'vizito-team-business-cards' ) . '</h2><table class="form-table" role="presentation">';

			echo '<tr><th scope="row"><label for="tbcards-retention">' . esc_html__( 'Keep statistics for', 'vizito-team-business-cards' ) . '</label></th><td>';
			echo '<input type="number" min="0" max="' . esc_attr( (string) Settings::MAX_RETENTION ) . '" class="small-text" id="tbcards-retention" name="' . esc_attr( $name( 'retention_days' ) ) . '" value="' . esc_attr( (string) $all['retention_days'] ) . '"> ';
			echo esc_html__( 'days (0 = keep)', 'vizito-team-business-cards' ) . '</td></tr>';

			self::checkbox_row( $name( 'delete_data' ), 'tbcards-delete-data', (bool) $all['delete_data'], __( 'Uninstall', 'vizito-team-business-cards' ), __( 'Delete all cards, settings and statistics when the plugin is deleted.', 'vizito-team-business-cards' ) );
			echo '</table>';

			submit_button();
			echo '</form></div>';
		}

		/**
		 * One-off warning after the card address base changed.
		 *
		 * @return void
		 */
		public function base_changed_notice(): void {
			if ( ! current_user_can( \Asymetria\TeamCards\Capabilities::settings() ) ) {
				return;
			}

			$old = get_transient( Settings::BASE_CHANGED );

			if ( false === $old ) {
				return;
			}

			delete_transient( Settings::BASE_CHANGED );

			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: the previous address base, e.g. /card/ */
				esc_html__( 'The card address changed. QR codes already printed still point to %s and will stop working.', 'vizito-team-business-cards' ),
				'<code>/' . esc_html( (string) $old ) . '/</code>'
			);
			echo '</p></div>';
		}

		/**
		 * Stops users who cannot manage options.
		 *
		 * @return void
		 */
		private function require_capability(): void {
			if ( ! current_user_can( \Asymetria\TeamCards\Capabilities::settings() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to manage these settings.', 'vizito-team-business-cards' ) );
			}
		}

		/**
		 * Prints one input for a field type.
		 *
		 * @param string $type  Field type.
		 * @param string $id    Element id.
		 * @param string $name  Input name.
		 * @param string $value Current value.
		 * @return void
		 */
		public static function input( string $type, string $id, string $name, string $value ): void {
			if ( 'textarea' === $type ) {
				echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">' . esc_textarea( $value ) . '</textarea>';
				return;
			}

			$html_type = in_array( $type, array( 'tel', 'email', 'url' ), true ) ? $type : 'text';

			echo '<input type="' . esc_attr( $html_type ) . '" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}

		/**
		 * A checkbox row. The hidden zero before the box makes an unticked box
		 * reach the sanitiser, which only touches keys it receives.
		 *
		 * @param string $name        Input name.
		 * @param string $id          Element id.
		 * @param bool   $on          Current state.
		 * @param string $label       Row label.
		 * @param string $description Text beside the box.
		 * @return void
		 */
		private static function checkbox_row( string $name, string $id, bool $on, string $label, string $description ): void {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td>';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
			echo '<label for="' . esc_attr( $id ) . '"><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( $on, true, false ) . '> ' . esc_html( $description ) . '</label>';
			echo '</td></tr>';
		}

		/**
		 * A select row.
		 *
		 * @param string                $name    Input name.
		 * @param string                $id      Element id.
		 * @param string                $current Current value.
		 * @param string                $label   Row label.
		 * @param array<string, string> $options Value => label.
		 * @return void
		 */
		private static function select_row( string $name, string $id, string $current, string $label, array $options ): void {
			echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label></th><td>';
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '">';
			foreach ( $options as $value => $text ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $current, false ) . '>' . esc_html( $text ) . '</option>';
			}
			echo '</select></td></tr>';
		}
	}
}
