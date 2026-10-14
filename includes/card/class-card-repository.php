<?php
/**
 * Card storage.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

use Asymetria\TeamCards\Company\Company_Profile;
use Asymetria\TeamCards\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Card_Repository' ) ) {

	/**
	 * The only reader and writer of card meta. Resolution order (design §5):
	 * card value in the asked language → card value in the default language →
	 * company profile (inheritable fields only) → "".
	 */
	final class Card_Repository {

		public const POST_TYPE     = 'tbcards_card';
		public const META_PREFIX   = '_tbcards_';
		public const INACTIVE_META = '_tbcards_inactive';

		/**
		 * Plugin settings.
		 *
		 * @var Settings
		 */
		private Settings $settings;

		/**
		 * Company profile.
		 *
		 * @var Company_Profile
		 */
		private Company_Profile $company;

		/**
		 * Constructor.
		 *
		 * @param Settings        $settings Plugin settings.
		 * @param Company_Profile $company  Company profile.
		 */
		public function __construct( Settings $settings, Company_Profile $company ) {
			$this->settings = $settings;
			$this->company  = $company;
		}

		/**
		 * A card by post id.
		 *
		 * @param int         $id   Post id.
		 * @param string|null $lang Language code; the default when null or unknown.
		 * @return Card|null
		 */
		public function find( int $id, ?string $lang = null ): ?Card {
			$post = get_post( $id );

			if ( ! $post || self::POST_TYPE !== ( $post->post_type ?? '' ) ) {
				return null;
			}

			$lang   = $this->language( $lang );
			$raw    = $this->raw( $id );
			$values = array();

			foreach ( Fields::all() as $key => $def ) {
				$values[ $key ] = $this->resolve( $key, $def, $raw[ $key ] ?? '', $lang );
			}

			return new Card(
				(int) $post->ID,
				(string) $post->post_name,
				$lang,
				$values,
				(int) get_post_thumbnail_id( $id ),
				(string) get_permalink( $id ),
				'' === (string) get_post_meta( $id, self::INACTIVE_META, true )
			);
		}

		/**
		 * A published card by slug.
		 *
		 * @param string      $slug Post slug.
		 * @param string|null $lang Language code.
		 * @return Card|null
		 */
		public function find_by_slug( string $slug, ?string $lang = null ): ?Card {
			$slug = sanitize_title( $slug );

			if ( '' === $slug ) {
				return null;
			}

			$ids = get_posts(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'name'        => $slug,
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);

			return array() === $ids ? null : $this->find( (int) $ids[0], $lang );
		}

		/**
		 * Published cards for pickers: id, name and slug, by name.
		 *
		 * @return array<int, array{id: int, name: string, slug: string}>
		 */
		public function choices(): array {
			$out = array();

			foreach ( get_posts(
				array(
					'post_type'   => self::POST_TYPE,
					'post_status' => 'publish',
					'numberposts' => -1,
					'orderby'     => 'title',
					'order'       => 'ASC',
				)
			) as $post ) {
				$out[] = array(
					'id'   => (int) $post->ID,
					'name' => (string) ( $post->post_title ?? '' ),
					'slug' => (string) ( $post->post_name ?? '' ),
				);
			}

			return $out;
		}

		/**
		 * Raw stored values, keyed by field key, "" for missing ones.
		 *
		 * WordPress primes the whole meta cache of a post on the first
		 * get_post_meta() call, so this is one query, not one per field.
		 *
		 * @param int $id Post id.
		 * @return array<string, mixed>
		 */
		public function raw( int $id ): array {
			$raw = array();

			foreach ( array_keys( Fields::all() ) as $key ) {
				$raw[ $key ] = get_post_meta( $id, self::META_PREFIX . $key, true );
			}

			return $raw;
		}

		/**
		 * Stores already-sanitised values. Only keys passed are touched; an
		 * empty string or empty array deletes the meta.
		 *
		 * Values are slashed because update_post_meta() unslashes whatever it
		 * is given: without it a backslash in a name or an intro would vanish.
		 *
		 * @param int   $id     Post id.
		 * @param array $values Values keyed by field key.
		 * @return void
		 */
		public function save( int $id, array $values ): void {
			foreach ( array_keys( Fields::all() ) as $key ) {
				if ( ! array_key_exists( $key, $values ) ) {
					continue;
				}

				$value = $values[ $key ];

				if ( '' === $value || array() === $value ) {
					delete_post_meta( $id, self::META_PREFIX . $key );
				} else {
					update_post_meta( $id, self::META_PREFIX . $key, wp_slash( $value ) );
				}
			}
		}

		/**
		 * A configured language code; the default for anything else.
		 *
		 * @param string|null $lang Asked language.
		 * @return string
		 */
		private function language( ?string $lang ): string {
			return null !== $lang && in_array( $lang, $this->settings->languages(), true )
				? $lang
				: $this->settings->default_language();
		}

		/**
		 * One resolved value.
		 *
		 * @param string $key  Field key.
		 * @param array  $def  Field definition.
		 * @param mixed  $own  The card's stored value.
		 * @param string $lang Language code.
		 * @return string
		 */
		private function resolve( string $key, array $def, $own, string $lang ): string {
			if ( $def['translated'] ) {
				$value = is_array( $own ) ? Company_Profile::pick( $own, $lang, $this->settings->default_language() ) : '';
			} else {
				$value = is_scalar( $own ) ? trim( (string) $own ) : '';
			}

			if ( '' === $value && $def['inherits'] ) {
				$value = $this->company->value( $key, $lang );
			}

			return $value;
		}
	}
}
