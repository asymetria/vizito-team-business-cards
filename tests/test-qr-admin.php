<?php
/**
 * Pins the QR screens and handlers: guards, file names, ZIP contents, test sheet.
 *
 * @package Vizito_Team_Business_Cards
 */

$tbcards_root = dirname( __DIR__ );
require_once $tbcards_root . '/includes/class-settings.php';
require_once $tbcards_root . '/includes/card/class-fields.php';
require_once $tbcards_root . '/includes/card/class-field-sanitizer.php';
require_once $tbcards_root . '/includes/card/class-card.php';
require_once $tbcards_root . '/includes/company/class-company-profile.php';
require_once $tbcards_root . '/includes/card/class-card-repository.php';
require_once $tbcards_root . '/includes/qr/class-qr-colors.php';
require_once $tbcards_root . '/includes/qr/class-qr-service.php';
require_once $tbcards_root . '/includes/qr/class-svg-writer.php';
require_once $tbcards_root . '/includes/qr/class-png-writer.php';
require_once $tbcards_root . '/includes/qr/class-qr-output.php';
require_once $tbcards_root . '/includes/admin/class-settings-page.php';
require_once $tbcards_root . '/includes/admin/class-qr-admin.php';

use Asymetria\TeamCards\Settings;
use Asymetria\TeamCards\Card\Card_Repository;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Qr\Qr_Output;
use Asymetria\TeamCards\Qr\Qr_Service;
use Asymetria\TeamCards\Admin\Qr_Admin;

if ( ! function_exists( 'tbcards_test_qr_admin' ) ) {
	/**
	 * The screens over fresh services, recording what they send.
	 *
	 * @param array $sent Receives array( headers, body ) per response.
	 * @return Qr_Admin
	 */
	function tbcards_test_qr_admin( array &$sent ): Qr_Admin {
		$settings = new Settings();
		$company  = new Company_Profile( $settings );

		return new Qr_Admin(
			new Card_Repository( $settings, $company ),
			new Qr_Output( $settings, $company, new Qr_Service() ),
			static function ( array $headers, string $body ) use ( &$sent ) {
				$sent[] = array( $headers, $body );
			}
		);
	}

	/**
	 * Whether a callable ends in wp_die().
	 *
	 * @param callable $fn Callable.
	 * @return bool
	 */
	function tbcards_test_dies( callable $fn ): bool {
		try {
			$fn();
		} catch ( Tbcards_Died $e ) {
			return true;
		}
		return false;
	}
}

describe(
	'qr admin',
	function () {

		before_each(
			function () {
				$_GET = array();
				$_POST = array();
				$_REQUEST = array();

				foreach ( array( 5 => array( 'jan', 'Jan', 'Kowalski' ), 6 => array( 'ola', 'Ola', '<b>Nowak</b>' ), 7 => array( 'ex', 'Ex', 'Employee' ) ) as $id => $row ) {
					$GLOBALS['tbcards_stub']['posts_by_id'][ $id ] = (object) array(
						'ID'          => $id,
						'post_type'   => 'tbcards_card',
						'post_name'   => $row[0],
						'post_status' => 'publish',
					);
					$GLOBALS['tbcards_stub']['post_meta'][ $id ]   = array(
						'_tbcards_first_name' => $row[1],
						'_tbcards_last_name'  => $row[2],
					);
					$GLOBALS['tbcards_stub']['permalinks'][ $id ]  = 'https://example.test/card/' . $row[0] . '/';
					$GLOBALS['tbcards_stub']['posts'][]            = $GLOBALS['tbcards_stub']['posts_by_id'][ $id ];
				}

				$GLOBALS['tbcards_stub']['post_meta'][7]['_tbcards_inactive'] = '1';
				$GLOBALS['tbcards_stub']['capabilities']['edit_others_posts']    = true;
				$GLOBALS['tbcards_stub']['capabilities']['edit_post']     = array( 5, 6, 7 );
			}
		);

		it(
			'downloads one SVG with a named attachment',
			function () {
				$sent = array();
				$_GET = array(
					'card'     => '5',
					'format'   => 'svg',
					'_wpnonce' => 'nonce:tbcards_qr_5',
				);

				tbcards_test_qr_admin( $sent )->download();

				assert_same( 'Content-Type: image/svg+xml', $sent[0][0][0] );
				assert_same( 'Content-Disposition: attachment; filename="jan-qr.svg"', $sent[0][0][1] );
				assert_contains( '<svg', $sent[0][1] );
			}
		);

		it(
			'refuses a download with another card\'s nonce or without the capability',
			function () {
				$sent = array();
				$_GET = array(
					'card'     => '5',
					'_wpnonce' => 'nonce:tbcards_qr_6',
				);

				assert_true( tbcards_test_dies( array( tbcards_test_qr_admin( $sent ), 'download' ) ) );

				$_GET['_wpnonce']                                 = 'nonce:tbcards_qr_5';
				$GLOBALS['tbcards_stub']['capabilities']['edit_post'] = array( 6 );
				assert_true( tbcards_test_dies( array( tbcards_test_qr_admin( $sent ), 'download' ) ) );
				assert_same( array(), $sent );
			}
		);

		it(
			'refuses an export without the nonce',
			function () {
				$sent  = array();
				$_POST = array( 'cards' => array( '5' ) );

				assert_true( tbcards_test_dies( array( tbcards_test_qr_admin( $sent ), 'export' ) ) );
			}
		);

		it(
			'prints a test sheet of the chosen active cards, escaped',
			function () {
				$sent                 = array();
				$_POST                = array(
					'cards'  => array( '5', '6', '7' ),
					'tbcards_do' => 'sheet',
				);
				$_REQUEST['_wpnonce'] = 'nonce:tbcards_qr_export';

				tbcards_test_qr_admin( $sent )->export();
				$html = $sent[0][1];

				assert_same( 2, substr_count( $html, '<figure>' ) );
				assert_contains( '&lt;b&gt;Nowak&lt;/b&gt;', $html );
				assert_false( str_contains( $html, 'Employee' ) );
			}
		);

		it(
			'zips an SVG, and a PNG where GD exists, per active card',
			function () {
				if ( ! class_exists( 'ZipArchive' ) ) {
					return; // The zip run covers this; see tests/test-qr-png.php.
				}

				$sent                 = array();
				$_POST                = array( 'cards' => array( '5', '7' ) );
				$_REQUEST['_wpnonce'] = 'nonce:tbcards_qr_export';

				tbcards_test_qr_admin( $sent )->export();

				$file = tempnam( sys_get_temp_dir(), 'tbc' );
				file_put_contents( $file, $sent[0][1] );
				$zip   = new ZipArchive();
				$zip->open( $file );
				$names = array();
				for ( $i = 0; $i < $zip->numFiles; $i++ ) {
					$names[] = $zip->getNameIndex( $i );
				}
				$zip->close();
				unlink( $file );

				$expected = function_exists( 'imagepng' ) ? array( 'jan/jan.svg', 'jan/jan.png' ) : array( 'jan/jan.svg' );
				assert_same( $expected, $names );
				assert_same( 'Content-Type: application/zip', $sent[0][0][0] );
			}
		);

		it(
			'says why when the server cannot build a ZIP',
			function () {
				if ( class_exists( 'ZipArchive' ) ) {
					return; // The default run covers this.
				}

				$sent                 = array();
				$_POST                = array( 'cards' => array( '5' ) );
				$_REQUEST['_wpnonce'] = 'nonce:tbcards_qr_export';

				assert_true( tbcards_test_dies( array( tbcards_test_qr_admin( $sent ), 'export' ) ) );
				assert_contains( 'zip extension', $GLOBALS['tbcards_stub']['died'][0]['message'] );
			}
		);

		it(
			'asks to publish before showing a code',
			function () {
				$sent = array();
				ob_start();
				tbcards_test_qr_admin( $sent )->render_meta_box(
					(object) array(
						'ID'          => 5,
						'post_status' => 'draft',
					)
				);
				$html = (string) ob_get_clean();

				assert_contains( 'Publish the card', $html );
				assert_false( str_contains( $html, '<svg' ) );
			}
		);

		it(
			'previews a published card with signed download links',
			function () {
				$sent = array();
				ob_start();
				tbcards_test_qr_admin( $sent )->render_meta_box(
					(object) array(
						'ID'          => 5,
						'post_status' => 'publish',
					)
				);
				$html = (string) ob_get_clean();

				assert_contains( '<svg', $html );
				assert_contains( '_wpnonce=nonce%3Atbcards_qr_5', $html );
			}
		);
	}
);
