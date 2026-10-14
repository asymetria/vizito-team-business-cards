<?php
/**
 * Rebuilds lib/qrcode/qrcode.php from the untouched upstream file.
 *
 * Usage: php tools/vendor-qrcode.php
 *
 * Upstream (qrcode-generator by Kazuhiko Arase, MIT) declares global classes
 * (QRCode, QRUtil, ...) and global constants (QR_PAD0, ...). WordPress.org
 * rejects that, and a second plugin shipping the same file would cause a fatal
 * "cannot redeclare" on a live site. This script:
 *
 *   1. puts the whole file in namespace Asymetria\TeamCards\Vendor\Qr;
 *   2. turns every define("QR_X", expr); into const QR_X = expr; — namespaced
 *      constants, which unqualified references inside the same namespace
 *      resolve to, so no use site needs rewriting;
 *   3. removes createImage(), printHTML() and printSVG(), which draw or print
 *      output this plugin produces itself;
 *   4. keeps the MIT header and adds a note on what was changed.
 *
 * Generating rather than hand-editing makes a newer upstream a one-command job.
 * Dev tool only: not shipped (see .distignore).
 *
 * @package Vizito_Team_Business_Cards
 */

$root     = dirname( __DIR__ );
$upstream = $root . '/tools/upstream/qrcode.php';
$target   = $root . '/lib/qrcode/qrcode.php';
$source   = file_get_contents( $upstream );

if ( false === $source ) {
	fwrite( STDERR, "Cannot read $upstream\n" );
	exit( 1 );
}

$source = str_replace( "\r\n", "\n", $source );

// 1. Namespace, right after the opening tag and the licence header.
$header_end = strpos( $source, "//---------------------------------------------------------------\n// QRCode\n" );

if ( false === $header_end ) {
	fwrite( STDERR, "Upstream layout changed: QRCode banner not found\n" );
	exit( 1 );
}

$note = <<<'NOTE'
//---------------------------------------------------------------
// Vendored into Vizito Team Business Cards by tools/vendor-qrcode.php.
// Changes from upstream: moved into a namespace, global constants turned into
// namespaced const, createImage()/printHTML()/printSVG() removed,
// fatal errors on invalid input turned into a catchable exception.
// Everything else is upstream code, unmodified.
//---------------------------------------------------------------

namespace Asymetria\TeamCards\Vendor\Qr;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


NOTE;

$source = substr( $source, 0, $header_end ) . $note . substr( $source, $header_end );

// 2. define("QR_X", expr); → const QR_X = expr;  (expr may span lines).
$count  = 0;
$source = preg_replace( '/define\(\s*"(QR_[A-Z0-9_]+)"\s*,\s*(.*?)\);/s', 'const $1 = $2;', $source, -1, $count );

if ( $count < 20 ) {
	fwrite( STDERR, "Expected at least 20 define() calls, found $count\n" );
	exit( 1 );
}

// 3. Remove whole methods by brace matching.
foreach ( array( 'createImage', 'printHTML', 'printSVG' ) as $method ) {
	if ( ! preg_match( '/\n[ \t]*(?:\/\/[^\n]*\n[ \t]*)*(?:public\s+)?function\s+' . $method . '\s*\(/', $source, $m, PREG_OFFSET_CAPTURE ) ) {
		fwrite( STDERR, "Method $method not found\n" );
		exit( 1 );
	}

	$start = $m[0][1];
	$open  = strpos( $source, '{', $start );
	$depth = 0;
	$len   = strlen( $source );

	for ( $i = $open; $i < $len; $i++ ) {
		if ( '{' === $source[ $i ] ) {
			$depth++;
		} elseif ( '}' === $source[ $i ] ) {
			$depth--;

			if ( 0 === $depth ) {
				break;
			}
		}
	}

	$source = substr( $source, 0, $start ) . substr( $source, $i + 1 );
}

// 4. trigger_error( "...$var", E_USER_ERROR ) → a catchable exception with a
// fixed message. Upstream uses it for invalid input only, which the plugin
// never passes; as a fatal error it could not be caught, E_USER_ERROR is
// deprecated in PHP 8.4, and Plugin Check flags the interpolated messages as
// unescaped output.
$replaced = 0;

while ( false !== ( $at = strpos( $source, 'trigger_error(' ) ) ) {
	$depth = 0;
	$len   = strlen( $source );

	for ( $i = $at + strlen( 'trigger_error' ); $i < $len; $i++ ) {
		if ( '(' === $source[ $i ] ) {
			$depth++;
		} elseif ( ')' === $source[ $i ] ) {
			$depth--;

			if ( 0 === $depth ) {
				break;
			}
		}
	}

	$source = substr( $source, 0, $at ) . "throw new \\InvalidArgumentException( 'qrcode-generator: invalid input' )" . substr( $source, $i + 1 );
	$replaced++;
}

if ( $replaced < 10 ) {
	fwrite( STDERR, "Expected at least 10 trigger_error() calls, found $replaced\n" );
	exit( 1 );
}

if ( preg_match( '/\bdefine\(|\bprint\(|imagecreate|trigger_error/', $source ) ) {
	fwrite( STDERR, "Leftover define(), print() or GD call after transform\n" );
	exit( 1 );
}

if ( ! is_dir( dirname( $target ) ) ) {
	mkdir( dirname( $target ), 0777, true );
}

file_put_contents( $target, $source );

echo "Wrote $target ($count constants)\n";
echo 'upstream sha256: ' . hash_file( 'sha256', $upstream ) . "\n";
