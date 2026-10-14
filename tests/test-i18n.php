<?php
/**
 * Pins the translations: every string is extractable, placeholders are
 * explained to translators, both shipped languages are complete, and the
 * compiled .mo files really answer with the translation.
 *
 * @package Vizito_Team_Business_Cards
 */

if ( ! function_exists( 'tbcards_test_mo' ) ) {
	/**
	 * Reads a GNU .mo file into original => translation.
	 *
	 * @param string $file Path.
	 * @return array<string, string>
	 */
	function tbcards_test_mo( string $file ): array {
		$bytes = (string) file_get_contents( $file );
		$head  = unpack( 'Vmagic/Vrevision/Vcount/Vorig/Vtrans', substr( $bytes, 0, 20 ) );
		$out   = array();

		for ( $i = 0; $i < $head['count']; $i++ ) {
			$o = unpack( 'Vlen/Voff', substr( $bytes, $head['orig'] + 8 * $i, 8 ) );
			$t = unpack( 'Vlen/Voff', substr( $bytes, $head['trans'] + 8 * $i, 8 ) );

			$out[ substr( $bytes, $o['off'], $o['len'] ) ] = substr( $bytes, $t['off'], $t['len'] );
		}

		return $out;
	}
}

describe(
	'i18n',
	function () {

		$root = dirname( __DIR__ );

		it(
			'extracts every translatable call, all with the literal text domain',
			function () use ( $root ) {
				$output = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( $root . '/tools/i18n.php' ) . ' pot 2>&1' );

				assert_false( str_contains( $output, 'Skipped' ), $output );
				assert_true( (int) $output > 150, $output );
			}
		);

		it(
			'explains every placeholder to translators',
			function () use ( $root ) {
				$pot    = (string) file_get_contents( $root . '/languages/vizito-team-business-cards.pot' );
				$blocks = preg_split( '/\n\n/', $pot );

				foreach ( $blocks as $block ) {
					if ( preg_match( '/^msgid "(.*%[0-9$]*[sd].*)"$/m', $block, $m ) ) {
						assert_contains( '#. translators:', $block, 'no translators comment for: ' . $m[1] );
					}
				}
			}
		);

		it(
			'ships complete Polish and German translations',
			function () use ( $root ) {
				foreach ( array( 'pl_PL', 'de_DE' ) as $locale ) {
					$output = (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' -n ' . escapeshellarg( $root . '/tools/i18n.php' ) . ' check ' . escapeshellarg( $root . "/languages/vizito-team-business-cards-$locale.po" ) );

					assert_contains( '0 missing', $output, $locale . ': ' . $output );
				}
			}
		);

		it(
			'compiles .mo files that answer with the translation, plurals and context included',
			function () use ( $root ) {
				$pl = tbcards_test_mo( $root . '/languages/vizito-team-business-cards-pl_PL.mo' );
				$de = tbcards_test_mo( $root . '/languages/vizito-team-business-cards-de_DE.mo' );

				assert_same( 'Zapisz kontakt', $pl['Save contact'] );
				assert_same( 'Kontakt speichern', $de['Save contact'] );
				assert_same( "Zaimportowano %d wizytówkę.\0Zaimportowano %d wizytówki.\0Zaimportowano %d wizytówek.", $pl["%d card imported.\0%d cards imported."] );
				assert_same( 'Teamkarte', $de["block title\x04Team Card"] );
				assert_contains( 'Plural-Forms: nplurals=3;', $pl[''] );
			}
		);

		it(
			'loads the translations from the plugin folder on init',
			function () {
				\Asymetria\TeamCards\Plugin::reset_for_tests();
				\Asymetria\TeamCards\Plugin::instance()->load_textdomain();

				assert_same( 'vizito-team-business-cards', $GLOBALS['tbcards_stub']['textdomains'][0]['domain'] );
				assert_contains( '/languages', $GLOBALS['tbcards_stub']['textdomains'][0]['path'] );
			}
		);
	}
);
