<?php
/**
 * Draws the WordPress.org directory icon and banner as SVG, with real QR
 * codes from the bundled library, plus HTML wrappers a headless browser can
 * screenshot to PNG.
 *
 * Usage: php tools/wporg-assets.php <output-dir>
 * Then, per file: chrome --headless=new --hide-scrollbars --window-size=W,H
 *   --screenshot=<dir>/<name>.png file:///<dir>/<name>.html
 *
 * Dev tool only: not shipped (see .distignore).
 *
 * @package Vizito_Team_Business_Cards
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'TBCARDS_DIR', dirname( __DIR__ ) . '/' );

require TBCARDS_DIR . 'lib/qrcode/qrcode.php';
require TBCARDS_DIR . 'includes/qr/class-qr-service.php';

$out = rtrim( $argv[1] ?? '', '/\\' );

if ( '' === $out || ! is_dir( $out ) ) {
	fwrite( STDERR, "Usage: php tools/wporg-assets.php <existing-output-dir>\n" );
	exit( 1 );
}

$qr = new \Asymetria\TeamCards\Qr\Qr_Service();

/**
 * QR modules as one SVG path, placed at ($x, $y), $size wide, in $color.
 */
function vizito_qr_path( array $matrix, float $x, float $y, float $size, string $color ): string {
	$n    = count( $matrix );
	$cell = $size / $n;
	$d    = '';

	foreach ( $matrix as $r => $row ) {
		foreach ( $row as $c => $on ) {
			if ( $on ) {
				$d .= sprintf( 'M%.2f %.2fh%.2fv%.2fh-%.2fz', $x + $c * $cell, $y + $r * $cell, $cell, $cell, $cell );
			}
		}
	}

	return '<path fill="' . $color . '" d="' . $d . '"/>';
}

$defs = '<defs>'
	. '<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1e40af"/><stop offset="1" stop-color="#0b1b4d"/></linearGradient>'
	. '<linearGradient id="av" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#93c5fd"/><stop offset="1" stop-color="#3b82f6"/></linearGradient>'
	. '<filter id="sh" x="-20%" y="-20%" width="140%" height="160%"><feDropShadow dx="0" dy="6" stdDeviation="8" flood-color="#020617" flood-opacity=".45"/></filter>'
	. '</defs>';

// Icon: a business card with a photo, two text lines and a QR code.
$small = $qr->matrix( 'VIZITO', 'L' );
$icon  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256" width="256" height="256">' . $defs
	. '<rect width="256" height="256" rx="0" fill="url(#bg)"/>'
	. '<g filter="url(#sh)"><rect x="28" y="62" width="200" height="132" rx="16" fill="#ffffff"/></g>'
	. '<circle cx="72" cy="106" r="24" fill="url(#av)"/>'
	. '<rect x="48" y="146" width="84" height="12" rx="6" fill="#0f172a"/>'
	. '<rect x="48" y="166" width="58" height="9" rx="4.5" fill="#94a3b8"/>'
	. '<rect x="140" y="98" width="72" height="72" rx="6" fill="#eff6ff"/>'
	. vizito_qr_path( $small, 146, 104, 60, '#1e3a8a' )
	. '</svg>';

// Banner: name and tagline on the left, a card with a working QR on the right.
$link   = $qr->matrix( 'https://wordpress.org/plugins/vizito-team-business-cards/', 'M' );
$banner = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1544 500" width="1544" height="500">' . $defs
	. '<rect width="1544" height="500" fill="url(#bg)"/>'
	. '<circle cx="1380" cy="-40" r="260" fill="#3b82f6" opacity=".18"/>'
	. '<circle cx="1000" cy="560" r="220" fill="#60a5fa" opacity=".12"/>'
	. '<text x="96" y="190" font-family="Segoe UI, system-ui, sans-serif" font-size="112" font-weight="700" fill="#ffffff" letter-spacing="-2">Vizito</text>'
	. '<text x="100" y="256" font-family="Segoe UI, system-ui, sans-serif" font-size="44" font-weight="600" fill="#bfdbfe">Team Business Cards</text>'
	. '<text x="100" y="322" font-family="Segoe UI, system-ui, sans-serif" font-size="28" fill="#dbeafe">QR codes for print · one-tap “save contact” · your own domain</text>'
	. '<g filter="url(#sh)"><rect x="904" y="70" width="540" height="340" rx="28" fill="#ffffff"/></g>'
	. '<circle cx="1000" cy="168" r="56" fill="url(#av)"/>'
	. '<rect x="944" y="250" width="200" height="24" rx="12" fill="#0f172a"/>'
	. '<rect x="944" y="290" width="140" height="18" rx="9" fill="#94a3b8"/>'
	. '<rect x="944" y="336" width="96" height="40" rx="20" fill="#1d4ed8"/>'
	. '<rect x="1052" y="336" width="96" height="40" rx="20" fill="#dbeafe"/>'
	. '<rect x="1196" y="110" width="212" height="212" rx="14" fill="#eff6ff"/>'
	. vizito_qr_path( $link, 1210, 124, 184, '#0f172a' )
	. '</svg>';

$files = array(
	'icon-256x256'     => array( $icon, 256, 256 ),
	'icon-128x128'     => array( $icon, 128, 128 ),
	'banner-1544x500'  => array( $banner, 1544, 500 ),
	'banner-772x250'   => array( $banner, 772, 250 ),
);

foreach ( $files as $name => list( $svg, $w, $h ) ) {
	file_put_contents(
		"$out/$name.html",
		'<!doctype html><meta charset="utf-8"><style>html,body{margin:0;width:' . $w . 'px;height:' . $h . 'px;overflow:hidden}svg{display:block;width:' . $w . 'px;height:' . $h . 'px}</style>' . $svg
	);
}

file_put_contents( "$out/icon.svg", $icon );

echo "Wrote icon.svg and 4 HTML wrappers to $out\n";
