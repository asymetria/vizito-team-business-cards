<?php
/**
 * QR as PNG.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Qr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Png_Writer' ) ) {

	/**
	 * Writes a module matrix as PNG through GD, at a whole number of pixels
	 * per module so no module is blurred by scaling. Without GD the plugin
	 * offers SVG only.
	 *
	 * imagedestroy() is not called: it has done nothing since PHP 8.0 and is
	 * deprecated in 8.5; GD images are freed with their last reference.
	 */
	final class Png_Writer {

		public const DEFAULT_SIZE = 2000;

		/**
		 * Whether GD can write PNG here.
		 *
		 * @return bool
		 */
		public function is_available(): bool {
			return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagepng' );
		}

		/**
		 * PNG bytes, or null without GD.
		 *
		 * @param bool[][] $matrix Module matrix, true for dark.
		 * @param array    $opts {
		 *     @type int    $size      Target width in px; the result rounds down to whole modules.
		 *     @type string $dark      Dark colour, #rrggbb.
		 *     @type string $light     Light colour, #rrggbb.
		 *     @type string $logo_file Path to a JPEG, PNG or WebP to centre, or "".
		 * }
		 * @return string|null
		 */
		public function write( array $matrix, array $opts = array() ): ?string {
			if ( ! $this->is_available() || array() === $matrix ) {
				return null;
			}

			$count  = count( $matrix );
			$side   = $count + 2 * Svg_Writer::QUIET;
			$module = max( 1, (int) floor( (int) ( $opts['size'] ?? self::DEFAULT_SIZE ) / $side ) );
			$px     = $module * $side;
			$image  = imagecreatetruecolor( $px, $px );
			$dark   = $this->allocate( $image, (string) ( $opts['dark'] ?? '' ), '#000000' );
			$light  = $this->allocate( $image, (string) ( $opts['light'] ?? '' ), '#ffffff' );

			imagefilledrectangle( $image, 0, 0, $px - 1, $px - 1, $light );

			foreach ( $matrix as $y => $row ) {
				foreach ( $row as $x => $on ) {
					if ( $on ) {
						$left = ( $x + Svg_Writer::QUIET ) * $module;
						$top  = ( $y + Svg_Writer::QUIET ) * $module;
						imagefilledrectangle( $image, $left, $top, $left + $module - 1, $top + $module - 1, $dark );
					}
				}
			}

			$this->logo( $image, (string) ( $opts['logo_file'] ?? '' ), $count, $module, $light );

			ob_start();
			imagepng( $image );

			return (string) ob_get_clean();
		}

		/**
		 * Centres the logo on a light pad, same geometry as the SVG.
		 *
		 * @param \GdImage $image  Target.
		 * @param string   $file   Logo path.
		 * @param int      $count  Modules per side.
		 * @param int      $module Pixels per module.
		 * @param int      $light  Pad colour.
		 * @return void
		 */
		private function logo( $image, string $file, int $count, int $module, int $light ): void {
			if ( '' === $file || ! is_readable( $file ) ) {
				return;
			}

			$bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local attachment file.
			$logo  = false === $bytes ? false : @imagecreatefromstring( $bytes ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- a broken file only means no logo.

			if ( false === $logo ) {
				return;
			}

			$pad = Svg_Writer::pad_modules( $count );
			$at  = (int) ( ( Svg_Writer::QUIET + ( $count - $pad ) / 2 ) * $module );
			$box = $pad * $module;

			imagefilledrectangle( $image, $at, $at, $at + $box - 1, $at + $box - 1, $light );

			$inner = $box - 2 * $module;
			$w     = imagesx( $logo );
			$h     = imagesy( $logo );
			$scale = min( $inner / $w, $inner / $h );
			$dw    = max( 1, (int) round( $w * $scale ) );
			$dh    = max( 1, (int) round( $h * $scale ) );

			imagecopyresampled( $image, $logo, $at + (int) ( ( $box - $dw ) / 2 ), $at + (int) ( ( $box - $dh ) / 2 ), 0, 0, $dw, $dh, $w, $h );
		}

		/**
		 * A GD colour for #rrggbb, or for the fallback.
		 *
		 * @param \GdImage $image    Image.
		 * @param string   $hex      Colour.
		 * @param string   $fallback Default.
		 * @return int
		 */
		private function allocate( $image, string $hex, string $fallback ): int {
			$hex = preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ? $hex : $fallback;

			list( $r, $g, $b ) = Qr_Colors::rgb( $hex );

			return (int) imagecolorallocate( $image, $r, $g, $b );
		}
	}
}
