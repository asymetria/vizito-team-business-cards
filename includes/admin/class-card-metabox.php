<?php
/**
 * The card edit screen.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Admin;

use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Field_Sanitizer;
use Asymetria\TeamCards\Card\Fields;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Card_Metabox' ) ) {

	/**
	 * One meta box with every card field. The post title is not edited
	 * directly: it is derived from the first and last name on save, and
	 * WordPress builds the slug from it on first publish.
	 */
	final class Card_Metabox {

		public const NONCE_NAME   = 'tbcards_card_nonce';
		public const NONCE_PREFIX = 'tbcards_save_card_';
		public const INPUT        = 'tbcards';

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
		 * Answers true for autosaves and revisions, which must not save.
		 *
		 * @var callable
		 */
		private $skip;

		/**
		 * Constructor.
		 *
		 * @param Settings        $settings Plugin settings.
		 * @param Company_Profile $company  Company profile.
		 * @param Card_Repository $cards    Card repository.
		 * @param callable|null   $skip     Autosave/revision check; core's by default.
		 */
		public function __construct( Settings $settings, Company_Profile $company, Card_Repository $cards, ?callable $skip = null ) {
			$this->settings = $settings;
			$this->company  = $company;
			$this->cards    = $cards;
			$this->skip     = $skip ?? static function ( int $post_id ): bool {
				return ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
					|| wp_is_post_autosave( $post_id )
					|| wp_is_post_revision( $post_id );
			};
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'add_meta_boxes_' . Card_Repository::POST_TYPE, array( $this, 'add' ) );
			add_action( 'save_post_' . Card_Repository::POST_TYPE, array( $this, 'save' ) );
			add_filter( 'wp_insert_post_data', array( $this, 'derive_title' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		}

		/**
		 * Registers the meta box.
		 *
		 * @return void
		 */
		public function add(): void {
			add_meta_box(
				'tbcards_card_details',
				__( 'Card details', 'vizito-team-business-cards' ),
				array( $this, 'render' ),
				Card_Repository::POST_TYPE,
				'normal',
				'high'
			);
		}

		/**
		 * Loads the admin script and stylesheet on the card edit screen.
		 *
		 * @param string $hook Admin page hook.
		 * @return void
		 */
		public function enqueue( $hook ): void {
			if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
				return;
			}

			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

			if ( ! $screen || Card_Repository::POST_TYPE !== ( $screen->post_type ?? '' ) ) {
				return;
			}

			wp_enqueue_style( 'tbcards-admin', TBCARDS_URL . 'assets/admin.css', array(), TBCARDS_VERSION );
			wp_enqueue_script( 'tbcards-admin', TBCARDS_URL . 'assets/admin.js', array(), TBCARDS_VERSION, true );
		}

		/**
		 * The meta box body.
		 *
		 * @param object $post The post being edited.
		 * @return void
		 */
		public function render( $post ): void {
			$post_id = (int) $post->ID;
			$raw     = $this->cards->raw( $post_id );
			$codes   = $this->settings->languages();
			$default = $this->settings->default_language();
			$groups  = array(
				'person'  => __( 'Person', 'vizito-team-business-cards' ),
				'contact' => __( 'Contact', 'vizito-team-business-cards' ),
				'address' => __( 'Address', 'vizito-team-business-cards' ),
				'links'   => __( 'Website and social profiles', 'vizito-team-business-cards' ),
				'custom'  => __( 'Extra buttons', 'vizito-team-business-cards' ),
			);

			wp_nonce_field( self::NONCE_PREFIX . $post_id, self::NONCE_NAME );

			echo '<div class="tbcards-metabox">';
			echo '<p class="description">' . esc_html__( 'Fields left empty use the company profile where a grey hint is shown.', 'vizito-team-business-cards' ) . '</p>';

			foreach ( $groups as $group => $legend ) {
				$fields = array_filter(
					Fields::all(),
					static function ( $def ) use ( $group ) {
						return $group === $def['group'] && ! $def['translated'];
					}
				);

				if ( array() === $fields ) {
					continue;
				}

				echo '<fieldset class="tbcards-group"><legend>' . esc_html( $legend ) . '</legend>';

				foreach ( $fields as $key => $def ) {
					$value = is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : '';
					$hint  = $def['inherits'] ? $this->company->value( $key, $default ) : '';

					$this->row( $def, 'tbcards-' . $key, self::INPUT . '[' . $key . ']', $value, $hint );
				}

				echo '</fieldset>';

				if ( 'person' === $group ) {
					$this->render_languages( $raw, $codes, $default );
				}
			}

			echo '</div>';
		}

		/**
		 * Saves the posted fields.
		 *
		 * @param int $post_id Post id.
		 * @return void
		 */
		public function save( $post_id ): void {
			$post_id = (int) $post_id;

			if ( ( $this->skip )( $post_id ) || ! $this->nonce_ok( $post_id ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- sanitised per field below; nonce_ok() checked above.
			$input = isset( $_POST[ self::INPUT ] ) ? wp_unslash( $_POST[ self::INPUT ] ) : array();

			if ( ! is_array( $input ) ) {
				return;
			}

			$codes  = $this->settings->languages();
			$values = array();

			foreach ( Fields::all() as $key => $def ) {
				if ( ! array_key_exists( $key, $input ) ) {
					continue;
				}

				$values[ $key ] = $def['translated']
					? Field_Sanitizer::translated( $def['type'], $input[ $key ], $codes )
					: Field_Sanitizer::value( $def['type'], $input[ $key ] );
			}

			$this->cards->save( $post_id, $values );

			do_action( 'tbcards_card_saved', $post_id );
		}

		/**
		 * Sets the post title to "First Last" from the posted names.
		 *
		 * @param array $data    Slashed post data about to be written.
		 * @param array $postarr Raw post array.
		 * @return array
		 */
		public function derive_title( $data, $postarr ) {
			if ( ! is_array( $data ) || Card_Repository::POST_TYPE !== ( $data['post_type'] ?? '' ) ) {
				return $data;
			}

			$post_id = (int) ( $postarr['ID'] ?? 0 );

			if ( ! $this->nonce_ok( $post_id ) ) {
				return $data;
			}

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- sanitised below; nonce_ok() checked above.
			$input = isset( $_POST[ self::INPUT ] ) ? wp_unslash( $_POST[ self::INPUT ] ) : array();
			$first = Field_Sanitizer::value( 'text', $input['first_name'] ?? '' );
			$last  = Field_Sanitizer::value( 'text', $input['last_name'] ?? '' );
			$name  = trim( $first . ' ' . $last );

			if ( '' !== $name ) {
				$data['post_title'] = wp_slash( $name );
			}

			return $data;
		}

		/**
		 * Translated fields, one tab panel per language. Every panel is visible
		 * in the markup; admin.js turns them into tabs.
		 *
		 * @param array    $raw     Raw stored values.
		 * @param string[] $codes   Language codes.
		 * @param string   $default Default language.
		 * @return void
		 */
		private function render_languages( array $raw, array $codes, string $default ): void {
			$fields = array_filter(
				Fields::all(),
				static function ( $def ) {
					return $def['translated'];
				}
			);

			echo '<fieldset class="tbcards-group tbcards-langs" data-tbcards-tabs><legend>' . esc_html__( 'Role and texts, per language', 'vizito-team-business-cards' ) . '</legend>';
			echo '<div class="tbcards-tablist" role="tablist" hidden>';

			foreach ( $codes as $i => $code ) {
				echo '<button type="button" class="button" role="tab" id="tbcards-tab-' . esc_attr( $code ) . '" aria-controls="tbcards-panel-' . esc_attr( $code ) . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '">' . esc_html( strtoupper( $code ) ) . '</button>';
			}

			echo '</div>';

			foreach ( $codes as $code ) {
				echo '<div class="tbcards-panel" role="tabpanel" id="tbcards-panel-' . esc_attr( $code ) . '" aria-labelledby="tbcards-tab-' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '">';
				echo '<p class="tbcards-panel-label"><code>' . esc_html( $code ) . '</code></p>';

				foreach ( $fields as $key => $def ) {
					$stored = is_array( $raw[ $key ] ) ? (string) ( $raw[ $key ][ $code ] ?? '' ) : '';
					$hint   = $def['inherits'] ? $this->company->value( $key, $code ) : '';

					if ( '' === $hint && $code !== $default && is_array( $raw[ $key ] ) ) {
						$hint = (string) ( $raw[ $key ][ $default ] ?? '' );
					}

					$this->row( $def, 'tbcards-' . $key . '-' . $code, self::INPUT . '[' . $key . '][' . $code . ']', $stored, $hint );
				}

				echo '</div>';
			}

			echo '</fieldset>';
		}

		/**
		 * One labelled input.
		 *
		 * @param array  $def   Field definition.
		 * @param string $id    Element id.
		 * @param string $name  Input name.
		 * @param string $value Stored value.
		 * @param string $hint  Value shown when empty.
		 * @return void
		 */
		private function row( array $def, string $id, string $name, string $value, string $hint ): void {
			$placeholder = '' !== $hint ? ' placeholder="' . esc_attr( $hint ) . '"' : '';

			echo '<p class="tbcards-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $def['label'] ) . '</label><br>';

			if ( 'textarea' === $def['type'] ) {
				echo '<textarea class="large-text" rows="3" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $placeholder . '>' . esc_textarea( $value ) . '</textarea>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $placeholder is escaped above.
			} else {
				$type = in_array( $def['type'], array( 'tel', 'email', 'url' ), true ) ? $def['type'] : 'text';
				echo '<input type="' . esc_attr( $type ) . '" class="regular-text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . $placeholder . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $placeholder is escaped above.
			}

			echo '</p>';
		}

		/**
		 * Whether the request carries this post's nonce.
		 *
		 * @param int $post_id Post id.
		 * @return bool
		 */
		private function nonce_ok( int $post_id ): bool {
			if ( ! isset( $_POST[ self::NONCE_NAME ] ) ) {
				return false;
			}

			$nonce = sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) );

			return (bool) wp_verify_nonce( $nonce, self::NONCE_PREFIX . $post_id );
		}
	}
}
