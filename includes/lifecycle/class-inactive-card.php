<?php
/**
 * Cards of people who have left.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Lifecycle;

use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Field_Sanitizer;
use Asymetria\TeamCards\Company\Company_Profile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Inactive_Card' ) ) {

	/**
	 * A printed QR code outlives a job. When someone leaves, the address keeps
	 * working and does one of three things: shows a notice with the company's
	 * details, redirects to a successor's card, or redirects to a URL.
	 *
	 * Redirects are 302, never 301: browsers keep a 301 indefinitely and the
	 * successor may change. The person's data disappears from the page, the
	 * vCard, embeds and the print export at once.
	 */
	final class Inactive_Card {

		public const MODES        = array( 'notice', 'card', 'url' );
		public const MODE_META    = '_tbcards_inactive_mode';
		public const TARGET_META  = '_tbcards_inactive_target';
		public const NONCE_NAME   = 'tbcards_status_nonce';
		public const NONCE_PREFIX = 'tbcards_status_';
		public const SLUG_NOTICE  = 'tbcards_slug_changed_';

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

		/**
		 * Company profile, for the allowed redirect host.
		 *
		 * @var Company_Profile
		 */
		private Company_Profile $company;

		/**
		 * Redirects and ends the request.
		 *
		 * @var callable
		 */
		private $redirect;

		/**
		 * Constructor.
		 *
		 * @param Card_Repository $cards    Card repository.
		 * @param Company_Profile $company  Company profile.
		 * @param callable|null   $redirect function( string $url, int $status ): void.
		 */
		public function __construct( Card_Repository $cards, Company_Profile $company, ?callable $redirect = null ) {
			$this->cards    = $cards;
			$this->company  = $company;
			$this->redirect = $redirect ?? static function ( string $url, int $status ): void {
				wp_redirect( $url, $status ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- validated in target_url().
				exit;
			};
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 4 );
			add_action( 'add_meta_boxes_' . Card_Repository::POST_TYPE, array( $this, 'add_meta_box' ) );
			add_action( 'save_post_' . Card_Repository::POST_TYPE, array( $this, 'save' ) );
			add_action( 'post_updated', array( $this, 'note_slug_change' ), 10, 3 );
			add_action( 'admin_notices', array( $this, 'slug_notice' ) );
		}

		/**
		 * Redirects an inactive card when it is set to.
		 *
		 * @return void
		 */
		public function maybe_redirect(): void {
			if ( ! is_singular( Card_Repository::POST_TYPE ) ) {
				return;
			}

			$id   = get_queried_object_id();
			$card = $this->cards->find( $id );

			if ( null === $card || $card->is_active() ) {
				return;
			}

			$url = $this->target_url( $id );

			if ( '' !== $url ) {
				( $this->redirect )( $url, 302 );
			}
		}

		/**
		 * Where an inactive card sends visitors, "" to show the notice.
		 *
		 * A successor must be a published, active card other than this one.
		 * A URL must be on this site or on the company website's host.
		 *
		 * @param int $id Card id.
		 * @return string
		 */
		public function target_url( int $id ): string {
			$mode   = (string) get_post_meta( $id, self::MODE_META, true );
			$target = get_post_meta( $id, self::TARGET_META, true );

			if ( 'card' === $mode ) {
				$successor = absint( $target );
				$post      = $successor && $successor !== $id ? get_post( $successor ) : null;
				$card      = $post && 'publish' === ( $post->post_status ?? '' ) ? $this->cards->find( $successor ) : null;

				return null !== $card && $card->is_active() ? $card->url() : '';
			}

			if ( 'url' === $mode ) {
				$url  = esc_url_raw( (string) $target, Field_Sanitizer::URL_SCHEMES );
				$host = (string) wp_parse_url( $url, PHP_URL_HOST );

				if ( '' === $url || '' === $host ) {
					return '';
				}

				$allowed = array_filter(
					array(
						(string) wp_parse_url( home_url(), PHP_URL_HOST ),
						(string) wp_parse_url( $this->company->value( 'website', '' ), PHP_URL_HOST ),
					)
				);

				return in_array( strtolower( $host ), array_map( 'strtolower', $allowed ), true ) ? $url : '';
			}

			return '';
		}

		/**
		 * The side box.
		 *
		 * @return void
		 */
		public function add_meta_box(): void {
			add_meta_box( 'tbcards_card_status', __( 'Status', 'vizito-team-business-cards' ), array( $this, 'render_meta_box' ), Card_Repository::POST_TYPE, 'side', 'high' );
		}

		/**
		 * Active / inactive and what an inactive card does.
		 *
		 * @param object $post The post being edited.
		 * @return void
		 */
		public function render_meta_box( $post ): void {
			$id       = (int) $post->ID;
			$inactive = '' !== (string) get_post_meta( $id, Card_Repository::INACTIVE_META, true );
			$mode     = (string) get_post_meta( $id, self::MODE_META, true );
			$target   = get_post_meta( $id, self::TARGET_META, true );
			$mode     = in_array( $mode, self::MODES, true ) ? $mode : 'notice';

			wp_nonce_field( self::NONCE_PREFIX . $id, self::NONCE_NAME );

			echo '<p><label><input type="checkbox" name="tbcards_status[inactive]" value="1"' . checked( $inactive, true, false ) . '> '
				. esc_html__( 'This person no longer works here', 'vizito-team-business-cards' ) . '</label></p>';

			echo '<p><label for="tbc-inactive-mode">' . esc_html__( 'Then the card address', 'vizito-team-business-cards' ) . '</label><br>';
			echo '<select id="tbc-inactive-mode" name="tbcards_status[mode]">';

			foreach ( array(
				'notice' => __( 'shows a notice with company details', 'vizito-team-business-cards' ),
				'card'   => __( 'redirects to another card', 'vizito-team-business-cards' ),
				'url'    => __( 'redirects to an address', 'vizito-team-business-cards' ),
			) as $value => $label ) {
				echo '<option value="' . esc_attr( $value ) . '"' . selected( $value, $mode, false ) . '>' . esc_html( $label ) . '</option>';
			}

			echo '</select></p>';

			echo '<p><label for="tbc-inactive-card">' . esc_html__( 'Successor', 'vizito-team-business-cards' ) . '</label><br><select id="tbc-inactive-card" name="tbcards_status[card]"><option value="0">—</option>';

			foreach ( get_posts(
				array(
					'post_type'   => Card_Repository::POST_TYPE,
					'post_status' => 'publish',
					'numberposts' => -1,
					'orderby'     => 'title',
					'order'       => 'ASC',
				)
			) as $other ) {
				if ( (int) $other->ID === $id ) {
					continue;
				}

				echo '<option value="' . esc_attr( (string) $other->ID ) . '"' . selected( (int) $other->ID, 'card' === $mode ? absint( $target ) : 0, false ) . '>'
					. esc_html( get_the_title( $other ) ) . '</option>';
			}

			echo '</select></p>';

			echo '<p><label for="tbc-inactive-url">' . esc_html__( 'Address', 'vizito-team-business-cards' ) . '</label><br>';
			echo '<input type="url" class="widefat" id="tbc-inactive-url" name="tbcards_status[url]" value="' . esc_attr( 'url' === $mode ? (string) $target : '' ) . '"></p>';
			echo '<p class="description">' . esc_html__( 'Only this site or the company website. Printed codes keep working either way.', 'vizito-team-business-cards' ) . '</p>';
		}

		/**
		 * Saves the status box.
		 *
		 * @param int $post_id Post id.
		 * @return void
		 */
		public function save( $post_id ): void {
			$post_id = (int) $post_id;

			if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_PREFIX . $post_id ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			$input = isset( $_POST['tbcards_status'] ) && is_array( $_POST['tbcards_status'] ) ? wp_unslash( $_POST['tbcards_status'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitised per key below.
			$mode  = isset( $input['mode'] ) && in_array( $input['mode'], self::MODES, true ) ? $input['mode'] : 'notice';

			if ( ! empty( $input['inactive'] ) ) {
				update_post_meta( $post_id, Card_Repository::INACTIVE_META, '1' );
			} else {
				delete_post_meta( $post_id, Card_Repository::INACTIVE_META );
			}

			update_post_meta( $post_id, self::MODE_META, $mode );

			if ( 'card' === $mode ) {
				update_post_meta( $post_id, self::TARGET_META, absint( $input['card'] ?? 0 ) );
			} elseif ( 'url' === $mode ) {
				update_post_meta( $post_id, self::TARGET_META, esc_url_raw( (string) ( $input['url'] ?? '' ), Field_Sanitizer::URL_SCHEMES ) );
			} else {
				delete_post_meta( $post_id, self::TARGET_META );
			}

			do_action( 'tbcards_card_saved', $post_id );
		}

		/**
		 * Remembers that a published card's address changed, for a warning.
		 *
		 * @param int    $post_id Post id.
		 * @param object $after   Post after the update.
		 * @param object $before  Post before the update.
		 * @return void
		 */
		public function note_slug_change( $post_id, $after, $before ): void {
			if ( Card_Repository::POST_TYPE !== ( $after->post_type ?? '' ) || 'publish' !== ( $before->post_status ?? '' ) ) {
				return;
			}

			// Trashing renames the slug to "…__trashed"; that is not an address change.
			if ( 'trash' === ( $after->post_status ?? '' ) ) {
				return;
			}

			if ( ( $before->post_name ?? '' ) !== ( $after->post_name ?? '' ) ) {
				set_transient( self::SLUG_NOTICE . get_current_user_id(), (string) $before->post_name, HOUR_IN_SECONDS );
			}
		}

		/**
		 * The warning, once.
		 *
		 * @return void
		 */
		public function slug_notice(): void {
			$key = self::SLUG_NOTICE . get_current_user_id();
			$old = get_transient( $key );

			if ( false === $old ) {
				return;
			}

			delete_transient( $key );

			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: the card's previous address slug */
				esc_html__( 'This card\'s address changed. Printed QR codes still carry the old address (%s); WordPress redirects it for now, but it breaks if another card takes that address. Reprint the codes when you can.', 'vizito-team-business-cards' ),
				'<code>' . esc_html( (string) $old ) . '</code>'
			);
			echo '</p></div>';
		}
	}
}
