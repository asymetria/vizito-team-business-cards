<?php
/**
 * The card HTML.
 *
 * @package Vizito_Team_Business_Cards
 */

namespace Asymetria\TeamCards\Front;

use Asymetria\TeamCards\Card\Card;
use Asymetria\TeamCards\Card\Field_Sanitizer;
use Asymetria\TeamCards\Card\Fields;
use Asymetria\TeamCards\Company\Company_Profile;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( __NAMESPACE__ . '\\Card_Renderer' ) ) {

	/**
	 * One renderer for the full page and every embed, so a card looks the
	 * same everywhere. Everything is escaped where it is printed. Labels come
	 * from __(); callers wrap render() in Language::with_locale().
	 *
	 * An action whose value is empty is left out, never rendered disabled.
	 */
	final class Card_Renderer {

		/**
		 * Every statistic event a card can emit (design §9).
		 */
		public const EVENTS = array( 'view', 'vcard', 'call', 'email', 'whatsapp', 'directions', 'share', 'link' );

		/**
		 * Company profile, for the logo and the company name.
		 *
		 * @var Company_Profile
		 */
		private Company_Profile $company;

		/**
		 * Constructor.
		 *
		 * @param Company_Profile $company Company profile.
		 */
		public function __construct( Company_Profile $company ) {
			$this->company = $company;
		}

		/**
		 * The card HTML.
		 *
		 * @param Card  $card Card.
		 * @param array $args {
		 *     @type string                $context   "page" or "embed".
		 *     @type string                $vcard_url Contact file address; "" hides Save contact.
		 *     @type array<string, string> $languages Switcher links by language code (page only).
		 * }
		 * @return string
		 */
		public function render( Card $card, array $args = array() ): string {
			$args = array_merge(
				array(
					'context'   => 'page',
					'vcard_url' => '',
					'languages' => array(),
				),
				$args
			);

			$page = 'page' === $args['context'];

			$html  = '<article class="tbc-card tbc-card--' . ( $page ? 'page' : 'embed' ) . '" data-tbc-card="' . esc_attr( (string) $card->id() ) . '" lang="' . esc_attr( $card->lang() ) . '">';
			$html .= $this->head( $card, $page );
			$html .= $this->actions( $card, (string) $args['vcard_url'] );
			$html .= $this->details( $card );
			$html .= $this->address( $card );
			$html .= $this->social( $card );
			$html .= $this->buttons( $card );
			$html .= $this->foot( $card, $page ? (array) $args['languages'] : array() );
			$html .= '</article>';

			return $html;
		}

		/**
		 * What an inactive card shows: the company, never the person.
		 *
		 * @param Card   $card    The inactive card, for its language only.
		 * @param string $context "page" or "embed".
		 * @return string
		 */
		public function notice( Card $card, string $context = 'page' ): string {
			$lang    = $card->lang();
			$company = $this->company->value( 'company_name', $lang );
			$logo    = $this->company->logo_id() ? wp_get_attachment_image_url( $this->company->logo_id(), 'medium' ) : false;
			$tag     = 'page' === $context ? 'h1' : 'h2';
			$html    = '<article class="tbc-card tbc-card--' . ( 'page' === $context ? 'page' : 'embed' ) . ' tbc-card--inactive" lang="' . esc_attr( $lang ) . '"><header class="tbc-card__head">';

			if ( $logo ) {
				$html .= '<img class="tbc-card__logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $company ) . '">';
			}

			$html .= '<' . $tag . ' class="tbc-card__name">' . esc_html( '' !== $company ? $company : __( 'Business card', 'vizito-team-business-cards' ) ) . '</' . $tag . '>';
			$html .= '<p class="tbc-card__intro">' . esc_html__( 'This business card is no longer active. You can still reach the company:', 'vizito-team-business-cards' ) . '</p></header>';

			$details = array();
			$phone   = Field_Sanitizer::dial( $this->company->value( 'landline', $lang ) );
			$website = $this->company->value( 'website', $lang );

			if ( '' !== $phone ) {
				$details[] = $this->action( 'tel:' . $phone, 'call', 'phone', $this->company->value( 'landline', $lang ) );
			}

			if ( '' !== $website ) {
				$details[] = $this->action( $website, 'link', 'globe', (string) preg_replace( '#^https?://(www\.)?#', '', rtrim( $website, '/' ) ), true );
			}

			if ( array() !== $details ) {
				$html .= '<nav class="tbc-card__actions" aria-label="' . esc_attr__( 'Contact', 'vizito-team-business-cards' ) . '">' . implode( '', $details ) . '</nav>';
			}

			return $html . '</article>';
		}

		/**
		 * Logo, photo, name, role, company and intro.
		 *
		 * @param Card $card Card.
		 * @param bool $page Whether this is the full page.
		 * @return string
		 */
		private function head( Card $card, bool $page ): string {
			$name    = $card->full_name();
			$company = $this->company->value( 'company_name', $card->lang() );
			$logo    = $this->company->logo_id() ? wp_get_attachment_image_url( $this->company->logo_id(), 'medium' ) : false;
			$photo   = $card->photo_id() ? wp_get_attachment_image_url( $card->photo_id(), 'medium' ) : false;
			$tag     = $page ? 'h1' : 'h2';
			$html    = '<header class="tbc-card__head">';

			if ( $logo ) {
				$html .= '<img class="tbc-card__logo" src="' . esc_url( $logo ) . '" alt="' . esc_attr( $company ) . '">';
			}

			if ( $photo ) {
				$html .= '<img class="tbc-card__photo" src="' . esc_url( $photo ) . '" alt="' . esc_attr( $name ) . '" width="160" height="160">';
			} else {
				$html .= '<span class="tbc-card__photo tbc-card__initials" aria-hidden="true">' . esc_html( $this->initials( $card ) ) . '</span>';
			}

			$html .= '<' . $tag . ' class="tbc-card__name">' . esc_html( $name ) . '</' . $tag . '>';

			if ( $card->has( 'job_title' ) ) {
				$html .= '<p class="tbc-card__title">' . esc_html( $card->get( 'job_title' ) ) . '</p>';
			}

			if ( '' !== $company ) {
				$html .= '<p class="tbc-card__company">' . esc_html( $company ) . '</p>';
			}

			if ( $card->has( 'intro' ) ) {
				$html .= '<p class="tbc-card__intro">' . nl2br( esc_html( $card->get( 'intro' ) ) ) . '</p>';
			}

			return $html . '</header>';
		}

		/**
		 * Save contact, Call, Email, WhatsApp.
		 *
		 * @param Card   $card      Card.
		 * @param string $vcard_url Contact file address.
		 * @return string
		 */
		private function actions( Card $card, string $vcard_url ): string {
			$items = array();

			if ( '' !== $vcard_url ) {
				$items[] = '<a class="tbc-action tbc-action--primary" href="' . esc_url( $vcard_url ) . '" download data-tbc-event="vcard">'
					. Icons::svg( 'download' ) . '<span>' . esc_html__( 'Save contact', 'vizito-team-business-cards' ) . '</span></a>';
			}

			$dial = Field_Sanitizer::dial( $card->get( 'mobile' ) );

			if ( '' !== $dial ) {
				$items[] = $this->action( 'tel:' . $dial, 'call', 'phone', __( 'Call', 'vizito-team-business-cards' ) );
			}

			if ( $card->has( 'email' ) ) {
				$items[] = $this->action( 'mailto:' . $card->get( 'email' ), 'email', 'mail', __( 'Email', 'vizito-team-business-cards' ) );
			}

			$whatsapp = ltrim( Field_Sanitizer::dial( $card->get( 'whatsapp' ) ), '+' );

			if ( '' !== $whatsapp ) {
				$items[] = $this->action( 'https://wa.me/' . $whatsapp, 'whatsapp', 'whatsapp', 'WhatsApp', true );
			}

			if ( array() === $items ) {
				return '';
			}

			return '<nav class="tbc-card__actions" aria-label="' . esc_attr__( 'Contact', 'vizito-team-business-cards' ) . '">' . implode( '', $items ) . '</nav>';
		}

		/**
		 * Labelled contact details.
		 *
		 * @param Card $card Card.
		 * @return string
		 */
		private function details( Card $card ): string {
			$rows = array();

			foreach ( array( 'mobile', 'landline' ) as $key ) {
				$dial = Field_Sanitizer::dial( $card->get( $key ) );

				if ( '' !== $dial ) {
					$rows[] = $this->detail( Fields::get( $key )['label'], 'tel:' . $dial, $card->get( $key ), 'call' );
				}
			}

			if ( $card->has( 'email' ) ) {
				$rows[] = $this->detail( Fields::get( 'email' )['label'], 'mailto:' . $card->get( 'email' ), $card->get( 'email' ), 'email' );
			}

			if ( $card->has( 'website' ) ) {
				$shown  = preg_replace( '#^https?://(www\.)?#', '', rtrim( $card->get( 'website' ), '/' ) );
				$rows[] = $this->detail( Fields::get( 'website' )['label'], $card->get( 'website' ), (string) $shown, 'link', true );
			}

			return array() === $rows ? '' : '<dl class="tbc-card__details">' . implode( '', $rows ) . '</dl>';
		}

		/**
		 * Address and Directions.
		 *
		 * @param Card $card Card.
		 * @return string
		 */
		private function address( Card $card ): string {
			$line_two = trim( $card->get( 'postcode' ) . ' ' . $card->get( 'city' ) );
			$lines    = array_values( array_filter( array( $card->get( 'street' ), $line_two, $card->get( 'country' ) ), 'strlen' ) );

			if ( array() === $lines && ! $card->has( 'map_url' ) ) {
				return '';
			}

			$map = $card->has( 'map_url' )
				? $card->get( 'map_url' )
				: 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( implode( ', ', $lines ) );

			$html = '<div class="tbc-card__address">';

			if ( array() !== $lines ) {
				$html .= '<address>' . implode( '<br>', array_map( 'esc_html', $lines ) ) . '</address>';
			}

			$html .= $this->action( $map, 'directions', 'pin', __( 'Directions', 'vizito-team-business-cards' ), true );

			return $html . '</div>';
		}

		/**
		 * Social profile icons, in catalogue order.
		 *
		 * @param Card $card Card.
		 * @return string
		 */
		private function social( Card $card ): string {
			$items = array();

			foreach ( Fields::social() as $network ) {
				if ( ! $card->has( $network ) ) {
					continue;
				}

				$label   = Fields::get( $network )['label'] . ': ' . $card->full_name();
				$items[] = '<li><a href="' . esc_url( $card->get( $network ) ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $label ) . '" data-tbc-event="link">' . Icons::svg( $network ) . '</a></li>';
			}

			return array() === $items ? '' : '<ul class="tbc-card__social">' . implode( '', $items ) . '</ul>';
		}

		/**
		 * Custom buttons; each needs both a label and an address.
		 *
		 * @param Card $card Card.
		 * @return string
		 */
		private function buttons( Card $card ): string {
			$items = array();

			for ( $i = 1; $i <= Fields::MAX_CUSTOM; $i++ ) {
				$label = $card->get( "custom_{$i}_label" );
				$url   = $card->get( "custom_{$i}_url" );

				if ( '' !== $label && '' !== $url ) {
					$items[] = '<a class="tbc-button" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" data-tbc-event="link">' . esc_html( $label ) . self::new_tab() . '</a>';
				}
			}

			return array() === $items ? '' : '<div class="tbc-card__buttons">' . implode( '', $items ) . '</div>';
		}

		/**
		 * Share, the status line it writes to, and the language switcher.
		 *
		 * @param Card                  $card      Card.
		 * @param array<string, string> $languages Switcher links by code.
		 * @return string
		 */
		private function foot( Card $card, array $languages ): string {
			$html  = '<footer class="tbc-card__foot">';
			$html .= '<button type="button" class="tbc-share" data-tbc-event="share" data-tbc-url="' . esc_url( $card->url() ) . '">'
				. Icons::svg( 'share' ) . '<span>' . esc_html__( 'Share', 'vizito-team-business-cards' ) . '</span></button>';
			$html .= '<p class="tbc-status" role="status" aria-live="polite"></p>';

			if ( count( $languages ) > 1 ) {
				$html .= '<nav class="tbc-card__langs" aria-label="' . esc_attr__( 'Language', 'vizito-team-business-cards' ) . '">';

				foreach ( $languages as $code => $url ) {
					$current = $code === $card->lang() ? ' aria-current="true"' : '';
					$html   .= '<a href="' . esc_url( $url ) . '" hreflang="' . esc_attr( $code ) . '" lang="' . esc_attr( $code ) . '"' . $current . '>' . esc_html( strtoupper( $code ) ) . '</a>';
				}

				$html .= '</nav>';
			}

			return $html . '</footer>';
		}

		/**
		 * One action link.
		 *
		 * @param string $url      Address.
		 * @param string $event    Statistic event.
		 * @param string $icon     Icon name.
		 * @param string $label    Visible label.
		 * @param bool   $external Opens in a new tab.
		 * @return string
		 */
		private function action( string $url, string $event, string $icon, string $label, bool $external = false ): string {
			$target = $external ? ' target="_blank" rel="noopener"' : '';

			return '<a class="tbc-action" href="' . esc_url( $url ) . '"' . $target . ' data-tbc-event="' . esc_attr( $event ) . '">'
				. Icons::svg( $icon ) . '<span>' . esc_html( $label ) . '</span>' . ( $external ? self::new_tab() : '' ) . '</a>';
		}

		/**
		 * Screen-reader text for links that open a new tab.
		 *
		 * @return string
		 */
		private static function new_tab(): string {
			return '<span class="tbc-sr"> ' . esc_html__( '(opens in a new tab)', 'vizito-team-business-cards' ) . '</span>';
		}

		/**
		 * One row of the details list.
		 *
		 * @param string $label    Term.
		 * @param string $url      Address.
		 * @param string $text     Shown value.
		 * @param string $event    Statistic event.
		 * @param bool   $external Opens in a new tab.
		 * @return string
		 */
		private function detail( string $label, string $url, string $text, string $event, bool $external = false ): string {
			$target = $external ? ' target="_blank" rel="noopener"' : '';

			return '<div><dt>' . esc_html( $label ) . '</dt><dd><a href="' . esc_url( $url ) . '"' . $target . ' data-tbc-event="' . esc_attr( $event ) . '">' . esc_html( $text ) . ( $external ? self::new_tab() : '' ) . '</a></dd></div>';
		}

		/**
		 * Up to two initials, multibyte-safe where mbstring exists.
		 *
		 * @param Card $card Card.
		 * @return string
		 */
		private function initials( Card $card ): string {
			$out = '';

			foreach ( array( 'first_name', 'last_name' ) as $key ) {
				$value = $card->get( $key );

				if ( '' === $value ) {
					continue;
				}

				$out .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $value, 0, 1 ) ) : strtoupper( (string) preg_replace( '/^(.).*$/us', '$1', $value ) );
			}

			return $out;
		}
	}
}
