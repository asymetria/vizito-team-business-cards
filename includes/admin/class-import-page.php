<?php
/**
 * Import / Export screen.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Admin;

use Asymetria\TeamCards\Csv\Csv_Reader;
use Asymetria\TeamCards\Csv\Exporter;
use Asymetria\TeamCards\Csv\Importer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Import_Page' ) ) {

	/**
	 * Upload or paste → preview (nothing written, plan kept per user for an
	 * hour) → import in batches of 50 through a redirect loop, so a large file
	 * never runs into a request time limit and no script is required.
	 */
	final class Import_Page {

		public const SLUG       = 'tbc-import';
		public const NONCE      = 'tbcards_import';
		public const PLAN_KEY   = 'tbcards_import_';
		public const BATCH      = 50;
		public const MAX_BYTES  = 2097152;

		/**
		 * Importer.
		 *
		 * @var Importer
		 */
		private Importer $importer;

		/**
		 * Exporter.
		 *
		 * @var Exporter
		 */
		private Exporter $exporter;

		/**
		 * Redirects and ends the request.
		 *
		 * @var callable
		 */
		private $redirect;

		/**
		 * Sends a file and ends the request.
		 *
		 * @var callable
		 */
		private $send;

		/**
		 * Reads an uploaded file; "" when it is not a valid upload.
		 *
		 * @var callable
		 */
		private $read_upload;

		/**
		 * Constructor.
		 *
		 * @param Importer      $importer    Importer.
		 * @param Exporter      $exporter    Exporter.
		 * @param callable|null $redirect    function( string $url ): void.
		 * @param callable|null $send        function( array $headers, string $body ): void.
		 * @param callable|null $read_upload function( array $file ): string.
		 */
		public function __construct( Importer $importer, Exporter $exporter, ?callable $redirect = null, ?callable $send = null, ?callable $read_upload = null ) {
			$this->importer    = $importer;
			$this->exporter    = $exporter;
			$this->redirect    = $redirect ?? static function ( string $url ): void {
				wp_safe_redirect( $url );
				exit;
			};
			$this->send        = $send ?? static function ( array $headers, string $body ): void {
				\Asymetria\TeamCards\Http::clean_buffers();
				nocache_headers();

				foreach ( $headers as $header ) {
					header( $header );
				}

				echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV file.
				exit;
			};
			$this->read_upload = $read_upload ?? static function ( array $file ): string {
				$tmp = (string) ( $file['tmp_name'] ?? '' );

				if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
					return '';
				}

				return (string) file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the uploaded temp file.
			};
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'admin_menu', array( $this, 'register_menu' ) );
			add_action( 'admin_post_tbcards_import_preview', array( $this, 'preview' ) );
			add_action( 'admin_post_tbcards_import_run', array( $this, 'run' ) );
			add_action( 'admin_post_tbcards_export', array( $this, 'export' ) );
		}

		/**
		 * The page.
		 *
		 * @return void
		 */
		public function register_menu(): void {
			add_submenu_page( Settings_Page::PARENT, __( 'Import / Export', 'vizito-team-business-cards' ), __( 'Import / Export', 'vizito-team-business-cards' ), \Asymetria\TeamCards\Capabilities::manage(), self::SLUG, array( $this, 'render' ) );
		}

		/**
		 * Renders the current step.
		 *
		 * @return void
		 */
		public function render(): void {
			if ( ! current_user_can( \Asymetria\TeamCards\Capabilities::manage() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to import cards.', 'vizito-team-business-cards' ) );
			}

			// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only step switch; every write is nonce-checked in its handler.
			$step = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
			$done = isset( $_GET['done'] ) ? absint( $_GET['done'] ) : 0;
			// phpcs:enable

			echo '<div class="wrap"><h1>' . esc_html__( 'Import / Export', 'vizito-team-business-cards' ) . '</h1>';

			if ( 'done' === $step ) {
				/* translators: %d: number of cards */
				echo '<div class="notice notice-success"><p>' . esc_html( sprintf( _n( '%d card imported.', '%d cards imported.', $done, 'vizito-team-business-cards' ), $done ) ) . '</p></div>';
			}

			$plan = $this->plan();

			if ( 'preview' === $step && null !== $plan ) {
				$this->render_preview( $plan );
			} else {
				$this->render_form();
			}

			echo '</div>';
		}

		/**
		 * admin-post: parse the file, keep the plan, show the preview.
		 *
		 * @return void
		 */
		public function preview(): void {
			$this->guard();

			// phpcs:disable WordPress.Security.NonceVerification.Missing -- guard() ran check_admin_referer() above.
			$text = isset( $_POST['tbcards_csv_text'] ) ? (string) wp_unslash( $_POST['tbcards_csv_text'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- raw CSV; every cell is sanitised per field by the importer.

			if ( isset( $_FILES['tbcards_csv_file'] ) && is_array( $_FILES['tbcards_csv_file'] ) && ! empty( $_FILES['tbcards_csv_file']['name'] ) ) {
				$file = $_FILES['tbcards_csv_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked below.
				// phpcs:enable WordPress.Security.NonceVerification.Missing
				$ext  = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );

				if ( ! in_array( $ext, array( 'csv', 'txt' ), true ) || (int) ( $file['size'] ?? 0 ) > self::MAX_BYTES ) {
					wp_die( esc_html__( 'Upload a .csv or .txt file of at most 2 MB.', 'vizito-team-business-cards' ) );
				}

				$text = ( $this->read_upload )( $file );
			}

			if ( '' === trim( $text ) || strlen( $text ) > self::MAX_BYTES ) {
				wp_die( esc_html__( 'Paste the rows or choose a file (at most 2 MB).', 'vizito-team-business-cards' ) );
			}

			$plan = $this->importer->plan( ( new Csv_Reader() )->parse( $text ) );

			set_transient( self::PLAN_KEY . get_current_user_id(), $plan, HOUR_IN_SECONDS );

			( $this->redirect )( $this->page_url( array( 'step' => 'preview' ) ) );
		}

		/**
		 * admin-post: import one batch, then come back for the next.
		 *
		 * @return void
		 */
		public function run(): void {
			$this->guard();

			$plan = $this->plan();

			if ( null === $plan ) {
				wp_die( esc_html__( 'The preview has expired. Start the import again.', 'vizito-team-business-cards' ) );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- guard() checked the nonce.
			$offset = isset( $_REQUEST['offset'] ) ? absint( $_REQUEST['offset'] ) : 0;
			$done   = isset( $_REQUEST['done'] ) ? absint( $_REQUEST['done'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- guard() checked the nonce.
			$next   = $offset + self::BATCH;

			// A repeated request (double click, reload) must not run a batch twice,
			// or rows planned as "create" would create duplicate cards.
			if ( $offset >= (int) ( $plan['committed'] ?? 0 ) ) {
				$done             += $this->importer->commit( $plan, $offset, self::BATCH );
				$plan['committed'] = $next;
				set_transient( self::PLAN_KEY . get_current_user_id(), $plan, HOUR_IN_SECONDS );
			}

			if ( $next < count( $plan['rows'] ) ) {
				$url = admin_url( 'admin-post.php' );

				foreach ( array(
					'action'   => 'tbcards_import_run',
					'offset'   => (string) $next,
					'done'     => (string) $done,
					'_wpnonce' => wp_create_nonce( self::NONCE ),
				) as $key => $value ) {
					$url = add_query_arg( $key, $value, $url );
				}

				( $this->redirect )( $url );
				return;
			}

			delete_transient( self::PLAN_KEY . get_current_user_id() );

			( $this->redirect )(
				$this->page_url(
					array(
						'step' => 'done',
						'done' => (string) $done,
					)
				)
			);
		}

		/**
		 * admin-post: download every card as CSV.
		 *
		 * @return void
		 */
		public function export(): void {
			$this->guard();

			( $this->send )(
				array(
					'Content-Type: text/csv; charset=utf-8',
					'Content-Disposition: attachment; filename="business-cards-' . gmdate( 'Y-m-d' ) . '.csv"',
				),
				$this->exporter->csv()
			);
		}

		/**
		 * The stored plan of the current user.
		 *
		 * @return array|null
		 */
		private function plan(): ?array {
			$plan = get_transient( self::PLAN_KEY . get_current_user_id() );

			return is_array( $plan ) && isset( $plan['rows'] ) ? $plan : null;
		}

		/**
		 * Nonce and capability for every handler.
		 *
		 * @return void
		 */
		private function guard(): void {
			check_admin_referer( self::NONCE );

			if ( ! current_user_can( \Asymetria\TeamCards\Capabilities::manage() ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to import cards.', 'vizito-team-business-cards' ), '', array( 'response' => 403 ) );
			}
		}

		/**
		 * The upload and export forms.
		 *
		 * @return void
		 */
		private function render_form(): void {
			$post = esc_url( admin_url( 'admin-post.php' ) );

			echo '<h2>' . esc_html__( 'Import', 'vizito-team-business-cards' ) . '</h2>';
			echo '<p>' . esc_html__( 'Columns are the ones in an exported file. Rows match existing cards by email, then by slug. An empty cell leaves a field as it is; a single dash clears it.', 'vizito-team-business-cards' ) . '</p>';
			echo '<form method="post" enctype="multipart/form-data" action="' . $post . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			echo '<input type="hidden" name="action" value="tbcards_import_preview">';
			wp_nonce_field( self::NONCE );
			echo '<p><label for="tbc-csv-file">' . esc_html__( 'CSV file', 'vizito-team-business-cards' ) . '</label><br><input type="file" id="tbc-csv-file" name="tbcards_csv_file" accept=".csv,.txt,text/csv"></p>';
			echo '<p><label for="tbc-csv-text">' . esc_html__( 'Or paste rows from a spreadsheet', 'vizito-team-business-cards' ) . '</label><br><textarea id="tbc-csv-text" name="tbcards_csv_text" rows="8" class="large-text code"></textarea></p>';
			submit_button( __( 'Preview', 'vizito-team-business-cards' ) );
			echo '</form>';

			echo '<h2>' . esc_html__( 'Export', 'vizito-team-business-cards' ) . '</h2>';
			echo '<form method="post" action="' . $post . '"><input type="hidden" name="action" value="tbcards_export">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			wp_nonce_field( self::NONCE );
			submit_button( __( 'Download all cards as CSV', 'vizito-team-business-cards' ), 'secondary' );
			echo '</form>';
		}

		/**
		 * The preview table and the import button.
		 *
		 * @param array $plan Plan.
		 * @return void
		 */
		private function render_preview( array $plan ): void {
			$labels = array(
				'create' => __( 'Create', 'vizito-team-business-cards' ),
				'update' => __( 'Update', 'vizito-team-business-cards' ),
				'error'  => __( 'Error', 'vizito-team-business-cards' ),
			);

			echo '<h2>' . esc_html__( 'Preview', 'vizito-team-business-cards' ) . '</h2>';
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: cards to create, 2: cards to update, 3: rows with errors */
						__( '%1$d to create, %2$d to update, %3$d with errors. Nothing has been written yet.', 'vizito-team-business-cards' ),
						$plan['counts']['create'],
						$plan['counts']['update'],
						$plan['counts']['error']
					)
				)
			);

			foreach ( $plan['warnings'] as $warning ) {
				echo '<div class="notice notice-warning inline"><p>' . esc_html( $warning ) . '</p></div>';
			}

			if ( $plan['counts']['error'] > 0 ) {
				echo '<div class="notice notice-error inline" role="alert"><p>' . esc_html__( 'Rows with errors are skipped. Fix them in the file and import again, or import the rest now.', 'vizito-team-business-cards' ) . '</p></div>';
			}

			echo '<table class="widefat striped"><thead><tr><th scope="col">' . esc_html__( 'Line', 'vizito-team-business-cards' ) . '</th><th scope="col">' . esc_html__( 'Action', 'vizito-team-business-cards' ) . '</th><th scope="col">' . esc_html__( 'Name', 'vizito-team-business-cards' ) . '</th><th scope="col">' . esc_html__( 'Notes', 'vizito-team-business-cards' ) . '</th></tr></thead><tbody>';

			foreach ( $plan['rows'] as $row ) {
				echo '<tr><td>' . esc_html( (string) $row['line'] ) . '</td><td>' . esc_html( $labels[ $row['action'] ] ) . '</td><td>' . esc_html( $row['name'] ) . '</td><td>' . esc_html( implode( ' ', $row['messages'] ) ) . '</td></tr>';
			}

			echo '</tbody></table>';

			if ( $plan['counts']['create'] + $plan['counts']['update'] > 0 ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="tbcards_import_run">';
				wp_nonce_field( self::NONCE );
				submit_button( __( 'Import', 'vizito-team-business-cards' ) );
				echo '</form>';
			}

			echo '<p><a href="' . esc_url( $this->page_url() ) . '">' . esc_html__( 'Start over', 'vizito-team-business-cards' ) . '</a></p>';
		}

		/**
		 * The page address with extra arguments.
		 *
		 * @param array $args Query arguments.
		 * @return string
		 */
		private function page_url( array $args = array() ): string {
			$url = add_query_arg( 'page', self::SLUG, admin_url( Settings_Page::PARENT ) );

			foreach ( $args as $key => $value ) {
				$url = add_query_arg( $key, $value, $url );
			}

			return $url;
		}
	}
}
