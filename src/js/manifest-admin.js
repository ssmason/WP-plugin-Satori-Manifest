/**
 * Satori Manifest — price list editor (manifest edit screen).
 *
 * Drives the Sections & Items meta box. No AJAX: the DOM is serialised to a
 * hidden JSON field which is saved with the standard WordPress post form.
 *
 * - One delegated click listener handles every button via data-sm-action,
 *   because sections and items are cloned from <template> elements at
 *   runtime.
 * - Text input re-serialises on a 300 ms debounce; structural changes
 *   serialise immediately.
 * - Section removal uses a two-step inline confirmation instead of a
 *   blocking window.confirm().
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   2.0.0
 */

(function () {
	'use strict';

	const editor = document.querySelector('[data-sm-manifest-editor]');
	const { _n, sprintf } = wp.i18n;

	if (!editor) {
		return;
	}

	const list = editor.querySelector('#satori-manifest-sections-list');
	const field = editor.querySelector('#satori-manifest-sections-data');
	const stats = editor.querySelector('[data-sm-stats]');
	const empty = editor.querySelector('[data-sm-empty]');
	const sectionTemplate = document.getElementById(
		'satori-manifest-section-template'
	);
	const itemTemplate = document.getElementById(
		'satori-manifest-item-template'
	);

	/**
	 * Returns the trimmed value of a matching input inside a container.
	 *
	 * @param {Element} container Element to search within.
	 * @param {string}  selector  Input selector.
	 * @return {string} Value, or empty string.
	 */
	const valueOf = (container, selector) =>
		container.querySelector(selector)?.value ?? '';

	/**
	 * Returns the item rows directly inside a section.
	 *
	 * @param {Element} section Section element.
	 * @return {Element[]} Item rows.
	 */
	const itemsOf = (section) =>
		Array.from(section.querySelectorAll('.sm-items > .sm-item'));

	/**
	 * Returns all section elements in order.
	 *
	 * @return {Element[]} Sections.
	 */
	const sections = () =>
		Array.from(list.querySelectorAll(':scope > .sm-section'));

	// ── Serialise ───────────────────────────────────────────────────────────

	/**
	 * Writes the current DOM state to the hidden JSON field and refreshes
	 * counts, the empty state and sub-heading styling.
	 *
	 * @return {void}
	 */
	function serialise() {
		let totalItems = 0;

		const data = sections().map((section, sectionIndex) => {
			const items = itemsOf(section).map((row, itemIndex) => {
				const price = valueOf(row, '.sm-item__price');
				const label = valueOf(row, '.sm-item__label');

				row.classList.toggle(
					'is-subheading',
					'' === price.trim() && '' !== label.trim()
				);

				return {
					label,
					description: valueOf(row, '.sm-item__description'),
					badge: valueOf(row, '.sm-item__badge'),
					price_prefix: valueOf(row, '.sm-item__prefix'),
					price,
					sort_order: itemIndex,
				};
			});

			totalItems += items.length;

			const count = section.querySelector('[data-sm-count]');
			if (count) {
				count.textContent = sprintf(
					/* translators: %d: number of items. */
					_n('%d item', '%d items', items.length, 'satori-manifest'),
					items.length
				);
			}

			return {
				// Permanent id; empty for new sections (assigned on save).
				id: section.dataset.smId || '',
				title: valueOf(section, '.sm-section__title-input'),
				description: valueOf(section, '.sm-section__description-input'),
				sort_order: sectionIndex,
				items,
			};
		});

		field.value = JSON.stringify(data);

		if (stats) {
			stats.textContent = data.length
				? sprintf(
						/* translators: 1: number of sections, 2: number of items. */
						_n(
							'%1$d section · %2$d items',
							'%1$d sections · %2$d items',
							data.length,
							'satori-manifest'
						),
						data.length,
						totalItems
					)
				: '';
		}

		editor.classList.toggle('is-empty', 0 === data.length);
		if (empty) {
			empty.hidden = 0 !== data.length;
		}
	}

	let timer = null;

	/**
	 * Debounced serialise for typing.
	 *
	 * @return {void}
	 */
	function serialiseSoon() {
		clearTimeout(timer);
		timer = setTimeout(serialise, 300);
	}

	// ── Structure ───────────────────────────────────────────────────────────

	/**
	 * Appends a new item to a section and focuses it.
	 *
	 * @param {Element} section Section element.
	 * @return {void}
	 */
	function addItem(section) {
		const items = section.querySelector('.sm-items');
		items.appendChild(itemTemplate.content.cloneNode(true));
		items.lastElementChild?.querySelector('input')?.focus();
		serialise();
	}

	/**
	 * Appends a new section (with one empty item) and focuses its title.
	 *
	 * @return {void}
	 */
	function addSection() {
		list.appendChild(sectionTemplate.content.cloneNode(true));
		const section = list.lastElementChild;
		addItem(section);
		section.querySelector('.sm-section__title-input')?.focus();
		section.scrollIntoView({ behavior: 'smooth', block: 'center' });
		serialise();
	}

	/**
	 * Moves an element one place up or down among its siblings.
	 *
	 * @param {Element} element   Element to move.
	 * @param {number}  direction -1 for up, 1 for down.
	 * @return {void}
	 */
	function move(element, direction) {
		const sibling =
			-1 === direction
				? element.previousElementSibling
				: element.nextElementSibling;

		if (!sibling) {
			return;
		}

		if (-1 === direction) {
			element.parentNode.insertBefore(element, sibling);
		} else {
			element.parentNode.insertBefore(sibling, element);
		}

		element.classList.add('is-moved');
		setTimeout(() => element.classList.remove('is-moved'), 400);
		serialise();
	}

	/**
	 * Opens or closes a section.
	 *
	 * @param {Element} section Section element.
	 * @param {boolean} open    Desired state.
	 * @return {void}
	 */
	function setOpen(section, open) {
		section.classList.toggle('is-collapsed', !open);
		section
			.querySelector('[data-sm-action="section-toggle"]')
			?.setAttribute('aria-expanded', open ? 'true' : 'false');
	}

	/**
	 * Two-step confirmation before removing a section.
	 *
	 * @param {Element} button  The remove button.
	 * @param {Element} section The section.
	 * @return {void}
	 */
	function confirmRemoveSection(button, section) {
		if (button.classList.contains('is-confirming')) {
			section.remove();
			serialise();
			return;
		}

		button.classList.add('is-confirming');
		setTimeout(() => button.classList.remove('is-confirming'), 3000);
	}

	// ── Events ──────────────────────────────────────────────────────────────

	const actions = {
		'section-add': () => addSection(),
		'section-toggle': (button, section) =>
			setOpen(section, section.classList.contains('is-collapsed')),
		'section-up': (button, section) => move(section, -1),
		'section-down': (button, section) => move(section, 1),
		'section-remove': (button, section) =>
			confirmRemoveSection(button, section),
		'item-add': (button, section) => addItem(section),
		'item-up': (button, section, item) => move(item, -1),
		'item-down': (button, section, item) => move(item, 1),
		'item-remove': (button, section, item) => {
			item.remove();
			serialise();
		},
		'expand-all': () => sections().forEach((s) => setOpen(s, true)),
		'collapse-all': () => sections().forEach((s) => setOpen(s, false)),
	};

	editor.addEventListener('click', (event) => {
		const button = event.target.closest('[data-sm-action]');

		if (!button || !editor.contains(button)) {
			return;
		}

		const handler = actions[button.dataset.smAction];

		if (handler) {
			event.preventDefault();
			handler(
				button,
				button.closest('.sm-section'),
				button.closest('.sm-item')
			);
		}
	});

	editor.addEventListener('input', serialiseSoon);

	// Enter in an item's price field adds the next item (fast data entry);
	// Enter anywhere else must not submit the whole post form.
	editor.addEventListener('keydown', (event) => {
		if ('Enter' !== event.key || 'INPUT' !== event.target.tagName) {
			return;
		}

		event.preventDefault();

		if (event.target.classList.contains('sm-item__price')) {
			const section = event.target.closest('.sm-section');
			const row = event.target.closest('.sm-item');

			if (section && row === itemsOf(section).pop()) {
				addItem(section);
			}
		}
	});

	// Auto-grow the section description textareas.
	editor.addEventListener('input', (event) => {
		if (event.target.matches('.sm-section__description-input')) {
			event.target.style.height = 'auto';
			event.target.style.height = `${event.target.scrollHeight}px`;
		}
	});

	document.getElementById('post')?.addEventListener('submit', serialise);

	serialise();
})();
