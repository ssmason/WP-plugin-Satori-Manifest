<?php
/**
 * Template: public site page (home, register).
 *
 * Theme-independent document: site header, block content, site footer.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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

<a class="sm-skip-link" href="#sm-main"><?php esc_html_e( 'Skip to content', 'satori-manifest' ); ?></a>

<div class="sm-site">
	<?php require __DIR__ . '/parts/site-header.php'; ?>

	<main id="sm-main" class="sm-main">
		<?php
		while ( have_posts() ) :
			the_post();
			the_content();
		endwhile;
		?>
	</main>

	<?php require __DIR__ . '/parts/site-footer.php'; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
