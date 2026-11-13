<?php
/**
 * View: AI assistant screen (clients).
 *
 * Receives from Connect_Screen::render():
 *   $satori_manifest_allowed, $satori_manifest_token, $satori_manifest_new_link,
 *   $satori_manifest_changes, $satori_manifest_support, $satori_manifest_site,
 *   $satori_manifest_action, $satori_manifest_notice.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.9.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SatoriManifest\Core\Security;

$satori_manifest_date_fmt = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
$satori_manifest_notices  = array(
	'connected'    => __( 'Your private link is ready. Copy it now: it is only shown once.', 'satori-manifest' ),
	'disconnected' => __( 'Disconnected. AI apps using the old link can no longer change your page.', 'satori-manifest' ),
	'undone'       => __( 'The last change has been undone.', 'satori-manifest' ),
	'nothing'      => __( 'There was nothing to undo.', 'satori-manifest' ),
);
$satori_manifest_examples = array(
	__( '"Here\'s a photo of our menu. Set up my page from it."', 'satori-manifest' ),
	__( '"Guinness is now €5.50 and Heineken €4.80."', 'satori-manifest' ),
	__( '"Put all cocktails up 5%, rounded to 10 cents. Show me first."', 'satori-manifest' ),
	__( '"Add a Sunday roast section at the top: beef €16.50, chicken €14.50."', 'satori-manifest' ),
	__( '"Mark the lamb shank as sold out."', 'satori-manifest' ),
	__( '"Which design would suit an Irish bar? Switch to it in dark."', 'satori-manifest' ),
	__( '"Change our opening hours to 10am to midnight, every day."', 'satori-manifest' ),
	__( '"Check my price list for typos and missing prices."', 'satori-manifest' ),
	__( '"Undo that."', 'satori-manifest' ),
);
?>
<div class="wrap sm-admin sm-ai-screen">
	<header class="sm-page-header">
		<div>
			<p class="sm-eyebrow"><?php esc_html_e( 'Your page', 'satori-manifest' ); ?></p>
			<h1 class="sm-page-header__title"><?php esc_html_e( 'AI assistant', 'satori-manifest' ); ?></h1>
			<p class="sm-page-header__lead"><?php esc_html_e( 'Set up and update your price list by asking your AI app (Claude, ChatGPT and others) in your own words, or by showing it a photo of your menu.', 'satori-manifest' ); ?></p>
		</div>
	</header>

	<?php if ( isset( $satori_manifest_notices[ $satori_manifest_notice ] ) && 'connected' !== $satori_manifest_notice ) : ?>
		<div class="sm-notice sm-notice--info"><?php echo esc_html( $satori_manifest_notices[ $satori_manifest_notice ] ); ?></div>
	<?php endif; ?>

	<?php if ( ! $satori_manifest_allowed ) : ?>
		<section class="sm-panel sm-ai-upsell">
			<h2 class="sm-panel__title"><?php esc_html_e( 'Look after your page by just asking', 'satori-manifest' ); ?></h2>
			<ul class="sm-ai-list">
				<li><?php esc_html_e( 'Snap your menu and your whole page is set up in a minute.', 'satori-manifest' ); ?></li>
				<li><?php esc_html_e( 'Change prices, add specials or mark items sold out by typing one line.', 'satori-manifest' ); ?></li>
				<li><?php esc_html_e( 'Price rises across the whole list, previewed before anything changes.', 'satori-manifest' ); ?></li>
				<li><?php esc_html_e( 'Every change can be undone.', 'satori-manifest' ); ?></li>
			</ul>
			<p class="sm-panel__intro">
				<?php esc_html_e( 'The AI assistant is not part of your plan yet. To add it, email us:', 'satori-manifest' ); ?>
				<a href="<?php echo esc_url( 'mailto:' . $satori_manifest_support ); ?>"><?php echo esc_html( $satori_manifest_support ); ?></a>
			</p>
		</section>
	<?php else : ?>
		<div class="sm-two-col">
			<div>
				<section class="sm-panel">
					<h2 class="sm-panel__title"><?php esc_html_e( '1. Get your private link', 'satori-manifest' ); ?></h2>

					<?php if ( '' !== $satori_manifest_new_link ) : ?>
						<div class="sm-notice sm-notice--warning"><?php echo esc_html( $satori_manifest_notices['connected'] ); ?></div>
						<div class="sm-ai-link">
							<input id="sm-ai-link" class="sm-ai-link__input" type="text" readonly value="<?php echo esc_attr( $satori_manifest_new_link ); ?>" aria-label="<?php esc_attr_e( 'Your private link', 'satori-manifest' ); ?>">
							<div class="sm-ai-link__actions">
								<button type="button" class="button button-primary" data-sm-copy="#sm-ai-link"><?php esc_html_e( 'Copy link', 'satori-manifest' ); ?></button>
								<span class="sm-ai-link__status" data-sm-copy-status aria-live="polite"></span>
							</div>
						</div>
						<p class="sm-ai-small"><?php esc_html_e( 'Keep it private, like a password: anyone with this link can change your price list. If it is ever shared by mistake, create a new link and the old one stops working straight away.', 'satori-manifest' ); ?></p>
					<?php elseif ( null !== $satori_manifest_token ) : ?>
						<p>
							<span class="sm-pill sm-pill--on"><?php esc_html_e( 'Connected', 'satori-manifest' ); ?></span>
							<span class="sm-ai-small">
								<?php
								echo esc_html(
									sprintf(
										/* translators: 1: date the link was created, 2: date last used or "not used yet". */
										__( 'Link created %1$s · last used %2$s', 'satori-manifest' ),
										wp_date( $satori_manifest_date_fmt, $satori_manifest_token['created'] ),
										$satori_manifest_token['last_used'] ? wp_date( $satori_manifest_date_fmt, $satori_manifest_token['last_used'] ) : __( 'not yet', 'satori-manifest' )
									)
								);
								?>
							</span>
						</p>
						<p class="sm-ai-small"><?php esc_html_e( 'For your security the link is only shown when it is created. Lost it, or want to connect another app? Create a new one (the old link stops working).', 'satori-manifest' ); ?></p>
						<div class="sm-ai-buttons">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php Security::output_form_nonce( $satori_manifest_action ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( $satori_manifest_action ); ?>">
								<input type="hidden" name="task" value="connect">
								<button type="submit" class="button"><?php esc_html_e( 'Create a new link', 'satori-manifest' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php Security::output_form_nonce( $satori_manifest_action ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( $satori_manifest_action ); ?>">
								<input type="hidden" name="task" value="disconnect">
								<button type="submit" class="button"><?php esc_html_e( 'Disconnect', 'satori-manifest' ); ?></button>
							</form>
						</div>
					<?php else : ?>
						<p class="sm-panel__intro"><?php esc_html_e( 'Your link lets your AI app read and update your price list, and nothing else.', 'satori-manifest' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<?php Security::output_form_nonce( $satori_manifest_action ); ?>
							<input type="hidden" name="action" value="<?php echo esc_attr( $satori_manifest_action ); ?>">
							<input type="hidden" name="task" value="connect">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Create my private link', 'satori-manifest' ); ?></button>
						</form>
					<?php endif; ?>

					<h2 class="sm-panel__title sm-panel__title--spaced"><?php esc_html_e( '2. Add it to your AI app', 'satori-manifest' ); ?></h2>
					<p><?php esc_html_e( 'In your AI app, add a custom connector (it may be called an "MCP server") and paste your link as its address. Claude, ChatGPT and other apps that support connectors can use it; their help pages show where to add one.', 'satori-manifest' ); ?></p>
					<details class="sm-ai-details">
						<summary><?php esc_html_e( 'Using Claude Code or another developer tool?', 'satori-manifest' ); ?></summary>
						<p><?php esc_html_e( 'Add it as an HTTP MCP server, for example:', 'satori-manifest' ); ?></p>
						<code class="sm-ai-code">claude mcp add --transport http <?php echo esc_html( sanitize_title( $satori_manifest_site ) ); ?> <?php esc_html_e( 'YOUR-LINK', 'satori-manifest' ); ?></code>
					</details>

					<h2 class="sm-panel__title sm-panel__title--spaced"><?php esc_html_e( '3. Just ask', 'satori-manifest' ); ?></h2>
					<ul class="sm-ai-examples">
						<?php foreach ( $satori_manifest_examples as $satori_manifest_example ) : ?>
							<li><?php echo esc_html( $satori_manifest_example ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			</div>

			<aside class="sm-panel">
				<h2 class="sm-panel__title"><?php esc_html_e( 'What it can do', 'satori-manifest' ); ?></h2>
				<ul class="sm-ai-list">
					<li><?php esc_html_e( 'Read and change your price list: sections, items, prices, descriptions and badges.', 'satori-manifest' ); ?></li>
					<li><?php esc_html_e( 'Change your page details: name, tagline, opening hours, address, phone, links.', 'satori-manifest' ); ?></li>
					<li><?php esc_html_e( 'Switch your design, light or dark, or load a ready-made layout.', 'satori-manifest' ); ?></li>
					<li><?php esc_html_e( 'Tell you how many people visited and scanned your code.', 'satori-manifest' ); ?></li>
				</ul>
				<h2 class="sm-panel__title sm-panel__title--spaced"><?php esc_html_e( 'What it can\'t do', 'satori-manifest' ); ?></h2>
				<ul class="sm-ai-list sm-ai-list--no">
					<li><?php esc_html_e( 'See or change your billing, password or account.', 'satori-manifest' ); ?></li>
					<li><?php esc_html_e( 'Touch anything outside your own page.', 'satori-manifest' ); ?></li>
					<li><?php esc_html_e( 'Upload photos or your logo (use Branding for those).', 'satori-manifest' ); ?></li>
				</ul>
				<p class="sm-ai-small"><?php esc_html_e( 'Changes go live straight away and are listed below, so you can always undo them. Your AI app may ask you to approve each change first.', 'satori-manifest' ); ?></p>
			</aside>
		</div>
	<?php endif; ?>

	<?php if ( array() !== $satori_manifest_changes ) : ?>
		<section class="sm-panel sm-section-gap">
			<div class="sm-ai-changes__head">
				<h2 class="sm-panel__title"><?php esc_html_e( 'Recent changes by your AI app', 'satori-manifest' ); ?></h2>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php Security::output_form_nonce( $satori_manifest_action ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( $satori_manifest_action ); ?>">
					<input type="hidden" name="task" value="undo">
					<button type="submit" class="button"><?php esc_html_e( 'Undo the last change', 'satori-manifest' ); ?></button>
				</form>
			</div>
			<table class="sm-table sm-table--compact">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Change', 'satori-manifest' ); ?></th>
						<th scope="col"><?php esc_html_e( 'When', 'satori-manifest' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $satori_manifest_changes as $satori_manifest_change ) : ?>
						<tr class="<?php echo $satori_manifest_change['undone'] ? 'is-undone' : ''; ?>">
							<td>
								<?php echo esc_html( $satori_manifest_change['summary'] ); ?>
								<?php if ( $satori_manifest_change['undone'] ) : ?>
									<span class="sm-pill sm-pill--test"><?php esc_html_e( 'Undone', 'satori-manifest' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( wp_date( $satori_manifest_date_fmt, $satori_manifest_change['time'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</section>
	<?php endif; ?>
</div>
