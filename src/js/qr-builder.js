/**
 * Satori Manifest - QR builder: live preview of the printable QR sign.
 *
 * The server draws the sign (Qr_Sign::svg()), so the preview matches the
 * PDF exactly. As the client changes the form, the form is posted to the
 * preview endpoint (not saved) and the chosen size's preview is replaced.
 * Saving is a normal form post.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

(function () {
	'use strict';

	const root = document.querySelector('[data-sm-qr-builder]');
	const form = root?.querySelector('form');

	if (!root || !form) {
		return;
	}

	const unsaved = root.querySelector('[data-sm-sign-unsaved]');
	let timer = 0;
	let request = 0;

	/**
	 * The size currently chosen.
	 *
	 * @return {string} 'large' or 'small'.
	 */
	const size = () =>
		form.querySelector('input[name="size"]:checked')?.value || 'large';

	/**
	 * Shows the chosen size's fields and preview.
	 *
	 * @return {void}
	 */
	function toggle() {
		const chosen = size();

		root.querySelectorAll('[data-sm-preview]').forEach((preview) => {
			preview.hidden = preview.dataset.smPreview !== chosen;
		});
		root.querySelectorAll('[data-sm-only]').forEach((element) => {
			element.hidden = element.dataset.smOnly !== chosen;
		});
	}

	/**
	 * Fetches the sign for the form as it stands and shows it.
	 *
	 * @return {Promise<void>} Resolves when drawn (or ignored when stale).
	 */
	async function refresh() {
		const data = new FormData(form);
		const mine = ++request;
		const target = root.querySelector(`[data-sm-preview="${size()}"]`);

		data.set('action', root.dataset.smPreviewAction);

		try {
			const response = await fetch(root.dataset.smPreviewUrl, {
				method: 'POST',
				body: data,
				credentials: 'same-origin',
			});
			const json = await response.json();

			if (mine === request && json.success && target) {
				target.innerHTML = json.data.svg;
			}
		} catch (error) {
			// Keep the last preview; saving still works.
		}
	}

	form.addEventListener('input', () => {
		toggle();

		if (unsaved) {
			unsaved.hidden = false;
		}

		window.clearTimeout(timer);
		timer = window.setTimeout(refresh, 250);
	});

	toggle();
})();
