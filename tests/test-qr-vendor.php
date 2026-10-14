<?php
/**
 * Pins the vendored QR library: nothing global, and module-for-module the same
 * output as the untouched upstream file for every level and a spread of versions.
 *
 * Two upstream facts shape these cases. getMinimumQRCode() reads a capacity
 * table that stops at version 10, so beyond it upstream silently builds a wrong
 * symbol (Qr_Service never calls it). And every upstream error path is
 * trigger_error( E_USER_ERROR ), a fatal that cannot be caught, so no case here
 * may hand either copy data that does not fit.
 *
 * @package Vizito_Team_Business_Cards
 */

/*
 * What the file declares is measured in a fresh PHP process, because another
 * test file may already have loaded the library into this one.
 */
$tbcards_probe = <<<'PROBE'
define( 'ABSPATH', __DIR__ );
$classes   = get_declared_classes();
$constants = array_keys( get_defined_constants( true )['user'] ?? array() );
require $argv[1];
echo json_encode(
	array(
		'classes'   => array_values( array_diff( get_declared_classes(), $classes ) ),
		'constants' => array_values( array_diff( array_keys( get_defined_constants( true )['user'] ?? array() ), $constants, array( 'ABSPATH' ) ) ),
	)
);
PROBE;

$tbcards_probe_file = tempnam( sys_get_temp_dir(), 'tbc' );
file_put_contents( $tbcards_probe_file, "<?php
" . $tbcards_probe );
$tbcards_declared = json_decode(
	(string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( $tbcards_probe_file ) . ' ' . escapeshellarg( dirname( __DIR__ ) . '/lib/qrcode/qrcode.php' ) ),
	true
);
unlink( $tbcards_probe_file );
$tbcards_new_classes   = $tbcards_declared['classes'] ?? array();
$tbcards_new_constants = $tbcards_declared['constants'] ?? array();

require_once dirname( __DIR__ ) . '/lib/qrcode/qrcode.php';

use Asymetria\TeamCards\Vendor\Qr\QRCode as Vendored;

if ( ! function_exists( 'tbcards_test_matrix' ) ) {
	/**
	 * The module matrix of a QR code object as rows of 0/1.
	 *
	 * @param object $qr QRCode (either copy).
	 * @return string[]
	 */
	function tbcards_test_matrix( $qr ): array {
		$rows = array();
		$n    = $qr->getModuleCount();

		for ( $r = 0; $r < $n; $r++ ) {
			$row = '';
			for ( $c = 0; $c < $n; $c++ ) {
				$row .= $qr->isDark( $r, $c ) ? '1' : '0';
			}
			$rows[] = $row;
		}

		return $rows;
	}
}

describe(
	'vendored qr library',
	function () use ( $tbcards_new_classes, $tbcards_new_constants ) {

		it(
			'declares its classes inside our namespace only',
			function () use ( $tbcards_new_classes ) {
				assert_true( count( $tbcards_new_classes ) >= 8 );

				foreach ( $tbcards_new_classes as $class ) {
					assert_true( str_starts_with( $class, 'Asymetria\\TeamCards\\Vendor\\Qr\\' ), $class );
				}
			}
		);

		it(
			'defines its constants inside our namespace only',
			function () use ( $tbcards_new_constants ) {
				assert_true( count( $tbcards_new_constants ) >= 20 );

				foreach ( $tbcards_new_constants as $constant ) {
					assert_true( str_starts_with( $constant, 'Asymetria\\TeamCards\\Vendor\\Qr\\' ), $constant );
				}
			}
		);

		it(
			'produces the known 41-module symbol for a production card address at level H',
			function () {
				$qr = Vendored::getMinimumQRCode( 'https://acme-firma.example/wizytowka/jozef-wezycki?src=qr', \Asymetria\TeamCards\Vendor\Qr\QR_ERROR_CORRECT_LEVEL_H );

				assert_same( 41, $qr->getModuleCount() );
			}
		);

		it(
			'matches the untouched upstream file module for module, versions 1 to 15',
			function () {
				require_once dirname( __DIR__ ) . '/tools/upstream/qrcode.php';

				$ns = 'Asymetria\\TeamCards\\Vendor\\Qr\\';

				// Seven bytes fit version 1 at every level (H holds 7 in byte mode).
				$cases = array(
					array( 1, 'card/ab' ),
					array( 2, 'card/ab' ),
					array( 5, 'https://example.test/card/jan/?src=qr' ),
					array( 10, 'https://example.test/card/jan/?src=qr' ),
					array( 11, 'https://example.test/card/jan/?src=qr' ),
					array( 15, 'https://example.test/card/jan/?src=qr' ),
				);

				// Versions above 10 are slow to build twice (mask scoring grows with
				// the square of the size); one level is enough to show the vendored
				// copy is right past version 10, where upstream's own table ends.
				$slow = array( 11, 15 );

				foreach ( $cases as $case ) {
					list( $type, $input ) = $case;

					foreach ( in_array( $type, $slow, true ) ? array( 'M' ) : array( 'L', 'M', 'Q', 'H' ) as $level ) {
						$ours = new Vendored();
						$ours->setTypeNumber( $type );
						$ours->setErrorCorrectLevel( constant( $ns . 'QR_ERROR_CORRECT_LEVEL_' . $level ) );
						$ours->addData( $input, constant( $ns . 'QR_MODE_8BIT_BYTE' ) );
						$ours->make();

						$theirs = new \QRCode();
						$theirs->setTypeNumber( $type );
						$theirs->setErrorCorrectLevel( constant( 'QR_ERROR_CORRECT_LEVEL_' . $level ) );
						$theirs->addData( $input, QR_MODE_8BIT_BYTE );
						$theirs->make();

						assert_same( 17 + 4 * $type, $ours->getModuleCount(), "v$type size" );
						assert_same( tbcards_test_matrix( $theirs ), tbcards_test_matrix( $ours ), "v$type $level: $input" );
					}
				}
			}
		);

		it(
			'throws a catchable exception where upstream would stop the whole request',
			function () {
				$qr = new Vendored();
				$qr->setTypeNumber( 1 );
				$qr->setErrorCorrectLevel( \Asymetria\TeamCards\Vendor\Qr\QR_ERROR_CORRECT_LEVEL_H );
				$qr->addData( str_repeat( 'x', 100 ), \Asymetria\TeamCards\Vendor\Qr\QR_MODE_8BIT_BYTE );

				$caught = '';

				try {
					$qr->make();
				} catch ( InvalidArgumentException $e ) {
					$caught = $e->getMessage();
				}

				assert_same( 'qrcode-generator: invalid input', $caught );
			}
		);
	}
);
