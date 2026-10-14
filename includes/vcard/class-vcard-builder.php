<?php
/**
 * vCard 3.0 files.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\VCard;

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Card\Field_Sanitizer;
use Asymetria\TeamCards\Card\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\VCard_Builder' ) ) {

	/**
	 * Version 3.0, the one iOS and Android both read reliably. Lines end in
	 * CRLF and fold at 75 octets without splitting a UTF-8 character. Empty
	 * values produce no line at all.
	 */
	final class VCard_Builder {

		public const FOLD_AT = 75;

		/**
		 * The file contents.
		 *
		 * @param Card   $card    Card, already in the language wanted.
		 * @param string $company Company name, "" for none.
		 * @param string $photo   Base64 JPEG, "" for none.
		 * @return string
		 */
		public function build( Card $card, string $company, string $photo = '' ): string {
			$lines = array(
				'BEGIN:VCARD',
				'VERSION:3.0',
				'N:' . self::text( $card->get( 'last_name' ) ) . ';' . self::text( $card->get( 'first_name' ) ) . ';;;',
				'FN:' . self::text( $card->full_name() ),
			);

			if ( '' !== $company ) {
				$lines[] = 'ORG:' . self::text( $company );
			}

			if ( $card->has( 'job_title' ) ) {
				$lines[] = 'TITLE:' . self::text( $card->get( 'job_title' ) );
			}

			$mobile   = Field_Sanitizer::dial( $card->get( 'mobile' ) );
			$landline = Field_Sanitizer::dial( $card->get( 'landline' ) );

			if ( '' !== $mobile ) {
				$lines[] = 'TEL;TYPE=CELL,VOICE:' . $mobile;
			}

			if ( '' !== $landline ) {
				$lines[] = 'TEL;TYPE=WORK,VOICE:' . $landline;
			}

			if ( $card->has( 'email' ) ) {
				$lines[] = 'EMAIL;TYPE=INTERNET,WORK:' . $card->get( 'email' );
			}

			$address = array( 'street', 'city', 'postcode', 'country' );

			if ( array_filter( array_map( array( $card, 'get' ), $address ), 'strlen' ) ) {
				// Post office box; extended; street; locality; region; postcode; country.
				$lines[] = 'ADR;TYPE=WORK:;;' . self::text( $card->get( 'street' ) ) . ';' . self::text( $card->get( 'city' ) ) . ';;'
					. self::text( $card->get( 'postcode' ) ) . ';' . self::text( $card->get( 'country' ) );
			}

			if ( $card->has( 'website' ) ) {
				$lines[] = 'URL;TYPE=WORK:' . self::uri( $card->get( 'website' ) );
			}

			// iOS reads X-SOCIALPROFILE; Android keeps the plain URL.
			foreach ( Fields::social() as $network ) {
				if ( $card->has( $network ) ) {
					$lines[] = 'X-SOCIALPROFILE;TYPE=' . $network . ':' . self::uri( $card->get( $network ) );
					$lines[] = 'URL;TYPE=' . $network . ':' . self::uri( $card->get( $network ) );
				}
			}

			if ( '' !== $card->url() ) {
				$lines[] = 'URL:' . self::uri( $card->url() );
			}

			if ( '' !== $photo ) {
				$lines[] = 'PHOTO;ENCODING=b;TYPE=JPEG:' . $photo;
			}

			$lines[] = 'END:VCARD';

			return implode( "\r\n", array_map( array( __CLASS__, 'fold' ), $lines ) ) . "\r\n";
		}

		/**
		 * The download file name: the person's name in plain ASCII.
		 *
		 * @param Card $card Card.
		 * @return string
		 */
		public static function filename( Card $card ): string {
			$name = function_exists( 'remove_accents' ) ? remove_accents( $card->full_name() ) : $card->full_name();
			$name = strtolower( trim( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $name ), '-' ) );

			return ( '' === $name ? 'contact' : $name ) . '.vcf';
		}

		/**
		 * A text value, escaped per RFC 2426.
		 *
		 * @param string $value Raw text.
		 * @return string
		 */
		private static function text( string $value ): string {
			return str_replace(
				array( '\\', ',', ';', "\r\n", "\n", "\r" ),
				array( '\\\\', '\\,', '\\;', '\\n', '\\n', '\\n' ),
				$value
			);
		}

		/**
		 * A URI value: not text-escaped, but never allowed to break a line.
		 *
		 * @param string $value URI.
		 * @return string
		 */
		private static function uri( string $value ): string {
			return str_replace( array( "\r", "\n" ), '', $value );
		}

		/**
		 * Folds a line at 75 octets; continuation lines start with a space.
		 * A cut never lands inside a UTF-8 sequence.
		 *
		 * @param string $line One content line.
		 * @return string
		 */
		private static function fold( string $line ): string {
			if ( strlen( $line ) <= self::FOLD_AT ) {
				return $line;
			}

			$parts = array();
			$limit = self::FOLD_AT;

			while ( strlen( $line ) > $limit ) {
				$cut = $limit;

				// Step back off continuation bytes (10xxxxxx) to a character start.
				while ( $cut > 0 && ( ord( $line[ $cut ] ) & 0xC0 ) === 0x80 ) {
					$cut--;
				}

				$parts[] = substr( $line, 0, $cut );
				$line    = substr( $line, $cut );
				$limit   = self::FOLD_AT - 1; // Room for the leading space.
			}

			$parts[] = $line;

			return implode( "\r\n ", $parts );
		}
	}
}
