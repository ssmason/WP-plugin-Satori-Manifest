<?php
/**
 * Email layout.
 *
 * Table-based, inline-styled HTML that renders in every mail client (email
 * clients ignore stylesheets, so inline styles are required here).
 *
 * Receives $satori_manifest_email from Email_Builder::render():
 *   preheader, heading, paragraphs[], details[{label,value}], button_label, button_url,
 *   footer, site_name, site_url, logo_url, accent, on_accent, dark, support.
 *
 * @package SatoriManifest
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.1.0
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$satori_manifest_e     = $satori_manifest_email;
$satori_manifest_sans  = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
$satori_manifest_serif = "Fraunces,Georgia,'Times New Roman',serif";
?>
<!doctype html>
<html lang="<?php echo esc_attr( substr( get_locale(), 0, 2 ) ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="color-scheme" content="light">
	<title><?php echo esc_html( $satori_manifest_e['heading'] ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f1ec;font-family:<?php echo esc_attr( $satori_manifest_sans ); ?>;color:<?php echo esc_attr( $satori_manifest_e['dark'] ); ?>;">
	<span style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;"><?php echo esc_html( $satori_manifest_e['preheader'] ); ?></span>
	<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f1ec;">
		<tr>
			<td align="center" style="padding:32px 16px;">
				<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:580px;">

					<?php // Card. ?>
					<tr>
						<td style="background:#ffffff;border-radius:16px;border-top:6px solid <?php echo esc_attr( $satori_manifest_e['accent'] ); ?>;overflow:hidden;">
							<table role="presentation" width="100%" cellspacing="0" cellpadding="0">
								<tr>
									<td style="padding:28px 36px 0;">
										<a href="<?php echo esc_url( $satori_manifest_e['site_url'] ); ?>" style="text-decoration:none;color:<?php echo esc_attr( $satori_manifest_e['dark'] ); ?>;">
											<?php if ( '' !== $satori_manifest_e['logo_url'] ) : ?>
												<img src="<?php echo esc_url( $satori_manifest_e['logo_url'] ); ?>" alt="<?php echo esc_attr( $satori_manifest_e['site_name'] ); ?>" height="52" style="display:block;height:52px;width:auto;border:0;">
											<?php else : ?>
												<span style="font-family:<?php echo esc_attr( $satori_manifest_serif ); ?>;font-size:20px;font-weight:600;"><?php echo esc_html( $satori_manifest_e['site_name'] ); ?></span>
											<?php endif; ?>
										</a>
									</td>
								</tr>
								<tr>
									<td style="padding:24px 36px 36px;">
										<h1 style="margin:0 0 16px;font-family:<?php echo esc_attr( $satori_manifest_serif ); ?>;font-size:26px;font-weight:600;line-height:1.25;color:<?php echo esc_attr( $satori_manifest_e['dark'] ); ?>;"><?php echo esc_html( $satori_manifest_e['heading'] ); ?></h1>

										<?php foreach ( $satori_manifest_e['paragraphs'] as $satori_manifest_paragraph ) : ?>
											<p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#3c4a55;"><?php echo wp_kses( nl2br( esc_html( $satori_manifest_paragraph ) ), array( 'br' => array() ) ); ?></p>
										<?php endforeach; ?>

										<?php if ( ! empty( $satori_manifest_e['details'] ) ) : ?>
											<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:8px 0 20px;border:1px solid #ece7df;border-radius:10px;">
												<?php foreach ( $satori_manifest_e['details'] as $satori_manifest_index => $satori_manifest_row ) : ?>
													<tr>
														<td style="padding:10px 14px;<?php echo $satori_manifest_index ? 'border-top:1px solid #ece7df;' : ''; ?>font-size:13px;color:#6b7781;width:38%;vertical-align:top;"><?php echo esc_html( $satori_manifest_row['label'] ); ?></td>
														<td style="padding:10px 14px;<?php echo $satori_manifest_index ? 'border-top:1px solid #ece7df;' : ''; ?>font-size:14px;font-weight:600;color:<?php echo esc_attr( $satori_manifest_e['dark'] ); ?>;word-break:break-word;"><?php echo esc_html( $satori_manifest_row['value'] ); ?></td>
													</tr>
												<?php endforeach; ?>
											</table>
										<?php endif; ?>

										<?php if ( '' !== $satori_manifest_e['button_url'] ) : ?>
											<table role="presentation" cellspacing="0" cellpadding="0" style="margin:24px 0 4px;">
												<tr>
													<td style="border-radius:999px;background:<?php echo esc_attr( $satori_manifest_e['dark'] ); ?>;">
														<a href="<?php echo esc_url( $satori_manifest_e['button_url'] ); ?>" style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:999px;"><?php echo esc_html( $satori_manifest_e['button_label'] ); ?> &rarr;</a>
													</td>
												</tr>
											</table>
											<p style="margin:12px 0 0;font-size:12px;line-height:1.5;color:#8a949c;">
												<?php esc_html_e( 'Button not working? Copy this link into your browser:', 'satori-manifest' ); ?><br>
												<a href="<?php echo esc_url( $satori_manifest_e['button_url'] ); ?>" style="color:#8a949c;word-break:break-all;"><?php echo esc_html( $satori_manifest_e['button_url'] ); ?></a>
											</p>
										<?php endif; ?>
									</td>
								</tr>
							</table>
						</td>
					</tr>

					<?php // Footer. ?>
					<tr>
						<td style="padding:24px 16px 0;font-size:12px;line-height:1.7;color:#8a949c;text-align:center;">
							<?php if ( '' !== $satori_manifest_e['footer'] ) : ?>
								<?php echo esc_html( $satori_manifest_e['footer'] ); ?><br>
							<?php endif; ?>
							<?php esc_html_e( 'Questions? Just reply, or email', 'satori-manifest' ); ?>
							<a href="<?php echo esc_url( 'mailto:' . $satori_manifest_e['support'] ); ?>" style="color:#8a949c;"><?php echo esc_html( $satori_manifest_e['support'] ); ?></a><br>
							<a href="<?php echo esc_url( $satori_manifest_e['site_url'] ); ?>" style="color:<?php echo esc_attr( $satori_manifest_e['dark'] ); ?>;font-weight:600;text-decoration:none;"><?php echo esc_html( $satori_manifest_e['site_name'] ); ?></a>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
