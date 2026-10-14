<?php
/**
 * Pins the field catalogue: which fields exist, which are translated, which inherit.
 *
 * @package Vizito_Team_Business_Cards
 */

require_once dirname( __DIR__ ) . '/includes/card/class-fields.php';

use Asymetria\TeamCards\Card\Fields;

describe(
	'fields',
	function () {

		it(
			'defines every field with label, type, group and two flags',
			function () {
				foreach ( Fields::all() as $key => $def ) {
					assert_true( is_string( $def['label'] ) && '' !== $def['label'], "$key label" );
					assert_true( in_array( $def['type'], Fields::TYPES, true ), "$key type" );
					assert_true( is_string( $def['group'] ), "$key group" );
					assert_true( is_bool( $def['translated'] ), "$key translated" );
					assert_true( is_bool( $def['inherits'] ), "$key inherits" );
				}
			}
		);

		it(
			'translates exactly the job title, the intro and the custom labels',
			function () {
				assert_same(
					array( 'job_title', 'intro', 'custom_1_label', 'custom_2_label', 'custom_3_label' ),
					Fields::translated()
				);
			}
		);

		it(
			'lets the company profile supply the intro, landline, address and links',
			function () {
				assert_same(
					array( 'intro', 'landline', 'street', 'postcode', 'city', 'country', 'map_url', 'website', 'linkedin', 'facebook', 'instagram', 'x', 'youtube', 'tiktok', 'github', 'xing' ),
					Fields::inheritable()
				);
			}
		);

		it(
			'never lets personal fields inherit',
			function () {
				foreach ( array( 'first_name', 'last_name', 'job_title', 'mobile', 'email', 'whatsapp' ) as $key ) {
					assert_false( Fields::get( $key )['inherits'], $key );
				}
			}
		);

		it(
			'lists social networks in display order',
			function () {
				assert_same( array( 'linkedin', 'facebook', 'instagram', 'x', 'youtube', 'tiktok', 'github', 'xing' ), Fields::social() );
			}
		);

		it(
			'answers null for an unknown field',
			function () {
				assert_same( null, Fields::get( 'nope' ) );
			}
		);
	}
);
