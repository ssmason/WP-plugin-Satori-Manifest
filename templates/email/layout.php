<?php
/**
 * Email layout.
 *
 * Table-based, inline-styled HTML that renders in every mail client (email
 * clients ignore stylesheets, so inline styles are required here).
 * Receives $satori_manifest_email from Mailer::build():
 *   preheader, heading, paragraphs[], button_label, button_url, footer, site_name, site_url.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_e = $satori_manifest_email;
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $satori_manifest_e['heading'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2340;">
	<span style="display:none;max-height:0;overflow:hidden;opacity:0;"><?php echo esc_html( $satori_manifest_e['preheader'] ); ?></span>
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f7;">
		<tr>
			<td align="center" style="padding:32px 16px;">
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;">
					<tr>
						<td style="padding:0 0 16px;font-size:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#4f46e5;">
							<?php echo esc_html( $satori_manifest_e['site_name'] ); ?>
						</td>
					</tr>
					<tr>
						<td style="background:#ffffff;border-radius:14px;padding:36px 32px;box-shadow:0 1px 3px rgba(31,35,64,.08);">
							<h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;color:#1f2340;"><?php echo esc_html( $satori_manifest_e['heading'] ); ?></h1>
							<?php foreach ( $satori_manifest_e['paragraphs'] as $satori_manifest_paragraph ) : ?>
								<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#3c4257;"><?php echo wp_kses( nl2br( esc_html( $satori_manifest_paragraph ) ), array( 'br' => array() ) ); ?></p>
							<?php endforeach; ?>
							<?php if ( '' !== $satori_manifest_e['button_url'] ) : ?>
								<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0 8px;">
									<tr>
										<td style="border-radius:8px;background:#4f46e5;">
											<a href="<?php echo esc_url( $satori_manifest_e['button_url'] ); ?>" style="display:inline-block;padding:12px 22px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;"><?php echo esc_html( $satori_manifest_e['button_label'] ); ?></a>
										</td>
									</tr>
								</table>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td style="padding:20px 8px 0;font-size:12px;line-height:1.6;color:#8a8fa3;text-align:center;">
							<?php echo esc_html( $satori_manifest_e['footer'] ); ?><br>
							<a href="<?php echo esc_url( $satori_manifest_e['site_url'] ); ?>" style="color:#8a8fa3;"><?php echo esc_html( $satori_manifest_e['site_name'] ); ?></a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
