<?php
/**
 * The public hit endpoint.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Stats;

use Asymetria\TeamCards\Card\Card_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Stats_Endpoint' ) ) {

	/**
	 * POST /wp-json/tbcards/v1/hit, sent by card.js with navigator.sendBeacon().
	 *
	 * Public by design: card pages are cached, so a nonce printed into them
	 * would be stale. It is guarded instead: the card must exist and be
	 * active, the event must be known, bots and link-preview fetchers are
	 * dropped, and one address gets at most 30 hits a minute, tracked by a
	 * salted hash in a 60-second transient. No address is ever stored.
	 *
	 * It always answers 204, so a caller learns nothing from the response.
	 * The figures are indicative; a determined caller can still inflate them.
	 */
	final class Stats_Endpoint {

		/**
		 * Events the page may report. "vcard" is not one of them: the server
		 * counts downloads itself, where they cannot be double-counted.
		 */
		public const EVENTS = array( 'view', 'call', 'email', 'whatsapp', 'directions', 'share', 'link' );

		public const PER_MINUTE = 30;

		private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|skype|discord|headless|curl|wget|python|http/i';

		/**
		 * Card repository.
		 *
		 * @var Card_Repository
		 */
		private Card_Repository $cards;

		/**
		 * Statistics table.
		 *
		 * @var Stats_Table
		 */
		private Stats_Table $table;

		/**
		 * Constructor.
		 *
		 * @param Card_Repository $cards Card repository.
		 * @param Stats_Table     $table Statistics table.
		 */
		public function __construct( Card_Repository $cards, Stats_Table $table ) {
			$this->cards = $cards;
			$this->table = $table;
		}

		/**
		 * Hooks.
		 *
		 * @return void
		 */
		public function hooks(): void {
			add_action( 'rest_api_init', array( $this, 'register_route' ) );
			add_action( 'tbcards_vcard_downloaded', array( $this, 'vcard_downloaded' ) );
		}

		/**
		 * Registers the route.
		 *
		 * @return void
		 */
		public function register_route(): void {
			register_rest_route(
				'tbcards/v1',
				'/hit',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'card'  => array(
							'type'     => 'integer',
							'required' => true,
						),
						'event' => array(
							'type'     => 'string',
							'required' => true,
						),
						'src'   => array( 'type' => 'string' ),
					),
				)
			);
		}

		/**
		 * The route callback.
		 *
		 * @param \WP_REST_Request $request Request.
		 * @return \WP_REST_Response
		 */
		public function handle( $request ) {
			$id     = absint( $request->get_param( 'card' ) );
			$event  = (string) $request->get_param( 'event' );
			$source = 'qr' === $request->get_param( 'src' ) ? 'qr' : 'link';

			if ( in_array( $event, self::EVENTS, true ) && $this->countable( $id ) ) {
				$this->table->increment( $id, $event, $source );
			}

			return new \WP_REST_Response( null, 204 );
		}

		/**
		 * Counts a vCard download reported by the vCard endpoint.
		 *
		 * @param int $card_id Card id.
		 * @return void
		 */
		public function vcard_downloaded( $card_id ): void {
			if ( ! $this->is_bot() ) {
				$this->table->increment( (int) $card_id, 'vcard', 'link' );
			}
		}

		/**
		 * Whether this hit should count.
		 *
		 * @param int $id Card id.
		 * @return bool
		 */
		private function countable( int $id ): bool {
			if ( $this->is_bot() ) {
				return false;
			}

			$post = $id ? get_post( $id ) : null;
			$card = $post && 'publish' === ( $post->post_status ?? '' ) ? $this->cards->find( $id ) : null;

			if ( null === $card || ! $card->is_active() ) {
				return false;
			}

			return $this->within_limit();
		}

		/**
		 * Whether the caller is a bot, a preview fetcher or has no user agent.
		 *
		 * @return bool
		 */
		private function is_bot(): bool {
			$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

			return '' === $agent || 1 === preg_match( self::BOTS, $agent );
		}

		/**
		 * Per-address rate limit on a salted hash that lives for a minute.
		 *
		 * @return bool
		 */
		private function within_limit(): bool {
			$address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

			/**
			 * The visitor's address for the rate limit. Behind a proxy or CDN,
			 * REMOTE_ADDR is the proxy for everyone, so all visitors would share
			 * one limit; a site that trusts its proxy's header can return the
			 * real client address here.
			 *
			 * @param string $address REMOTE_ADDR.
			 */
			$address = (string) apply_filters( 'tbcards_client_ip', $address );
			$key     = 'tbcards_hit_' . substr( hash_hmac( 'sha256', $address, wp_salt( 'nonce' ) ), 0, 24 );
			$count   = (int) get_transient( $key );

			if ( $count >= self::PER_MINUTE ) {
				return false;
			}

			set_transient( $key, $count + 1, MINUTE_IN_SECONDS );

			return true;
		}
	}
}
