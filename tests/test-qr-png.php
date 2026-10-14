<?php
/**
 * Pins colour rules and the PNG writer.
 *
 * The PNG cases need GD. The default run (php -n) has no extensions, which is
 * how the "no GD" path gets tested; the GD path runs with:
 *
 *   php -n -d extension_dir=/path/to/php/ext -d extension=gd -d extension=zip tests/run.php
 *
 * Both runs must pass before a commit that touches QR output.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/qr/class-qr-colors.php';
require_once dirname( __DIR__ ) . '/includes/qr/class-svg-writer.php';
require_once dirname( __DIR__ ) . '/includes/qr/class-png-writer.php';

use Asymetria\TeamCards\Qr\Png_Writer;
use Asymetria\TeamCards\Qr\Qr_Colors;

describe(
	'qr colours',
	function () {

		it(
			'computes WCAG contrast',
			function () {
				assert_close( 21.0, Qr_Colors::contrast( '#000000', '#ffffff' ), 0.01 );
				assert_close( 4.48, Qr_Colors::contrast( '#777777', '#ffffff' ), 0.01 );
				assert_close( 1.0, Qr_Colors::contrast( '#123456', '#123456' ), 0.001 );
			}
		);

		it(
			'accepts dark on light with enough contrast',
			function () {
				assert_same( null, Qr_Colors::validate( '#000000', '#ffffff' ) );
				assert_same( null, Qr_Colors::validate( '#012169', '#ffffff' ) );
			}
		);

		it(
			'refuses low contrast, inverted colours and malformed values',
			function () {
				assert_true( is_string( Qr_Colors::validate( '#777777', '#ffffff' ) ) );
				assert_true( is_string( Qr_Colors::validate( '#ffffff', '#000000' ) ) );
				assert_true( is_string( Qr_Colors::validate( 'black', '#ffffff' ) ) );
			}
		);
	}
);

describe(
	'png writer',
	function () {

		it(
			'reports whether GD is there, and writes nothing without it',
			function () {
				$writer = new Png_Writer();
				$gd     = function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' );

				assert_same( $gd, $writer->is_available() );

				if ( ! $gd ) {
					assert_same( null, $writer->write( array( array( true ) ) ) );
				}
			}
		);

		it(
			'draws modules at a whole-pixel size with the quiet zone (GD only)',
			function () {
				$writer = new Png_Writer();

				if ( ! $writer->is_available() ) {
					return; // Covered by the GD run; see the file header.
				}

				$matrix = array_fill( 0, 21, array_fill( 0, 21, false ) );
				$matrix[0][0] = true;

				$png   = $writer->write(
					$matrix,
					array(
						'size'  => 290,
						'dark'  => '#012169',
						'light' => '#ffffff',
					)
				);
				$image = imagecreatefromstring( $png );

				// 21 + 8 = 29 modules; 290 / 29 = 10 px each.
				assert_same( 290, imagesx( $image ) );
				assert_same( 290, imagesy( $image ) );

				$at = static function ( $x, $y ) use ( $image ) {
					return sprintf( '#%06x', imagecolorat( $image, $x, $y ) & 0xFFFFFF );
				};

				assert_same( '#ffffff', $at( 5, 5 ) );    // Quiet zone.
				assert_same( '#012169', $at( 45, 45 ) );  // Module (0,0) starts at 40 px.
				assert_same( '#ffffff', $at( 55, 45 ) );  // Module (0,1) is light.
			}
		);

		it(
			'puts the logo on a light pad in the middle (GD only)',
			function () {
				$writer = new Png_Writer();

				if ( ! $writer->is_available() ) {
					return;
				}

				$logo_file = tempnam( sys_get_temp_dir(), 'tbc' );
				$logo      = imagecreatetruecolor( 20, 20 );
				imagefill( $logo, 0, 0, imagecolorallocate( $logo, 255, 0, 0 ) );
				imagepng( $logo, $logo_file );

				$matrix = array_fill( 0, 41, array_fill( 0, 41, true ) );
				$image  = imagecreatefromstring(
					$writer->write(
						$matrix,
						array(
							'size'      => 490,
							'logo_file' => $logo_file,
						)
					)
				);
				unlink( $logo_file );

				// 49 modules of 10 px; the centre of the symbol is at 245 px.
				assert_same( 0xFF0000, imagecolorat( $image, 245, 245 ) & 0xFFFFFF );
				assert_same( 0x000000, imagecolorat( $image, 45, 45 ) & 0xFFFFFF );
			}
		);

		it(
			'ignores a logo file that is not an image (GD only)',
			function () {
				$writer = new Png_Writer();

				if ( ! $writer->is_available() ) {
					return;
				}

				$file = tempnam( sys_get_temp_dir(), 'tbc' );
				file_put_contents( $file, 'not an image' );

				$png = $writer->write(
					array_fill( 0, 21, array_fill( 0, 21, true ) ),
					array( 'logo_file' => $file )
				);
				unlink( $file );

				assert_true( is_string( $png ) && str_starts_with( $png, "\x89PNG" ) );
			}
		);
	}
);
