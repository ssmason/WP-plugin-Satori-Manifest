/**
 * Satori Manifest - Block utility functions.
 *
 * Shared helpers used by the block editor components. Keeping these in a
 * dedicated module prevents utility logic from accumulating inside component
 * files and makes them independently testable.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

import { __ } from '@wordpress/i18n';

/**
 * Placeholder sections for previews (mirrors Manifest_Repository::demo_sections()).
 *
 * @type {Array<Object>}
 */
export const DEMO_SECTIONS = [
	{
		title: __('Signature services', 'satori-manifest'),
		description: __('Our most-loved treatments.', 'satori-manifest'),
		items: [
			{
				label: __('Service name', 'satori-manifest'),
				description: __(
					'A short description of the service',
					'satori-manifest'
				),
				price: '45',
				price_prefix: __('from', 'satori-manifest'),
				badge: __('Popular', 'satori-manifest'),
			},
			{
				label: __('Service name', 'satori-manifest'),
				price: '60',
			},
			{
				label: __('Service name', 'satori-manifest'),
				description: __(
					'A short description of the service',
					'satori-manifest'
				),
				price: '25',
				price_prefix: __('from', 'satori-manifest'),
			},
		],
	},
	{
		title: __('Another section', 'satori-manifest'),
		items: [
			{ label: __('Service name', 'satori-manifest'), price: '35' },
			{
				label: __('Service name', 'satori-manifest'),
				description: __(
					'A short description of the service',
					'satori-manifest'
				),
				price: '50',
				badge: __('New', 'satori-manifest'),
			},
		],
	},
];

/**
 * Returns page context passed from PHP (Editor::localize_block_data()).
 *
 * @return {{defaultManifestId: number, currency: {symbol: string, position: string}, isClient: boolean}} Editor data.
 */
export function editorData() {
	const data = window.satoriManifestEditor || {};

	return {
		defaultManifestId: Number(data.defaultManifestId) || 0,
		currency: data.currency || { symbol: '', position: 'before' },
		isClient: Boolean(data.isClient),
	};
}

/**
 * Parses the _satori_manifest_sections meta string into a sections array.
 *
 * Returns an empty array on missing or malformed JSON so components never
 * throw on a bad or absent REST API response.
 *
 * @param {string|undefined} raw Raw JSON string from post meta.
 * @return {Array} Decoded sections array, or [] on failure.
 */
export function parseSections(raw) {
	if (!raw) {
		return [];
	}
	try {
		const parsed = JSON.parse(raw);
		return Array.isArray(parsed) ? parsed : [];
	} catch {
		return [];
	}
}

/**
 * Formats a price like Brand::format_price() on the PHP side.
 *
 * Whole amounts show no decimals; others show two. The currency symbol is
 * placed before or after the number.
 *
 * @param {string|number} value    Raw price value.
 * @param {Object}        currency { symbol, position }.
 * @return {string} Formatted price (e.g. "£12.50").
 */
export function formatPrice(
	value,
	currency = { symbol: '', position: 'before' }
) {
	const amount = parseFloat(value) || 0;
	const decimals = Number.isInteger(amount) ? 0 : 2;
	const number = new Intl.NumberFormat(
		document.documentElement.lang || 'en',
		{
			minimumFractionDigits: decimals,
			maximumFractionDigits: decimals,
		}
	).format(amount);

	return 'after' === currency.position
		? `${number}${currency.symbol}`
		: `${currency.symbol}${number}`;
}

/**
 * Ids one block hides once new entries are placed by their neighbour.
 *
 * Mirrors Manifest\Block_Placement (PHP): on a page with several price list
 * blocks, an entry that no block mentions shows only in the block(s) that
 * show the nearest placed entry above it (or below it, at the top).
 *
 * @param {Array} sections Sections and headings, in order.
 * @param {Array} own      Ids this block hides.
 * @param {Array} sets     Ids each price list block on the page hides.
 * @return {Array} Ids to hide.
 */
export function placementHidden(sections, own, sets) {
	if (sets.length < 2) {
		return own;
	}

	const known = new Set(sets.flat());
	const ids = sections.map((section) => section.id || '');
	const hidden = [...own];

	ids.forEach((id, index) => {
		if (!id || known.has(id)) {
			return;
		}

		let anchor = null;

		for (let i = index - 1; i >= 0 && null === anchor; i--) {
			anchor = known.has(ids[i]) ? ids[i] : null;
		}

		for (let i = index + 1; i < ids.length && null === anchor; i++) {
			anchor = known.has(ids[i]) ? ids[i] : null;
		}

		if (null !== anchor && own.includes(anchor)) {
			hidden.push(id);
		}
	});

	return hidden;
}

/**
 * The sections one price list block shows (headings never: the Price list
 * heading block draws them above the menu). Mirrors Block_Placement::shown().
 *
 * @param {Array}  sections Sections and headings, in order.
 * @param {Object} block    This block: { hidden: [], heading: '' }.
 * @param {Array}  blocks   Every price list block on the page, same shape.
 * @return {Array} Sections to display.
 */
export function shownSections(sections, block, blocks) {
	const owner = {};
	let current = '';

	sections.forEach((section) => {
		if ('heading' === section.type) {
			current = section.id;
		} else {
			owner[section.id] = current;
		}
	});

	const normal = blocks.filter((b) => !b.heading);
	const sets = normal.map((b) => b.hidden);
	const placed = new Set(sets.length > 1 ? sets.flat() : []);
	const managed = new Set(blocks.map((b) => b.heading).filter(Boolean));
	const hidden = block.heading
		? block.hidden
		: placementHidden(sections, block.hidden, sets);

	return sections.filter((section) => {
		if ('heading' === section.type || hidden.includes(section.id)) {
			return false;
		}

		const heading = owner[section.id] || '';

		if (block.heading) {
			return heading === block.heading && !placed.has(section.id);
		}

		return !(heading && managed.has(heading) && !placed.has(section.id));
	});
}
