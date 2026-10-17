<?php
/**
 * QR in the admin: card meta box, single downloads, print export, test sheet.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Admin;

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Qr\Qr_Output;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Qr_Admin' ) ) {

	/**
	 * Every QR screen and file handler. Responses go through one injectable
	 * sender, so headers and the final exit can be observed in tests.
	 */
	final class Qr_Admin {

		public const PAGE_SLUG     = 'tbcards-print';
		public const SHEET_HANDLE  = 'tbcards-qr-sheet';
		public const EXPORT_NONCE  = 'tbcards_qr_export';
		public const SINGLE_PREFIX = 'tbcards_qr_';

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

		/**
		 * QR files.
		 *
		 * @var Qr_Output
		 */
		private Qr_Output $output;

		/**
		 * Sends headers and a body, then ends the request.
		 *
		 * @var callable
		 */
		private $send;

		/**
		 * Constructor.
		 *
		 * @param Card_Repository $cards  Card repository.
		 * @param Qr_Output       $output QR files.
		 * @param callable|null   $send   function( array $headers, string $body ): void.
		 */
		public function __construct( Card_Repository $cards, Qr_Output $output, ?callable $send = null ) {
			$this->cards  = $cards;
			$this->output = $output;
			$this->send   = $send ?? static function ( array $headers, string $body ): void {
				\Asymetria\TeamCards\Http::clean_buffers();
				nocache_headers();

				foreach ( $headers as $header ) {
					header( $header );
				}

				echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- file bytes or a page escaped where it was built.
				exit;
			};
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'add_meta_boxes_' . Card_Repository::POST_TYPE, array( $this, 'add_meta_box' ) );
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_post_tbcards_qr', array( $this, 'download' ) );
			add_action( 'admin_post_tbcards_qr_export', array( $this, 'export' ) );
		}

		/**
		 * The side meta box.
		 *
		 * @return void
		 */
		public function add_meta_box(): void {
			add_meta_box( 'tbcards_card_qr', __( 'QR code', 'vizito-team-business-cards' ), array( $this, 'render_meta_box' ), Card_Repository::POST_TYPE, 'side' );
		}

		/**
		 * The print page.
		 *
		 * @return void
		 */
		public function register_menu(): void {
			add_submenu_page(
				Settings_Page::PARENT,
				__( 'Print codes', 'vizito-team-business-cards' ),
				__( 'Print codes', 'vizito-team-business-cards' ),
				\Asymetria\TeamCards\Capabilities::manage(),
				self::PAGE_SLUG,
				array( $this, 'render_page' )
			);
		}

		/**
		 * Preview and downloads for one card.
		 *
		 * @param object $post The post being edited.
		 * @return void
		 */
		public function render_meta_box( $post ): void {
			$card = 'publish' === ( $post->post_status ?? '' ) ? $this->cards->find( (int) $post->ID ) : null;

			if ( null === $card ) {
				echo '<p>' . esc_html__( 'Publish the card to get its QR code. The code holds the card\'s final address.', 'vizito-team-business-cards' ) . '</p>';
				return;
			}

			echo '<div class="tbcards-qr-preview">' . $this->output->svg( $card, false ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG built by Svg_Writer from validated values.
			echo '<p><code>' . esc_html( $card->url() ) . '</code></p><p>';

			foreach ( $this->formats() as $format ) {
				foreach ( $this->variants() as $logo ) {
					echo '<a class="button" href="' . esc_url( $this->download_url( $card->id(), $format, $logo ) ) . '">'
						. esc_html( strtoupper( $format ) . ( $logo ? ' + ' . __( 'logo', 'vizito-team-business-cards' ) : '' ) ) . '</a> ';
				}
			}

			echo '</p><p class="description">' . esc_html__( 'Give the printer the SVG. Print one proof and scan it from paper before the full run.', 'vizito-team-business-cards' ) . '</p>';
		}

		/**
		 * The print page: every published, active card with a checkbox.
		 *
		 * @return void
		 */
		public function render_page(): void {
			if ( ! current_user_can( \Asymetria\TeamCards\Capabilities::manage() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to export codes.', 'vizito-team-business-cards' ) );
			}

			echo '<div class="wrap"><h1>' . esc_html__( 'Print codes', 'vizito-team-business-cards' ) . '</h1>';

			$cards = $this->active_cards();

			if ( array() === $cards ) {
				echo '<p>' . esc_html__( 'No published cards yet.', 'vizito-team-business-cards' ) . '</p></div>';
				return;
			}

			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			echo '<input type="hidden" name="action" value="tbcards_qr_export">';
			wp_nonce_field( self::EXPORT_NONCE );
			echo '<ul>';

			foreach ( $cards as $card ) {
				echo '<li><label><input type="checkbox" name="cards[]" value="' . esc_attr( (string) $card->id() ) . '" checked> '
					. esc_html( $card->full_name() ) . ' <code>' . esc_html( $card->slug() ) . '</code></label></li>';
			}

			echo '</ul><p>';
			echo '<button type="submit" class="button button-primary" name="tbcards_do" value="zip">' . esc_html__( 'Download ZIP', 'vizito-team-business-cards' ) . '</button> ';
			echo '<button type="submit" class="button" name="tbcards_do" value="sheet" formtarget="_blank">' . esc_html__( 'Open test sheet', 'vizito-team-business-cards' ) . '</button>';
			echo '</p></form></div>';
		}

		/**
		 * admin-post handler for one file.
		 *
		 * @return void
		 */
		public function download(): void {
			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified below with the per-card action.
			$id     = isset( $_GET['card'] ) ? absint( $_GET['card'] ) : 0;
			$format = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'svg';
			$logo   = ! empty( $_GET['logo'] );
			$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			// phpcs:enable

			if ( ! wp_verify_nonce( $nonce, self::SINGLE_PREFIX . $id ) || ! current_user_can( 'edit_post', $id ) ) {
				wp_die( esc_html__( 'This link has expired. Reload the card and try again.', 'vizito-team-business-cards' ), '', array( 'response' => 403 ) );
			}

			$card = $this->cards->find( $id );

			if ( null === $card || ! in_array( $format, $this->formats(), true ) ) {
				wp_die( esc_html__( 'Nothing to download.', 'vizito-team-business-cards' ), '', array( 'response' => 404 ) );
			}

			$body = 'png' === $format ? (string) $this->output->png( $card, $logo ) : $this->output->svg( $card, $logo );
			$name = $card->slug() . ( $logo ? '-logo' : '' ) . '-qr.' . $format;

			( $this->send )(
				array(
					'Content-Type: ' . ( 'png' === $format ? 'image/png' : 'image/svg+xml' ),
					'Content-Disposition: attachment; filename="' . $name . '"',
				),
				$body
			);
		}

		/**
		 * admin-post handler for the ZIP and the test sheet.
		 *
		 * @return void
		 */
		public function export(): void {
			check_admin_referer( self::EXPORT_NONCE );

			if ( ! current_user_can( \Asymetria\TeamCards\Capabilities::manage() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to export codes.', 'vizito-team-business-cards' ), '', array( 'response' => 403 ) );
			}

			$ids   = isset( $_POST['cards'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['cards'] ) ) : array();
			$cards = array_values(
				array_filter(
					$this->active_cards(),
					static function ( Card $card ) use ( $ids ) {
						return in_array( $card->id(), $ids, true );
					}
				)
			);

			if ( array() === $cards ) {
				wp_die( esc_html__( 'Select at least one card.', 'vizito-team-business-cards' ) );
			}

			$do = isset( $_POST['tbcards_do'] ) ? sanitize_key( wp_unslash( $_POST['tbcards_do'] ) ) : 'zip';

			if ( 'sheet' === $do ) {
				( $this->send )( array( 'Content-Type: text/html; charset=utf-8' ), $this->sheet( $cards ) );
				return;
			}

			$this->zip( $cards );
		}

		/**
		 * Streams a ZIP with every file for the given cards.
		 *
		 * @param Card[] $cards Cards.
		 * @return void
		 */
		private function zip( array $cards ): void {
			if ( ! class_exists( 'ZipArchive' ) ) {
				wp_die( esc_html__( 'This server cannot build ZIP files (the PHP zip extension is missing). Download the codes one by one from each card.', 'vizito-team-business-cards' ) );
			}

			$file = tempnam( get_temp_dir(), 'tbcards-qr' );
			$zip  = new \ZipArchive();

			if ( false === $file || true !== $zip->open( $file, \ZipArchive::OVERWRITE ) ) {
				wp_die( esc_html__( 'The ZIP file could not be created in the server\'s temporary folder. Download the codes one by one from each card.', 'vizito-team-business-cards' ) );
			}

			foreach ( $cards as $card ) {
				foreach ( $this->variants() as $logo ) {
					$base = $card->slug() . '/' . $card->slug() . ( $logo ? '-logo' : '' );

					$zip->addFromString( $base . '.svg', $this->output->svg( $card, $logo ) );

					$png = $this->output->png( $card, $logo );

					if ( null !== $png ) {
						$zip->addFromString( $base . '.png', $png );
					}
				}
			}

			$zip->close();

			$body = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- our own temp file.
			wp_delete_file( $file );

			( $this->send )(
				array(
					'Content-Type: application/zip',
					'Content-Disposition: attachment; filename="qr-codes-' . gmdate( 'Y-m-d' ) . '.zip"',
				),
				$body
			);
		}

		/**
		 * A printable A4 page: every code, both variants, with name, role and address.
		 *
		 * @param Card[] $cards Cards.
		 * @return string
		 */
		private function sheet( array $cards ): string {
			$cells = '';

			foreach ( $cards as $card ) {
				foreach ( $this->variants() as $logo ) {
					$cells .= '<figure>' . $this->output->svg( $card, $logo )
						. '<figcaption><strong>' . esc_html( $card->full_name() ) . '</strong>'
						. ( $card->has( 'job_title' ) ? '<br>' . esc_html( $card->get( 'job_title' ) ) : '' )
						. '<br><small>' . esc_html( $card->url() ) . ( $logo ? ' · ' . esc_html__( 'logo', 'vizito-team-business-cards' ) : '' ) . '</small></figcaption></figure>';
				}
			}

			wp_register_style( self::SHEET_HANDLE, TBCARDS_URL . 'assets/qr-sheet.css', array(), TBCARDS_VERSION );
			ob_start();
			wp_print_styles( array( self::SHEET_HANDLE ) );
			$styles = (string) ob_get_clean();

			return '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html__( 'QR test sheet', 'vizito-team-business-cards' ) . '</title>'
				. $styles
				. '</head><body><main>' . $cells . '</main></body></html>';
		}

		/**
		 * Published, active cards, by name.
		 *
		 * @return Card[]
		 */
		private function active_cards(): array {
			$posts = get_posts(
				array(
					'post_type'   => Card_Repository::POST_TYPE,
					'post_status' => 'publish',
					'numberposts' => -1,
					'orderby'     => 'title',
					'order'       => 'ASC',
				)
			);
			$cards = array();

			foreach ( $posts as $post ) {
				$card = $this->cards->find( (int) $post->ID );

				if ( null !== $card && $card->is_active() ) {
					$cards[] = $card;
				}
			}

			return $cards;
		}

		/**
		 * The single-file download address.
		 *
		 * @param int    $id     Card id.
		 * @param string $format svg or png.
		 * @param bool   $logo   With logo.
		 * @return string
		 */
		private function download_url( int $id, string $format, bool $logo ): string {
			$url = admin_url( 'admin-post.php' );

			foreach ( array(
				'action'   => 'tbcards_qr',
				'card'     => (string) $id,
				'format'   => $format,
				'logo'     => $logo ? '1' : '0',
				'_wpnonce' => wp_create_nonce( self::SINGLE_PREFIX . $id ),
			) as $key => $value ) {
				$url = add_query_arg( $key, $value, $url );
			}

			return $url;
		}

		/**
		 * Formats this server can produce.
		 *
		 * @return string[]
		 */
		private function formats(): array {
			return $this->output->png_available() ? array( 'svg', 'png' ) : array( 'svg' );
		}

		/**
		 * Logo variants to offer: without, and with when a logo is set up.
		 *
		 * @return bool[]
		 */
		private function variants(): array {
			return $this->output->logo_available() ? array( false, true ) : array( false );
		}
	}
}
