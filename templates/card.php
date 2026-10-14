<?php
/**
 * The full card page.
 *
 * A complete document on purpose: it does not call wp_head() or wp_footer(),
 * so nothing from the theme or other plugins reaches a scanned card, and it
 * loads in one request plus one small deferred script. A theme can override
 * this file by copying it to your-theme/vizito-team-business-cards/card.php; an
 * override is free to call wp_head() and wp_footer() if it wants them.
 *
 * Everything printed comes from Template_Loader::view().
 *
 * @package Vizito_Team_Business_Cards
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$tbcards_view = \Asymetria\TeamCards\Front\Template_Loader::view();

if ( null === $tbcards_view ) {
	return;
}

?><!doctype html>
<html lang="<?php echo esc_attr( $tbcards_view['lang'] ); ?>" data-tbc-scheme="<?php echo esc_attr( $tbcards_view['scheme'] ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, noarchive">
<meta name="theme-color" content="<?php echo esc_attr( $tbcards_view['accent'] ); ?>">
<title><?php echo esc_html( $tbcards_view['title'] ); ?></title>
<style><?php echo $tbcards_view['vars'] . $tbcards_view['css']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the plugin's own stylesheet and a sanitised colour. ?></style>
</head>
<body class="tbc-body">
<main class="tbc-main">
<?php echo $tbcards_view['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Card_Renderer escapes at the point of output. ?>
</main>
<?php wp_print_scripts( array( \Asymetria\TeamCards\Embed\Embed_Renderer::HANDLE ) ); ?>
</body>
</html>
