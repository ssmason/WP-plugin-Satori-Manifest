/**
 * Satori Manifest — client Home screen.
 *
 * "Copy code" button for the website embed snippet. Uses the Clipboard API,
 * falling back to selecting the text so the client can press Ctrl/Cmd+C.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.1.0
 */

document.querySelectorAll('[data-sm-copy]').forEach((button) => {
	const source = document.querySelector(button.dataset.smCopy);

	if (!source) {
		return;
	}

	const status = button.parentElement.querySelector('[data-sm-copy-status]');
	const fallback = wp.i18n.__(
		'Selected — press Ctrl+C to copy.',
		'satori-manifest'
	);

	/**
	 * Shows a short confirmation next to the button.
	 *
	 * @param {string} message Text to show.
	 */
	const say = (message) => {
		if (status) {
			status.textContent = message;
			window.setTimeout(() => {
				status.textContent = '';
			}, 4000);
		}
	};

	button.addEventListener('click', () => {
		source.select();

		if (!navigator.clipboard) {
			say(fallback);
			return;
		}

		navigator.clipboard
			.writeText(source.value)
			.then(() => say(wp.i18n.__('Copied!', 'satori-manifest')))
			.catch(() => say(fallback));
	});
});
