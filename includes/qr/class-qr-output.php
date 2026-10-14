<?php
/**
 * QR files for a card.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Qr;

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Qr_Output' ) ) {

	/**
	 * Applies the settings (colours, logo) to a card's QR code. Level M by
	 * default, H whenever the logo covers the middle.
	 */
	final class Qr_Output {

		public const LOGO_TYPES = array(
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
		);

		/**
		 * Plugin settings.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Company profile, for the logo.
		 *
		 * @var Company_Profile
		 */
		private Company_Profile $company;

		/**
		 * Symbol generation.
		 *
		 * @var Qr_Service
		 */
		private Qr_Service $service;

		/**
		 * Constructor.
		 *
		 * @param Settings        $settings Plugin settings.
		 * @param Company_Profile $company  Company profile.
		 * @param Qr_Service      $service  Symbol generation.
		 */
		public function __construct( Settings $settings, Company_Profile $company, Qr_Service $service ) {
			$this->settings = $settings;
			$this->company  = $company;
			$this->service  = $service;
		}

		/**
		 * Whether a logo can go in the middle: switched on and a usable file.
		 *
		 * @return bool
		 */
		public function logo_available(): bool {
			return (bool) $this->settings->get( 'qr_logo' ) && '' !== $this->logo_file();
		}

		/**
		 * The SVG document for a card.
		 *
		 * @param Card $card Card.
		 * @param bool $logo Put the logo in the middle, when available.
		 * @return string "" when the address cannot be encoded.
		 */
		public function svg( Card $card, bool $logo ): string {
			$logo   = $logo && $this->logo_available();
			$matrix = $this->service->matrix( $this->service->card_url( $card ), $logo ? 'H' : 'M' );

			if ( null === $matrix ) {
				return '';
			}

			return ( new Svg_Writer() )->write(
				$matrix,
				array(
					'dark'  => (string) $this->settings->get( 'qr_dark' ),
					'light' => (string) $this->settings->get( 'qr_light' ),
					'logo'  => $logo ? $this->logo_data_uri() : '',
				)
			);
		}

		/**
		 * The PNG bytes for a card.
		 *
		 * @param Card $card Card.
		 * @param bool $logo Put the logo in the middle, when available.
		 * @return string|null Null without GD or when the address cannot be encoded.
		 */
		public function png( Card $card, bool $logo ): ?string {
			$writer = new Png_Writer();

			if ( ! $writer->is_available() ) {
				return null;
			}

			$logo   = $logo && $this->logo_available();
			$matrix = $this->service->matrix( $this->service->card_url( $card ), $logo ? 'H' : 'M' );

			if ( null === $matrix ) {
				return null;
			}

			return $writer->write(
				$matrix,
				array(
					'dark'      => (string) $this->settings->get( 'qr_dark' ),
					'light'     => (string) $this->settings->get( 'qr_light' ),
					'logo_file' => $logo ? $this->logo_file() : '',
				)
			);
		}

		/**
		 * Whether PNG output is possible here.
		 *
		 * @return bool
		 */
		public function png_available(): bool {
			return ( new Png_Writer() )->is_available();
		}

		/**
		 * The logo file, "" when there is none or its type is not supported.
		 *
		 * @return string
		 */
		private function logo_file(): string {
			$id   = $this->company->logo_id();
			$file = $id ? (string) get_attached_file( $id ) : '';

			if ( '' === $file || ! is_readable( $file ) ) {
				return '';
			}

			return isset( self::LOGO_TYPES[ strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ] ) ? $file : '';
		}

		/**
		 * The logo as a data: URI for embedding in SVG.
		 *
		 * @return string
		 */
		private function logo_data_uri(): string {
			$file = $this->logo_file();

			if ( '' === $file ) {
				return '';
			}

			$bytes = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local attachment file.

			if ( false === $bytes ) {
				return '';
			}

			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- embedding an image in SVG.
			return 'data:' . self::LOGO_TYPES[ strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ] . ';base64,' . base64_encode( $bytes );
		}
	}
}
