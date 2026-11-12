/**
 * Satori Manifest - Design screen.
 *
 * Switching Light / Dark reloads every design preview (and its "Full preview"
 * link) in that colour mode. The choice is saved with "Apply design".
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   3.8.0
 */

(function () {
	const setMode = (url, mode) => {
		const next = new URL(url, window.location.href);
		next.searchParams.set('sm_mode', mode);
		return next.toString();
	};

	document.querySelectorAll('[data-sm-mode-switch]').forEach((input) => {
		input.addEventListener('change', () => {
			if (!input.checked) {
				return;
			}

			document
				.querySelectorAll('.sm-design-card__preview iframe')
				.forEach((frame) => {
					frame.src = setMode(frame.src, input.value);
				});

			document
				.querySelectorAll('.sm-design-card__open')
				.forEach((link) => {
					link.href = setMode(link.href, input.value);
				});
		});
	});
})();
