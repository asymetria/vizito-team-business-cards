<?php
/**
 * Card value object.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Card;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Card' ) ) {

	/**
	 * One card in one language, every value already resolved. Immutable.
	 */
	final class Card {

		/**
		 * Post id.
		 *
		 * @var int
		 */
		private int $id;

		/**
		 * Post slug.
		 *
		 * @var string
		 */
		private string $slug;

		/**
		 * Language the values are in.
		 *
		 * @var string
		 */
		private string $lang;

		/**
		 * Resolved values by field key.
		 *
		 * @var array<string, string>
		 */
		private array $values;

		/**
		 * Featured image id, 0 when none.
		 *
		 * @var int
		 */
		private int $photo_id;

		/**
		 * Permalink.
		 *
		 * @var string
		 */
		private string $url;

		/**
		 * False once the person has left.
		 *
		 * @var bool
		 */
		private bool $active;

		/**
		 * Constructor.
		 *
		 * @param int                   $id       Post id.
		 * @param string                $slug     Post slug.
		 * @param string                $lang     Language the values are in.
		 * @param array<string, string> $values   Resolved values by field key.
		 * @param int                   $photo_id Featured image id, 0 when none.
		 * @param string                $url      Permalink.
		 * @param bool                  $active   False once the person has left.
		 */
		public function __construct( int $id, string $slug, string $lang, array $values, int $photo_id, string $url, bool $active = true ) {
			$this->id       = $id;
			$this->slug     = $slug;
			$this->lang     = $lang;
			$this->values   = $values;
			$this->photo_id = $photo_id;
			$this->url      = $url;
			$this->active   = $active;
		}

		/**
		 * Post id.
		 *
		 * @return int
		 */
		public function id(): int {
			return $this->id;
		}

		/**
		 * Post slug.
		 *
		 * @return string
		 */
		public function slug(): string {
			return $this->slug;
		}

		/**
		 * Language code the values are in.
		 *
		 * @return string
		 */
		public function lang(): string {
			return $this->lang;
		}

		/**
		 * Featured image id, 0 when none.
		 *
		 * @return int
		 */
		public function photo_id(): int {
			return $this->photo_id;
		}

		/**
		 * Permalink.
		 *
		 * @return string
		 */
		public function url(): string {
			return $this->url;
		}

		/**
		 * Whether the person still works there.
		 *
		 * @return bool
		 */
		public function is_active(): bool {
			return $this->active;
		}

		/**
		 * A resolved value, "" when empty or unknown.
		 *
		 * @param string $key Field key.
		 * @return string
		 */
		public function get( string $key ): string {
			return $this->values[ $key ] ?? '';
		}

		/**
		 * Whether a value is present.
		 *
		 * @param string $key Field key.
		 * @return bool
		 */
		public function has( string $key ): bool {
			return '' !== $this->get( $key );
		}

		/**
		 * First and last name joined by one space.
		 *
		 * @return string
		 */
		public function full_name(): string {
			return trim( $this->get( 'first_name' ) . ' ' . $this->get( 'last_name' ) );
		}
	}
}
