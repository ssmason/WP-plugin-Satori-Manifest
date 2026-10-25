/**
 * Satori Manifest — Block utility functions.
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
