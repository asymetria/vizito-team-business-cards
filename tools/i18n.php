<?php
/**
 * Translation tooling without WP-CLI or gettext. Dev only, not shipped.
 *
 *   php tools/i18n.php pot           Writes languages/vizito-team-business-cards.pot
 *   php tools/i18n.php mo            Compiles every languages/*.po to .mo
 *   php tools/i18n.php check <file>  Lists msgids of a .po missing from the .pot
 *
 * The extractor reads PHP with token_get_all(), so strings are found the way
 * PHP parses them, not by a regular expression. It understands __, _e, _x,
 * _n, _nx and the esc_html / esc_attr variants, takes the "translators:"
 * comment right before a call, and adds the title, description and keywords of
 * every block.json, as WP-CLI does.
 *
 * @package Vizito_Team_Business_Cards
 */

const TBCARDS_DOMAIN = 'vizito-team-business-cards';

$root = dirname( __DIR__ );

/**
 * Scripts with __() calls. Their strings go into the .pot like PHP ones and
 * into one Jed JSON file per locale, named as wp_set_script_translations()
 * looks for them: {domain}-{locale}-{md5 of the script's relative path}.json.
 */
const TBCARDS_JS_FILES = array( 'assets/block.js' );

/**
 * The __( 'literal', 'vizito-team-business-cards' ) strings of a script, with lines.
 *
 * @param string $file Path.
 * @return array<string, int> msgid => first line.
 */
function tbcards_js_strings( string $file ): array {
	$found   = array();
	$pattern = "/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'\\s*,\\s*'" . TBCARDS_DOMAIN . "'\\s*\\)/u";

	foreach ( preg_split( '/\r\n|\n|\r/', (string) file_get_contents( $file ) ) as $i => $line ) {
		preg_match_all( $pattern, $line, $m );

		foreach ( $m[1] as $msgid ) {
			$msgid = stripslashes( $msgid );

			if ( ! isset( $found[ $msgid ] ) ) {
				$found[ $msgid ] = $i + 1;
			}
		}
	}

	return $found;
}

/**
 * Functions and the position of their arguments: singular, plural, context.
 */
$functions = array(
	'__'           => array( 0, null, null ),
	'_e'           => array( 0, null, null ),
	'esc_html__'   => array( 0, null, null ),
	'esc_html_e'   => array( 0, null, null ),
	'esc_attr__'   => array( 0, null, null ),
	'esc_attr_e'   => array( 0, null, null ),
	'_x'           => array( 0, null, 1 ),
	'esc_html_x'   => array( 0, null, 1 ),
	'esc_attr_x'   => array( 0, null, 1 ),
	'_n'           => array( 0, 1, null ),
	'_nx'          => array( 0, 1, 3 ),
);

/**
 * PHP source files that ship.
 *
 * @param string $root Plugin root.
 * @return string[]
 */
function tbcards_i18n_files( string $root ): array {
	$files = array( $root . '/vizito-team-business-cards.php' );

	foreach ( array( 'includes', 'templates' ) as $dir ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );

		foreach ( $it as $file ) {
			if ( 'php' === $file->getExtension() ) {
				$files[] = str_replace( '\\', '/', $file->getPathname() );
			}
		}
	}

	sort( $files );

	return $files;
}

/**
 * Extracts entries from one PHP file.
 *
 * @param string $file      Path.
 * @param string $relative  Path for references.
 * @param array  $functions Function table.
 * @param array  $entries   Entries, by key; extended in place.
 * @return void
 */
function tbcards_i18n_extract( string $file, string $relative, array $functions, array &$entries ): void {
	$tokens  = token_get_all( (string) file_get_contents( $file ) );
	$count   = count( $tokens );
	$comment = '';

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
			if ( preg_match( '/translators:\s*(.+?)\s*(\*\/)?$/s', $token[1], $m ) ) {
				$comment = trim( preg_replace( '/\s+/', ' ', $m[1] ) );
			}
			continue;
		}

		if ( ! is_array( $token ) || T_STRING !== $token[0] || ! isset( $functions[ $token[1] ] ) ) {
			if ( is_array( $token ) && T_WHITESPACE === $token[0] ) {
				continue;
			}
			// Only a statement end drops a pending comment: braces also come from
			// interpolation such as "custom_{$i}_label" on the same line.
			if ( ';' === $token ) {
				$comment = '';
			}
			continue;
		}

		// Skip method calls ->__() and definitions function __().
		$prev = $i - 1;
		while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
			$prev--;
		}
		if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_FUNCTION, T_DOUBLE_COLON ), true ) ) {
			continue;
		}

		$line = $token[2];
		$spec = $functions[ $token[1] ];
		$args = array();
		$arg  = array();
		$j    = $i + 1;

		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			$j++;
		}

		if ( '(' !== $tokens[ $j ] ) {
			continue;
		}

		$depth = 0;

		for ( ; $j < $count; $j++ ) {
			$t = $tokens[ $j ];

			if ( '(' === $t ) {
				$depth++;
				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( ')' === $t ) {
				$depth--;
				if ( 0 === $depth ) {
					$args[] = $arg;
					break;
				}
			} elseif ( ',' === $t && 1 === $depth ) {
				$args[] = $arg;
				$arg    = array();
				continue;
			}

			if ( is_array( $t ) && T_WHITESPACE === $t[0] ) {
				continue;
			}

			$arg[] = $t;
		}

		$literal = static function ( $index ) use ( $args ) {
			if ( null === $index || ! isset( $args[ $index ] ) || 1 !== count( $args[ $index ] ) ) {
				return null;
			}
			$t = $args[ $index ][0];
			if ( ! is_array( $t ) || T_CONSTANT_ENCAPSED_STRING !== $t[0] ) {
				return null;
			}
			return eval( 'return ' . $t[1] . ';' ); // phpcs:ignore Squiz.PHP.Eval -- a PHP string literal from the tokenizer.
		};

		$domain_index = count( $args ) - 1;
		$msgid        = $literal( $spec[0] );

		if ( null === $msgid || TBCARDS_DOMAIN !== $literal( $domain_index ) ) {
			fwrite( STDERR, "Skipped non-literal or foreign-domain call at $relative:$line\n" );
			$comment = '';
			continue;
		}

		$plural  = $literal( $spec[1] );
		$context = $literal( $spec[2] );
		$key     = ( null === $context ? '' : $context . "\x04" ) . $msgid;

		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array(
				'msgid'    => $msgid,
				'plural'   => $plural,
				'context'  => $context,
				'refs'     => array(),
				'comments' => array(),
			);
		}

		$entries[ $key ]['refs'][] = $relative . ':' . $line;

		if ( '' !== $comment ) {
			$entries[ $key ]['comments'][ $comment ] = true;
		}

		$comment = '';
	}
}

/**
 * A PO string literal.
 *
 * @param string $s Text.
 * @return string
 */
function tbcards_po_quote( string $s ): string {
	return '"' . str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $s ) . '"';
}

/**
 * Parses a .po file into entries.
 *
 * @param string $file Path.
 * @return array<int, array{context: ?string, msgid: string, plural: ?string, msgstr: string[]}>
 */
function tbcards_po_parse( string $file ): array {
	$entries = array();
	$current = null;
	$field   = null;

	$unquote = static function ( string $s ): string {
		return stripcslashes( substr( trim( $s ), 1, -1 ) );
	};

	// Not \R: without the u flag it also matches byte 0x85, the second byte of "ą".
	foreach ( preg_split( '/\r\n|\n|\r/', (string) file_get_contents( $file ) ) as $line ) {
		$line = trim( $line );

		if ( '' === $line || '#' === $line[0] ) {
			if ( '' === $line && null !== $current ) {
				$entries[] = $current;
				$current   = null;
			}
			continue;
		}

		if ( preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+(".*")$/', $line, $m ) ) {
			if ( null === $current || ( 'msgctxt' === $m[1] ) || ( 'msgid' === $m[1] && isset( $current['msgid'] ) ) ) {
				if ( null !== $current && isset( $current['msgid'] ) ) {
					$entries[] = $current;
				}
				$current = array(
					'context' => null,
					'plural'  => null,
					'msgstr'  => array(),
				);
			}

			if ( 'msgctxt' === $m[1] ) {
				$current['context'] = $unquote( $m[3] );
				$field              = array( 'context' );
			} elseif ( 'msgid' === $m[1] ) {
				$current['msgid'] = $unquote( $m[3] );
				$field            = array( 'msgid' );
			} elseif ( 'msgid_plural' === $m[1] ) {
				$current['plural'] = $unquote( $m[3] );
				$field             = array( 'plural' );
			} else {
				$index                       = isset( $m[2] ) && '' !== $m[2] ? (int) $m[2] : 0;
				$current['msgstr'][ $index ] = $unquote( $m[3] );
				$field                       = array( 'msgstr', $index );
			}
			continue;
		}

		if ( '"' === $line[0] && null !== $current && null !== $field ) {
			if ( 'msgstr' === $field[0] ) {
				$current['msgstr'][ $field[1] ] .= $unquote( $line );
			} else {
				$current[ $field[0] ] .= $unquote( $line );
			}
		}
	}

	if ( null !== $current && isset( $current['msgid'] ) ) {
		$entries[] = $current;
	}

	return $entries;
}

/**
 * Writes a GNU .mo file.
 *
 * @param array  $entries Parsed .po entries.
 * @param string $target  Path.
 * @return int Number of translated strings written.
 */
function tbcards_mo_write( array $entries, string $target ): int {
	$pairs = array();

	foreach ( $entries as $entry ) {
		$translated = array_filter(
			$entry['msgstr'],
			static function ( $s ) {
				return '' !== $s;
			}
		);

		if ( array() === $translated && '' !== $entry['msgid'] ) {
			continue;
		}

		$key = ( null === $entry['context'] ? '' : $entry['context'] . "\x04" ) . $entry['msgid'];

		if ( null !== $entry['plural'] ) {
			$key .= "\0" . $entry['plural'];
		}

		ksort( $entry['msgstr'] );
		$pairs[ $key ] = implode( "\0", $entry['msgstr'] );
	}

	ksort( $pairs, SORT_STRING );

	$n        = count( $pairs );
	$keys     = array_keys( $pairs );
	$values   = array_values( $pairs );
	$orig_at  = 28;
	$trans_at = $orig_at + 8 * $n;
	$data_at  = $trans_at + 8 * $n;
	$ids      = '';
	$strs     = '';
	$orig_tbl = '';
	$tran_tbl = '';

	foreach ( $keys as $key ) {
		$orig_tbl .= pack( 'VV', strlen( $key ), $data_at + strlen( $ids ) );
		$ids      .= $key . "\0";
	}

	$strs_at = $data_at + strlen( $ids );

	foreach ( $values as $value ) {
		$tran_tbl .= pack( 'VV', strlen( $value ), $strs_at + strlen( $strs ) );
		$strs     .= $value . "\0";
	}

	$mo = pack( 'VVVVVVV', 0x950412de, 0, $n, $orig_at, $trans_at, 0, $data_at ) . $orig_tbl . $tran_tbl . $ids . $strs;

	file_put_contents( $target, $mo );

	return $n - ( isset( $pairs[''] ) ? 1 : 0 );
}

if ( realpath( $argv[0] ?? '' ) !== realpath( __FILE__ ) ) {
	return; // Loaded as a library.
}

$command = $argv[1] ?? '';

if ( 'pot' === $command ) {
	$entries = array();

	foreach ( tbcards_i18n_files( $root ) as $file ) {
		tbcards_i18n_extract( $file, substr( $file, strlen( str_replace( '\\', '/', $root ) ) + 1 ), $functions, $entries );
	}

	foreach ( glob( $root . '/blocks/*/block.json' ) as $json_file ) {
		$json     = json_decode( (string) file_get_contents( $json_file ), true );
		$relative = substr( str_replace( '\\', '/', $json_file ), strlen( str_replace( '\\', '/', $root ) ) + 1 );

		foreach ( array( 'title' => 'block title', 'description' => 'block description' ) as $field => $context ) {
			if ( isset( $json[ $field ] ) ) {
				$entries[ $context . "\x04" . $json[ $field ] ] = array(
					'msgid'    => $json[ $field ],
					'plural'   => null,
					'context'  => $context,
					'refs'     => array( $relative ),
					'comments' => array(),
				);
			}
		}

		foreach ( $json['keywords'] ?? array() as $keyword ) {
			$entries[ "block keyword\x04" . $keyword ] = array(
				'msgid'    => $keyword,
				'plural'   => null,
				'context'  => 'block keyword',
				'refs'     => array( $relative ),
				'comments' => array(),
			);
		}
	}

	foreach ( TBCARDS_JS_FILES as $js_file ) {
		foreach ( tbcards_js_strings( $root . '/' . $js_file ) as $msgid => $line ) {
			$msgid = (string) $msgid;

			if ( ! isset( $entries[ $msgid ] ) ) {
				$entries[ $msgid ] = array(
					'msgid'    => $msgid,
					'plural'   => null,
					'context'  => null,
					'refs'     => array(),
					'comments' => array(),
				);
			}

			$entries[ $msgid ]['refs'][] = $js_file . ':' . $line;
		}
	}

	$out  = "# Copyright (C) Asymetria\n# This file is distributed under the GPL-2.0-or-later.\n";
	$out .= "msgid \"\"\nmsgstr \"\"\n";
	$out .= "\"Project-Id-Version: Vizito Team Business Cards\\n\"\n\"MIME-Version: 1.0\\n\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\"Content-Transfer-Encoding: 8bit\\n\"\n\"X-Domain: " . TBCARDS_DOMAIN . "\\n\"\n\n";

	foreach ( $entries as $entry ) {
		foreach ( array_keys( $entry['comments'] ) as $comment ) {
			$out .= '#. translators: ' . $comment . "\n";
		}
		$out .= '#: ' . implode( ' ', array_unique( $entry['refs'] ) ) . "\n";
		if ( null !== $entry['context'] ) {
			$out .= 'msgctxt ' . tbcards_po_quote( $entry['context'] ) . "\n";
		}
		$out .= 'msgid ' . tbcards_po_quote( $entry['msgid'] ) . "\n";
		if ( null !== $entry['plural'] ) {
			$out .= 'msgid_plural ' . tbcards_po_quote( $entry['plural'] ) . "\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n\n";
		} else {
			$out .= "msgstr \"\"\n\n";
		}
	}

	if ( ! is_dir( $root . '/languages' ) ) {
		mkdir( $root . '/languages' );
	}

	file_put_contents( $root . '/languages/' . TBCARDS_DOMAIN . '.pot', $out );
	echo count( $entries ) . " strings\n";
	exit( 0 );
}

if ( 'mo' === $command ) {
	foreach ( glob( $root . '/languages/*.po' ) as $po ) {
		$entries = tbcards_po_parse( $po );
		$count   = tbcards_mo_write( $entries, substr( $po, 0, -3 ) . '.mo' );
		echo basename( $po ) . ": $count translated\n";

		$locale  = substr( basename( $po, '.po' ), strlen( TBCARDS_DOMAIN ) + 1 );
		$header  = '';
		$strings = array();

		foreach ( $entries as $entry ) {
			if ( '' === $entry['msgid'] ) {
				$header = $entry['msgstr'][0] ?? '';
			} elseif ( null === $entry['context'] ) {
				$strings[ $entry['msgid'] ] = $entry['msgstr'];
			}
		}

		preg_match( '/Plural-Forms:\s*([^\n]+)/', $header, $plural );

		foreach ( TBCARDS_JS_FILES as $js_file ) {
			$messages = array(
				'' => array(
					'domain'       => 'messages',
					'lang'         => $locale,
					'plural-forms' => trim( $plural[1] ?? 'nplurals=2; plural=(n != 1);' ),
				),
			);

			foreach ( array_keys( tbcards_js_strings( $root . '/' . $js_file ) ) as $msgid ) {
				$msgid = (string) $msgid;

				if ( isset( $strings[ $msgid ] ) && '' !== implode( '', $strings[ $msgid ] ) ) {
					$messages[ $msgid ] = array_values( $strings[ $msgid ] );
				}
			}

			file_put_contents(
				$root . '/languages/' . TBCARDS_DOMAIN . '-' . $locale . '-' . md5( $js_file ) . '.json',
				json_encode(
					array(
						'domain'      => 'messages',
						'locale_data' => array( 'messages' => $messages ),
					),
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
				)
			);
		}
	}
	exit( 0 );
}

if ( 'check' === $command && isset( $argv[2] ) ) {
	$pot     = tbcards_po_parse( $root . '/languages/' . TBCARDS_DOMAIN . '.pot' );
	$po      = tbcards_po_parse( $argv[2] );
	$key     = static function ( $e ) {
		return ( $e['context'] ?? '' ) . "\x04" . $e['msgid'];
	};
	$have    = array();
	$missing = 0;

	foreach ( $po as $e ) {
		if ( '' !== implode( '', $e['msgstr'] ) ) {
			$have[ $key( $e ) ] = true;
		}
	}

	foreach ( $pot as $e ) {
		if ( '' !== $e['msgid'] && ! isset( $have[ $key( $e ) ] ) ) {
			echo 'missing: ' . ( null !== $e['context'] ? '[' . $e['context'] . '] ' : '' ) . $e['msgid'] . "\n";
			$missing++;
		}
	}

	echo "$missing missing\n";
	exit( $missing > 0 ? 1 : 0 );
}

fwrite( STDERR, "Usage: php tools/i18n.php pot|mo|check <file.po>\n" );
exit( 2 );
