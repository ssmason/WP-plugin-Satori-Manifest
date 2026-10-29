<?php
/**
 * View: Help screen.
 *
 * Receives from Help_Screen::render():
 *   $satori_manifest_help, $satori_manifest_support, $satori_manifest_page_url.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Branding\Icons;
?>
<div class="wrap sm-admin sm-help-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Help', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php echo esc_html( $satori_manifest_help['title'] ); ?></h1>
			<p class="sm-page-header__lead"><?php echo esc_html( $satori_manifest_help['intro'] ); ?></p>
		</div>
		<?php if ( '' !== $satori_manifest_page_url ) : ?>
			<a class="button" href="<?php echo esc_url( $satori_manifest_page_url ); ?>" target="_blank" rel="noopener"><?php Icons::render( 'eye', 16 ); ?> <?php esc_html_e( 'View my page', 'satori-manifest' ); ?></a>
		<?php endif; ?>
	</header>

	<div class="sm-two-col sm-two-col--wide-left">
		<ol class="sm-guide">
			<?php foreach ( $satori_manifest_help['steps'] as $satori_manifest_index => $satori_manifest_step ) : ?>
				<li class="sm-guide__step">
					<span class="sm-guide__number" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $satori_manifest_index + 1 ) ); ?></span>
					<div class="sm-guide__body">
						<h2 class="sm-guide__title"><?php echo esc_html( $satori_manifest_step['title'] ); ?></h2>
						<p class="sm-guide__text"><?php echo esc_html( $satori_manifest_step['text'] ); ?></p>
						<?php if ( '' !== $satori_manifest_step['url'] ) : ?>
							<a class="sm-guide__link" href="<?php echo esc_url( $satori_manifest_step['url'] ); ?>"><?php esc_html_e( 'Open', 'satori-manifest' ); ?> <?php Icons::render( 'arrow', 14 ); ?></a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>

		<aside class="sm-help-aside">
			<section class="sm-panel">
				<h2 class="sm-panel__title"><?php esc_html_e( 'Need a hand?', 'satori-manifest' ); ?></h2>
				<p class="sm-panel__intro"><?php esc_html_e( 'Email us and a real person will reply, usually the same day.', 'satori-manifest' ); ?></p>
				<a class="button button-primary" href="<?php echo esc_url( 'mailto:' . $satori_manifest_support ); ?>"><?php echo esc_html( $satori_manifest_support ); ?></a>
				<p class="sm-field__hint"><?php esc_html_e( 'Tip: every screen has a Help button at the top right with a short explanation.', 'satori-manifest' ); ?></p>
			</section>

			<?php if ( $satori_manifest_help['faq'] ) : ?>
				<section class="sm-panel">
					<h2 class="sm-panel__title"><?php esc_html_e( 'Questions', 'satori-manifest' ); ?></h2>
					<?php foreach ( $satori_manifest_help['faq'] as $satori_manifest_faq ) : ?>
						<details class="sm-faq">
							<summary><?php echo esc_html( $satori_manifest_faq['q'] ); ?></summary>
							<p><?php echo esc_html( $satori_manifest_faq['a'] ); ?></p>
						</details>
					<?php endforeach; ?>
				</section>
			<?php endif; ?>

			<?php if ( $satori_manifest_help['resources'] ) : ?>
				<section class="sm-panel">
					<h2 class="sm-panel__title"><?php esc_html_e( 'Good to know', 'satori-manifest' ); ?></h2>
					<ul class="sm-links">
						<?php foreach ( $satori_manifest_help['resources'] as $satori_manifest_link ) : ?>
							<li><a href="<?php echo esc_url( $satori_manifest_link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $satori_manifest_link['label'] ); ?> <span aria-hidden="true">↗</span><span class="screen-reader-text"><?php esc_html_e( '(opens in a new window)', 'satori-manifest' ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</aside>
	</div>
</div>
