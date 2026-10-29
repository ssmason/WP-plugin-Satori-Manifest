/**
 * Satori Manifest — Settings screen.
 *
 * Wires the portal logo picker to the WordPress media library.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.0.0
 */

document.querySelectorAll('[data-sm-logo-picker]').forEach((picker) => {
	const input = picker.querySelector('[data-sm-logo-id]');
	const preview = picker.querySelector('[data-sm-logo-preview]');
	let frame = null;

	/**
	 * Shows the chosen image (or the "site logo" note) in the preview box.
	 *
	 * @param {string} url Image URL, or '' to clear.
	 */
	const show = (url) => {
		preview.textContent = '';

		if (url) {
			const img = document.createElement('img');
			img.src = url;
			img.alt = '';
			preview.appendChild(img);
			return;
		}

		const note = document.createElement('span');
		note.className = 'sm-muted';
		note.textContent = wp.i18n.__('Using the site logo', 'satori-manifest');
		preview.appendChild(note);
	};

	picker
		.querySelector('[data-sm-logo-choose]')
		.addEventListener('click', () => {
			if (!frame) {
				frame = wp.media({
					title: wp.i18n.__('Choose a logo', 'satori-manifest'),
					library: { type: 'image' },
					multiple: false,
				});

				frame.on('select', () => {
					const attachment = frame
						.state()
						.get('selection')
						.first()
						.toJSON();
					const sizes = attachment.sizes || {};

					input.value = attachment.id;
					show((sizes.medium || attachment).url);
				});
			}

			frame.open();
		});

	picker
		.querySelector('[data-sm-logo-clear]')
		.addEventListener('click', () => {
			input.value = '0';
			show('');
		});
});
