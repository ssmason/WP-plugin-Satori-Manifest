/**
 * Satori Manifest — embed mode.
 *
 * Runs inside the iframe when a client page is shown on another website
 * (?sm_embed=1). Tells the host page how tall the content is, so the embed
 * snippet can resize the frame; re-reports whenever the content changes size.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.1.0
 */

(function () {
	'use strict';

	const config = window.satoriManifestEmbed || {};

	if (!config.type || window.parent === window) {
		return;
	}

	/**
	 * Posts the current document height to the host page.
	 *
	 * The height is not sensitive, so any host may receive it; the snippet on
	 * the host side checks that the message came from this site.
	 */
	function report() {
		window.parent.postMessage(
			{
				type: config.type,
				height: document.documentElement.scrollHeight,
			},
			'*'
		);
	}

	window.addEventListener('load', report);

	if ('ResizeObserver' in window) {
		new window.ResizeObserver(report).observe(document.body);
	} else {
		window.addEventListener('resize', report);
	}

	report();
})();
