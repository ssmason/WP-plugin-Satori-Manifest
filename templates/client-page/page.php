<?php
/**
 * Template: client price-list page.
 *
 * A complete, theme-independent document. Header and footer are rendered
 * from the client's brand settings; the page content (including the
 * Manifest block) is edited in the block editor.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_page_id = \SatoriManifest\Frontend\Client_Page::current_page_id();
$satori_manifest_brand   = \SatoriManifest\Branding\Brand::for_page(
	$satori_manifest_page_id,
	\SatoriManifest\Frontend\Client_Page::design_for_request( $satori_manifest_page_id )
);
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="sm-skip-link" href="#sm-main"><?php esc_html_e( 'Skip to price list', 'satori-manifest' ); ?></a>

<div class="sm-site">
	<?php
	// Embedded on the client's own website: their site supplies the header and footer.
	$satori_manifest_embedded = \SatoriManifest\Frontend\Embed::is_active();

	if ( ! $satori_manifest_embedded && 'hidden' !== $satori_manifest_brand['header']['layout'] ) {
		require __DIR__ . '/header.php';
	}
	?>

	<main id="sm-main" class="sm-main">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</main>

	<?php
	if ( ! $satori_manifest_embedded ) {
		require __DIR__ . '/footer.php';
	}
	?>
</div>

<?php wp_footer(); ?>
</body>
</html>
