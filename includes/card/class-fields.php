<?php
/**
 * Card field catalogue.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Fields' ) ) {

	/**
	 * Every card field, defined once. Order here is the order of the form,
	 * the CSV columns and the card.
	 */
	final class Fields {

		public const TYPES      = array( 'text', 'textarea', 'tel', 'email', 'url' );
		public const MAX_CUSTOM = 3;

		private const SOCIAL = array( 'linkedin', 'facebook', 'instagram', 'x', 'youtube', 'tiktok', 'github', 'xing' );

		/**
		 * All fields, keyed by field key.
		 *
		 * @return array<string, array{label: string, type: string, group: string, translated: bool, inherits: bool}>
		 */
		public static function all(): array {
			$fields = array(
				'first_name' => self::def( __( 'First name', 'vizito-team-business-cards' ), 'text', 'person' ),
				'last_name'  => self::def( __( 'Last name', 'vizito-team-business-cards' ), 'text', 'person' ),
				'job_title'  => self::def( __( 'Job title', 'vizito-team-business-cards' ), 'text', 'role', true ),
				'intro'      => self::def( __( 'Intro', 'vizito-team-business-cards' ), 'textarea', 'role', true, true ),
				'mobile'     => self::def( __( 'Mobile phone', 'vizito-team-business-cards' ), 'tel', 'contact' ),
				'landline'   => self::def( __( 'Landline', 'vizito-team-business-cards' ), 'tel', 'contact', false, true ),
				'email'      => self::def( __( 'Email', 'vizito-team-business-cards' ), 'email', 'contact' ),
				'whatsapp'   => self::def( __( 'WhatsApp number', 'vizito-team-business-cards' ), 'tel', 'contact' ),
				'street'     => self::def( __( 'Street and number', 'vizito-team-business-cards' ), 'text', 'address', false, true ),
				'postcode'   => self::def( __( 'Postcode', 'vizito-team-business-cards' ), 'text', 'address', false, true ),
				'city'       => self::def( __( 'City', 'vizito-team-business-cards' ), 'text', 'address', false, true ),
				'country'    => self::def( __( 'Country', 'vizito-team-business-cards' ), 'text', 'address', false, true ),
				'map_url'    => self::def( __( 'Map link', 'vizito-team-business-cards' ), 'url', 'address', false, true ),
				'website'    => self::def( __( 'Website', 'vizito-team-business-cards' ), 'url', 'links', false, true ),
				'linkedin'   => self::def( 'LinkedIn', 'url', 'links', false, true ),
				'facebook'   => self::def( 'Facebook', 'url', 'links', false, true ),
				'instagram'  => self::def( 'Instagram', 'url', 'links', false, true ),
				'x'          => self::def( 'X', 'url', 'links', false, true ),
				'youtube'    => self::def( 'YouTube', 'url', 'links', false, true ),
				'tiktok'     => self::def( 'TikTok', 'url', 'links', false, true ),
				'github'     => self::def( 'GitHub', 'url', 'links', false, true ),
				'xing'       => self::def( 'XING', 'url', 'links', false, true ),
			);

			for ( $i = 1; $i <= self::MAX_CUSTOM; $i++ ) {
				/* translators: %d: button number, 1 to 3. */
				$fields[ "custom_{$i}_label" ] = self::def( sprintf( __( 'Button %d label', 'vizito-team-business-cards' ), $i ), 'text', 'custom', true );
				/* translators: %d: button number, 1 to 3. */
				$fields[ "custom_{$i}_url" ] = self::def( sprintf( __( 'Button %d link', 'vizito-team-business-cards' ), $i ), 'url', 'custom' );
			}

			return $fields;
		}

		/**
		 * One field definition.
		 *
		 * @param string $key Field key.
		 * @return array|null
		 */
		public static function get( string $key ): ?array {
			return self::all()[ $key ] ?? null;
		}

		/**
		 * Keys of translated fields, in catalogue order.
		 *
		 * @return string[]
		 */
		public static function translated(): array {
			return array_keys(
				array_filter(
					self::all(),
					static function ( $f ) {
						return $f['translated'];
					}
				)
			);
		}

		/**
		 * Keys of fields the company profile can supply, in catalogue order.
		 *
		 * @return string[]
		 */
		public static function inheritable(): array {
			return array_keys(
				array_filter(
					self::all(),
					static function ( $f ) {
						return $f['inherits'];
					}
				)
			);
		}

		/**
		 * Social network keys, in display order.
		 *
		 * @return string[]
		 */
		public static function social(): array {
			return self::SOCIAL;
		}

		/**
		 * Builds one definition.
		 *
		 * @param string $label      Label.
		 * @param string $type       One of TYPES.
		 * @param string $group      Form group.
		 * @param bool   $translated Stored per language.
		 * @param bool   $inherits   Company profile supplies it when empty.
		 * @return array
		 */
		private static function def( string $label, string $type, string $group, bool $translated = false, bool $inherits = false ): array {
			return array(
				'label'      => $label,
				'type'       => $type,
				'group'      => $group,
				'translated' => $translated,
				'inherits'   => $inherits,
			);
		}
	}
}
