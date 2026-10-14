<?php
/**
 * QR as SVG.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Qr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Svg_Writer' ) ) {

	/**
	 * Writes a module matrix as one SVG path of horizontal runs, which keeps
	 * the file small and prints without hairline gaps between modules.
	 * Coordinates are in modules; the viewBox includes a 4-module quiet zone.
	 */
	final class Svg_Writer {

		public const QUIET = 4;

		/**
		 * Largest share of the symbol area the logo pad may cover. Level H
		 * restores up to 30 % damage; a fifth leaves room for dirt and wear.
		 */
		public const LOGO_AREA = 0.2;

		/**
		 * The SVG document.
		 *
		 * @param bool[][] $matrix Module matrix, true for dark.
		 * @param array    $opts {
		 *     @type string $dark  Dark colour, #rrggbb. Default #000000.
		 *     @type string $light Light colour, #rrggbb. Default #ffffff.
		 *     @type int    $size  Width and height in px; omitted when 0.
		 *     @type string $logo  A data: URI of an image to centre, or "".
		 * }
		 * @return string
		 */
		public function write( array $matrix, array $opts = array() ): string {
			$count = count( $matrix );
			$side  = $count + 2 * self::QUIET;
			$dark  = self::colour( $opts['dark'] ?? '', '#000000' );
			$light = self::colour( $opts['light'] ?? '', '#ffffff' );
			$size  = (int) ( $opts['size'] ?? 0 );
			$dims  = $size > 0 ? ' width="' . $size . '" height="' . $size . '"' : '';

			$svg  = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 ' . $side . ' ' . $side . '"' . $dims . ' shape-rendering="crispEdges">';
			$svg .= '<rect width="' . $side . '" height="' . $side . '" fill="' . $light . '"/>';
			$svg .= '<path fill="' . $dark . '" d="' . $this->path( $matrix ) . '"/>';
			$svg .= $this->logo( (string) ( $opts['logo'] ?? '' ), $count, $light );

			return $svg . '</svg>';
		}

		/**
		 * Path data: one closed rectangle per horizontal run of dark modules.
		 *
		 * @param bool[][] $matrix Module matrix.
		 * @return string
		 */
		private function path( array $matrix ): string {
			$d = '';

			foreach ( $matrix as $y => $row ) {
				$x   = 0;
				$len = count( $row );

				while ( $x < $len ) {
					if ( ! $row[ $x ] ) {
						$x++;
						continue;
					}

					$start = $x;

					while ( $x < $len && $row[ $x ] ) {
						$x++;
					}

					$run = $x - $start;
					$d  .= 'M' . ( $start + self::QUIET ) . ' ' . ( $y + self::QUIET ) . 'h' . $run . 'v1h-' . $run . 'z';
				}
			}

			return $d;
		}

		/**
		 * A light pad with the logo on it, centred, whole modules wide, with
		 * the same parity as the symbol so it sits on the module grid.
		 *
		 * @param string $uri   data: URI of the logo, or "".
		 * @param int    $count Modules per side.
		 * @param string $light Pad colour.
		 * @return string
		 */
		private function logo( string $uri, int $count, string $light ): string {
			if ( ! preg_match( '#^data:image/(png|jpeg|webp|svg\+xml);base64,[A-Za-z0-9+/=]+$#', $uri ) ) {
				return '';
			}

			$pad   = self::pad_modules( $count );
			$at    = self::QUIET + ( $count - $pad ) / 2;
			$inset = 1;

			return '<rect class="tbc-qr-pad" x="' . $at . '" y="' . $at . '" width="' . $pad . '" height="' . $pad . '" rx="1" fill="' . $light . '"/>'
				. '<image href="' . $uri . '" xlink:href="' . $uri . '" x="' . ( $at + $inset ) . '" y="' . ( $at + $inset ) . '" width="' . ( $pad - 2 * $inset ) . '" height="' . ( $pad - 2 * $inset ) . '" preserveAspectRatio="xMidYMid meet"/>';
		}

		/**
		 * Side of the logo pad in modules: at most LOGO_AREA of the symbol,
		 * with the symbol's parity so it is centred on whole modules.
		 *
		 * @param int $count Modules per side.
		 * @return int
		 */
		public static function pad_modules( int $count ): int {
			$pad = (int) floor( $count * sqrt( self::LOGO_AREA ) );

			if ( ( $pad % 2 ) !== ( $count % 2 ) ) {
				$pad--;
			}

			return $pad;
		}

		/**
		 * A #rrggbb colour or the fallback.
		 *
		 * @param string $value    Candidate.
		 * @param string $fallback Default.
		 * @return string
		 */
		private static function colour( string $value, string $fallback ): string {
			return preg_match( '/^#[0-9a-fA-F]{6}$/', $value ) ? strtolower( $value ) : $fallback;
		}
	}
}
