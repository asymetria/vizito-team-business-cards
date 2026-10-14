<?php
/**
 * The /card/{slug}/vcard/ address.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\VCard;

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Card\Card_Type;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Front\Language;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\VCard_Endpoint' ) ) {

	/**
	 * Serves the contact file. An inactive card answers 410 and discloses
	 * nothing. The photo is resized once and cached in post meta, keyed by the
	 * attachment and its file time, and dropped whenever the card is saved.
	 */
	final class VCard_Endpoint {

		public const PHOTO_META = '_tbcards_vcard_photo';
		public const PHOTO_SIZE = 256;

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

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
		 * Language resolution.
		 *
		 * @var Language
		 */
		private Language $language;

		/**
		 * Sends a status, headers and a body, then ends the request.
		 *
		 * @var callable
		 */
		private $send;

		/**
		 * Constructor.
		 *
		 * @param Card_Repository $cards    Card repository.
		 * @param Settings        $settings Plugin settings.
		 * @param Company_Profile $company  Company profile.
		 * @param Language        $language Language resolution.
		 * @param callable|null   $send     function( int $status, array $headers, string $body ): void.
		 */
		public function __construct( Card_Repository $cards, Settings $settings, Company_Profile $company, Language $language, ?callable $send = null ) {
			$this->cards    = $cards;
			$this->settings = $settings;
			$this->company  = $company;
			$this->language = $language;
			$this->send     = $send ?? static function ( int $status, array $headers, string $body ): void {
				\Asymetria\TeamCards\Http::clean_buffers();
				status_header( $status );
				nocache_headers();

				foreach ( $headers as $header ) {
					header( $header );
				}

				echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a text/vcard file, not HTML.
				exit;
			};
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'template_redirect', array( $this, 'serve' ), 5 );
			add_action( 'tbcards_card_saved', array( $this, 'forget_photo' ) );
		}

		/**
		 * The template_redirect handler.
		 *
		 * @return void
		 */
		public function serve(): void {
			if ( ! is_singular( Card_Repository::POST_TYPE ) || ! get_query_var( Card_Type::QUERY_VAR ) ) {
				return;
			}

			$card = $this->cards->find( get_queried_object_id(), $this->language->current( 'page' ) );

			if ( null === $card ) {
				return;
			}

			if ( ! $card->is_active() ) {
				( $this->send )( 410, array( 'Content-Type: text/plain; charset=utf-8' ), __( 'This contact is no longer available.', 'vizito-team-business-cards' ) );
				return;
			}

			$body = ( new VCard_Builder() )->build(
				$card,
				$this->company->value( 'company_name', $card->lang() ),
				$this->settings->get( 'vcard_photo' ) ? $this->photo( $card ) : ''
			);

			do_action( 'tbcards_vcard_downloaded', $card->id() );

			( $this->send )(
				200,
				array(
					'Content-Type: text/vcard; charset=utf-8',
					'Content-Disposition: attachment; filename="' . VCard_Builder::filename( $card ) . '"',
				),
				$body
			);
		}

		/**
		 * Drops the cached photo of a saved card.
		 *
		 * @param int $post_id Post id.
		 * @return void
		 */
		public function forget_photo( $post_id ): void {
			delete_post_meta( (int) $post_id, self::PHOTO_META );
		}

		/**
		 * The photo as base64 JPEG, resized once and cached.
		 *
		 * @param Card $card Card.
		 * @return string
		 */
		private function photo( Card $card ): string {
			$file = $card->photo_id() ? (string) get_attached_file( $card->photo_id() ) : '';

			if ( '' === $file || ! is_readable( $file ) ) {
				return '';
			}

			$key    = $card->photo_id() . '-' . (int) filemtime( $file );
			$cached = get_post_meta( $card->id(), self::PHOTO_META, true );

			if ( is_array( $cached ) && ( $cached['key'] ?? '' ) === $key ) {
				return (string) $cached['data'];
			}

			$data = $this->resize( $file );

			update_post_meta(
				$card->id(),
				self::PHOTO_META,
				array(
					'key'  => $key,
					'data' => $data,
				)
			);

			return $data;
		}

		/**
		 * A square JPEG of the photo, base64, or "" when the editor fails.
		 *
		 * @param string $file Image path.
		 * @return string
		 */
		private function resize( string $file ): string {
			if ( ! function_exists( 'wp_get_image_editor' ) ) {
				return '';
			}

			$editor = wp_get_image_editor( $file );

			if ( is_wp_error( $editor ) ) {
				return '';
			}

			$editor->resize( self::PHOTO_SIZE, self::PHOTO_SIZE, true );
			$editor->set_quality( 82 );

			// Not wp_tempnam(): it lives in wp-admin/includes/file.php, which the
			// public site, where this runs, never loads.
			$temp = tempnam( get_temp_dir(), 'tbcards-vcard' );

			if ( false === $temp ) {
				return '';
			}

			$saved = $editor->save( $temp, 'image/jpeg' );

			if ( is_wp_error( $saved ) || empty( $saved['path'] ) ) {
				wp_delete_file( $temp );
				return '';
			}

			$bytes = file_get_contents( $saved['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- our own temp file.

			wp_delete_file( $saved['path'] );

			if ( $saved['path'] !== $temp ) {
				wp_delete_file( $temp );
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- vCard PHOTO is base64 by definition.
			return false === $bytes ? '' : base64_encode( $bytes );
		}
	}
}
