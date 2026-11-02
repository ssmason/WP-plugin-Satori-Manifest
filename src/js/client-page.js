/**
 * Satori Manifest — client page behaviour.
 *
 * Mobile navigation toggle for the client page header. Progressive
 * enhancement: without JavaScript the navigation is simply always visible.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

(function () {
	'use strict';

	const toggle = document.querySelector('.sm-nav-toggle');
	const header = document.querySelector('.sm-header');

	if (!toggle || !header) {
		return;
	}

	header.classList.add('has-js');

	/**
	 * Opens or closes the navigation.
	 *
	 * @param {boolean} open Desired state.
	 * @return {void}
	 */
	function setOpen(open) {
		header.classList.toggle('is-nav-open', open);
		toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	toggle.addEventListener('click', () =>
		setOpen('true' !== toggle.getAttribute('aria-expanded'))
	);

	document.addEventListener('keydown', (event) => {
		if (
			'Escape' === event.key &&
			header.classList.contains('is-nav-open')
		) {
			setOpen(false);
			toggle.focus();
		}
	});

	// Close after choosing a same-page link.
	header.querySelector('.sm-nav')?.addEventListener('click', (event) => {
		if (event.target.closest('a')) {
			setOpen(false);
		}
	});
})();

/*
 * Visit counting. Reports one visit per page load to the plugin (see
 * Stats\View_Counter), marking QR code scans (?src=qr), then tidies the
 * address so a shared link does not count as a scan. Nothing is sent for
 * the page's owner or staff (no config is printed for them).
 */
(function () {
	'use strict';

	const stats = window.satoriManifestStats;

	if (!stats || !stats.url) {
		return;
	}

	const params = new URLSearchParams(window.location.search);
	const body = new URLSearchParams({
		page: String(stats.page),
		source: params.get(stats.sourceVar) || '',
	});

	if (navigator.sendBeacon) {
		navigator.sendBeacon(stats.url, body);
	} else {
		window.fetch(stats.url, { method: 'POST', body, keepalive: true });
	}

	if (params.has(stats.sourceVar) && window.history.replaceState) {
		params.delete(stats.sourceVar);
		const query = params.toString();
		window.history.replaceState(
			null,
			'',
			window.location.pathname +
				(query ? '?' + query : '') +
				window.location.hash
		);
	}
})();
