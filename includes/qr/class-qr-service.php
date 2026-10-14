<?php
/**
 * QR symbol generation.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Qr;

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Vendor\Qr\QRCode;
use Asymetria\TeamCards\Vendor\Qr\QRRSBlock;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Qr_Service' ) ) {

	/**
	 * Turns data into a module matrix through the vendored library, without
	 * ever touching its weak spots (lib/qrcode/README.md):
	 *
	 * - the version is chosen here from the RS block table, which covers all
	 *   forty versions, never by getMinimumQRCode(), whose table stops at ten;
	 * - data is always encoded in byte mode, so UTF-8 is never taken for Kanji;
	 * - data that fits no version is refused here, because the library's own
	 *   overflow path is a fatal trigger_error( E_USER_ERROR ).
	 */
	final class Qr_Service {

		public const LEVELS      = array( 'L', 'M', 'Q', 'H' );
		public const MAX_VERSION = 40;

		/**
		 * Loads the vendored library, only when a code is actually built.
		 *
		 * @return void
		 */
		private static function load(): void {
			require_once TBCARDS_DIR . 'lib/qrcode/qrcode.php';
		}

		/**
		 * The module matrix, true for dark.
		 *
		 * @param string $data  Bytes to encode.
		 * @param string $level L, M, Q or H.
		 * @return bool[][]|null Null when the data is empty, too long or the level unknown.
		 */
		public function matrix( string $data, string $level = 'M' ): ?array {
			$version = '' === $data ? null : $this->version_for( strlen( $data ), $level );

			if ( null === $version ) {
				return null;
			}

			self::load();

			$qr = new QRCode();
			$qr->setTypeNumber( $version );
			$qr->setErrorCorrectLevel( self::level_constant( $level ) );
			$qr->addData( $data, \Asymetria\TeamCards\Vendor\Qr\QR_MODE_8BIT_BYTE );
			$qr->make();

			$count  = $qr->getModuleCount();
			$matrix = array();

			for ( $row = 0; $row < $count; $row++ ) {
				for ( $col = 0; $col < $count; $col++ ) {
					$matrix[ $row ][ $col ] = (bool) $qr->isDark( $row, $col );
				}
			}

			return $matrix;
		}

		/**
		 * The smallest version whose data capacity holds this many bytes.
		 *
		 * @param int    $bytes Data length in bytes.
		 * @param string $level L, M, Q or H.
		 * @return int|null
		 */
		public function version_for( int $bytes, string $level ): ?int {
			if ( ! in_array( $level, self::LEVELS, true ) ) {
				return null;
			}

			self::load();

			$ecl = self::level_constant( $level );

			for ( $version = 1; $version <= self::MAX_VERSION; $version++ ) {
				$capacity = 0;

				foreach ( QRRSBlock::getRSBlocks( $version, $ecl ) as $block ) {
					$capacity += $block->getDataCount();
				}

				// Mode indicator, character count, data.
				$needed = 4 + ( $version < 10 ? 8 : 16 ) + 8 * $bytes;

				if ( $needed <= $capacity * 8 ) {
					return $version;
				}
			}

			return null;
		}

		/**
		 * The address a printed code carries: the card plus src=qr, so a scan
		 * can be told from a visit through a shared link.
		 *
		 * @param Card $card Card.
		 * @return string
		 */
		public function card_url( Card $card ): string {
			return add_query_arg( 'src', 'qr', $card->url() );
		}

		/**
		 * The library constant for a level letter.
		 *
		 * @param string $level L, M, Q or H.
		 * @return int
		 */
		private static function level_constant( string $level ): int {
			return (int) constant( 'Asymetria\\TeamCards\\Vendor\\Qr\\QR_ERROR_CORRECT_LEVEL_' . $level );
		}
	}
}
