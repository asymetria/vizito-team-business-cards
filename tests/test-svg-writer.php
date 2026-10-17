<?php
/**
 * Pins the SVG output: geometry reproduces the matrix, quiet zone, colours, logo.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/qr/class-svg-writer.php';

use Asymetria\TeamCards\Qr\Svg_Writer;

if ( ! function_exists( 'tbcards_test_grid_from_path' ) ) {
	/**
	 * Rebuilds the dark modules from the path: each run is "M{x} {y}h{n}v1h-{n}z".
	 *
	 * @param string $svg   SVG markup.
	 * @param int    $count Modules per side.
	 * @param int    $quiet Quiet zone in modules.
	 * @return bool[][]
	 */
	function tbcards_test_grid_from_path( string $svg, int $count, int $quiet ): array {
		$grid = array_fill( 0, $count, array_fill( 0, $count, false ) );

		preg_match( '/<path[^>]* d="([^"]*)"/', $svg, $m );
		preg_match_all( '/M(\d+) (\d+)h(\d+)v1h-\3z/', $m[1], $runs, PREG_SET_ORDER );

		foreach ( $runs as $run ) {
			for ( $i = 0; $i < (int) $run[3]; $i++ ) {
				$grid[ (int) $run[2] - $quiet ][ (int) $run[1] - $quiet + $i ] = true;
			}
		}

		return $grid;
	}

	/**
	 * A small asymmetric matrix.
	 *
	 * @return bool[][]
	 */
	function tbcards_test_small_matrix(): array {
		$rows = array( '11100', '10001', '00111', '01010', '11011' );

		return array_map(
			static function ( $row ) {
				return array_map( 'boolval', str_split( $row ) );
			},
			$rows
		);
	}
}

describe(
	'svg writer',
	function () {

		it(
			'draws exactly the dark modules, offset by the quiet zone',
			function () {
				$matrix = tbcards_test_small_matrix();
				$svg    = ( new Svg_Writer() )->write( $matrix );

				assert_same( $matrix, tbcards_test_grid_from_path( $svg, 5, 4 ) );
			}
		);

		it(
			'sizes the view box to the symbol plus four modules on each side',
			function () {
				$svg = ( new Svg_Writer() )->write( tbcards_test_small_matrix() );

				assert_contains( 'viewBox="0 0 13 13"', $svg );
				assert_contains( 'shape-rendering="crispEdges"', $svg );
			}
		);

		it(
			'uses the given colours and ignores invalid ones',
			function () {
				$writer = new Svg_Writer();

				$custom  = $writer->write(
					tbcards_test_small_matrix(),
					array(
						'dark'  => '#012169',
						'light' => '#fffff0',
					)
				);
				$invalid = $writer->write(
					tbcards_test_small_matrix(),
					array( 'dark' => '"><script>' )
				);

				assert_contains( 'fill="#012169"', $custom );
				assert_contains( 'fill="#fffff0"', $custom );
				assert_contains( 'fill="#000000"', $invalid );
				assert_false( str_contains( $invalid, '<script>' ) );
			}
		);

		it(
			'sets a physical size only when asked',
			function () {
				$writer = new Svg_Writer();

				preg_match( '/<svg[^>]*>/', $writer->write( tbcards_test_small_matrix() ), $bare );
				preg_match( '/<svg[^>]*>/', $writer->write( tbcards_test_small_matrix(), array( 'size' => 300 ) ), $sized );

				assert_false( str_contains( $bare[0], 'width=' ) );
				assert_contains( 'width="300" height="300"', $sized[0] );
			}
		);

		it(
			'centres a logo on a light pad covering at most a fifth of the symbol',
			function () {
				$matrix = array_fill( 0, 41, array_fill( 0, 41, true ) );
				$svg    = ( new Svg_Writer() )->write( $matrix, array( 'logo' => 'data:image/png;base64,AAAA' ) );

				preg_match( '/<rect class="tbcards-qr-pad" x="([\d.]+)" y="([\d.]+)" width="([\d.]+)"/', $svg, $pad );

				assert_contains( '<image href="data:image/png;base64,AAAA" xlink:href="data:image/png;base64,AAAA"', $svg );
				assert_contains( 'xmlns:xlink="http://www.w3.org/1999/xlink"', $svg );
				assert_true( (float) $pad[3] * (float) $pad[3] <= 0.2 * 41 * 41, 'pad area' );
				assert_same( 4 + ( 41 - (float) $pad[3] ) / 2, (float) $pad[1], 'centred' );
			}
		);

		it(
			'embeds no image without a logo and refuses anything but a data URI',
			function () {
				$writer = new Svg_Writer();

				assert_false( str_contains( $writer->write( tbcards_test_small_matrix() ), '<image' ) );
				assert_false( str_contains( $writer->write( tbcards_test_small_matrix(), array( 'logo' => 'https://evil.example/x.png' ) ), '<image' ) );
			}
		);
	}
);
