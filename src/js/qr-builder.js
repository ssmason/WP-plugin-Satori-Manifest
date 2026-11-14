/**
 * Satori Manifest - QR builder: live preview of the printable QR sign.
 *
 * Both sizes are rendered by the server (Qr_Sign::svg()). This script only
 * switches between them and mirrors the form into the preview as the client
 * types: text (shrunk to fit like the PDF), the small sign's red last word
 * and the large sign's colour. Saving is a normal form post.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.11.0
 */

(function () {
	'use strict';

	const root = document.querySelector('[data-sm-qr-builder]');

	if (!root) {
		return;
	}

	const svgNs = 'http://www.w3.org/2000/svg';
	const unsaved = root.querySelector('[data-sm-sign-unsaved]');
	const inputs = {
		title: root.querySelector('[data-sm-sign-input="title"]'),
		subtitle: root.querySelector('[data-sm-sign-input="subtitle"]'),
		colour: root.querySelector('[data-sm-sign-input="colour"]'),
	};

	/**
	 * The size currently chosen.
	 *
	 * @return {string} 'large' or 'small'.
	 */
	const size = () =>
		root.querySelector('input[name="size"]:checked')?.value || 'large';

	/**
	 * Shrinks a text element until it fits its width (as the PDF does).
	 *
	 * @param {SVGTextElement} text Text element with data-sm-sign-max/-size.
	 * @return {void}
	 */
	function fit(text) {
		const max = parseFloat(text.dataset.smSignMax);
		const base = parseFloat(text.dataset.smSignSize);

		text.setAttribute('font-size', base);

		const width = text.getComputedTextLength();

		if (width > max) {
			text.setAttribute(
				'font-size',
				Math.max(base * 0.45, (base * max) / width).toFixed(2)
			);
		}
	}

	/**
	 * Writes the top text; on the small sign the last word is coloured.
	 *
	 * @param {SVGTextElement} text  Title element.
	 * @param {string}         value Text.
	 * @return {void}
	 */
	function setTitle(text, value) {
		const words = value.trim().split(/\s+/);

		if (!('smSignSplit' in text.dataset) || words.length < 2) {
			text.textContent = value;
			return;
		}

		const last = document.createElementNS(svgNs, 'tspan');
		const colour = text.querySelector('tspan')?.getAttribute('fill');

		last.textContent = words.pop();
		last.setAttribute('fill', colour || '#c8102e');
		text.replaceChildren(
			document.createTextNode(words.join(' ') + ' '),
			last
		);
	}

	/**
	 * Mirrors the form into both previews.
	 *
	 * @return {void}
	 */
	function update() {
		const chosen = size();
		const fallback = 'small' === chosen ? 'Our Menu' : '';

		root.querySelectorAll('[data-sm-preview]').forEach((preview) => {
			const isChosen = preview.dataset.smPreview === chosen;
			const title = preview.querySelector('[data-sm-sign-title]');
			const subtitle = preview.querySelector('[data-sm-sign-subtitle]');

			preview.hidden = !isChosen;

			if (title && inputs.title) {
				setTitle(
					title,
					inputs.title.value || fallback || title.textContent
				);
			}

			if (subtitle && inputs.subtitle) {
				subtitle.textContent = inputs.subtitle.value;
			}

			if ('large' === preview.dataset.smPreview && inputs.colour) {
				preview
					.querySelectorAll('[data-sm-sign-colour="fill"]')
					.forEach((element) =>
						element.setAttribute('fill', inputs.colour.value)
					);
			}

			// Hidden SVGs cannot be measured; fit the visible one.
			if (isChosen) {
				preview
					.querySelectorAll('[data-sm-sign-max]')
					.forEach((text) => fit(text));
			}
		});

		root.querySelectorAll('[data-sm-only]').forEach((element) => {
			element.hidden = element.dataset.smOnly !== chosen;
		});
	}

	root.addEventListener('input', () => {
		update();

		if (unsaved) {
			unsaved.hidden = false;
		}
	});

	update();
})();
