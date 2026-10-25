/**
 * Satori Manifest — Inspector sidebar controls.
 *
 * Renders the InspectorControls panel for the price-list block,
 * providing manifest selection, layout preset, and display toggles.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

import { InspectorControls } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
	TextControl,
	CheckboxControl,
	Spinner,
	Button,
} from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Colour scheme presets.
 *
 * Each entry defines the swatch colours shown in the inspector picker and
 * maps to an `is-scheme-{value}` CSS modifier class on the block wrapper.
 * Adding a new scheme requires a matching CSS rule in frontend.scss.
 *
 * Defined outside the component so the reference is stable across renders.
 *
 * @type {Array<{value: string, label: string, bg: string, accent: string}>}
 */
const SCHEME_OPTIONS = [
	{
		value: 'brand',
		label: __('Page design', 'satori-manifest'),
		bg: null,
		accent: null,
	},
	{
		value: 'default',
		label: __('Default', 'satori-manifest'),
		bg: '#FDFAF5',
		accent: '#1C1712',
	},
	{
		value: 'warm',
		label: __('Warm', 'satori-manifest'),
		bg: '#EDE6D6',
		accent: '#5C4E3A',
	},
	{
		value: 'terracotta',
		label: __('Terracotta', 'satori-manifest'),
		bg: '#EDE6D6',
		accent: '#B5704A',
	},
	{
		value: 'dark',
		label: __('Dark', 'satori-manifest'),
		bg: '#1C1712',
		accent: '#FDFAF5',
	},
	{
		value: 'minimal',
		label: __('Minimal', 'satori-manifest'),
		bg: '#D4C5A9',
		accent: '#5C4E3A',
	},
	{
		value: 'custom',
		label: __('Custom', 'satori-manifest'),
		bg: null,
		accent: null,
	},
];

/**
 * Layout preset options for the display style selector.
 *
 * Defined outside the component so the reference is stable across renders.
 *
 * @type {Array<{label: string, value: string}>}
 */
const LAYOUT_OPTIONS = [
	{ label: __('Classic List', 'satori-manifest'), value: 'single-column' },
	{ label: __('Split Grid', 'satori-manifest'), value: 'two-column' },
	{ label: __('Card Style', 'satori-manifest'), value: 'card-style' },
	{ label: __('Minimal', 'satori-manifest'), value: 'minimal' },
	{ label: __('Menu (dotted leaders)', 'satori-manifest'), value: 'leaders' },
];

/**
 * REST API query for fetching all published manifests.
 *
 * Defined as a module-level constant so the object reference is stable
 * across renders. An inline object would be recreated every render,
 * causing hasFinishedResolution() to never match on reference equality.
 *
 * @type {Object}
 */
/**
 * Font weight options shared between section title and list item controls.
 *
 * @type {Array<{label: string, value: string}>}
 */
/**
 * Font family options. Values match the theme's design tokens.
 *
 * @type {Array<{label: string, value: string}>}
 */
/**
 * Font family options grouped by category.
 *
 * Theme fonts (Cormorant Garamond, DM Sans) are already loaded by the theme
 * and do not require an additional network request. All other values use
 * web-safe stacks that do not require external font loading.
 *
 * Defined outside the component so the reference is stable across renders.
 *
 * @type {Array<{label: string, value: string}>}
 */
const FONT_FAMILY_OPTIONS = [
	{ label: __('Default', 'satori-manifest'), value: '' },
	{
		label: __('Cormorant Garamond', 'satori-manifest'),
		value: "'Cormorant Garamond', Georgia, serif",
	},
	{
		label: __('DM Sans', 'satori-manifest'),
		value: "'DM Sans', sans-serif",
	},
	{
		label: __('Georgia', 'satori-manifest'),
		value: "Georgia, 'Times New Roman', serif",
	},
	{
		label: __('Times New Roman', 'satori-manifest'),
		value: "'Times New Roman', Times, serif",
	},
	{
		label: __('Palatino', 'satori-manifest'),
		value: "'Palatino Linotype', Palatino, 'Book Antiqua', serif",
	},
	{
		label: __('Garamond', 'satori-manifest'),
		value: "Garamond, 'Times New Roman', serif",
	},
	{
		label: __('Book Antiqua', 'satori-manifest'),
		value: "'Book Antiqua', Palatino, serif",
	},
	{
		label: __('System UI', 'satori-manifest'),
		value: 'system-ui, -apple-system, BlinkMacSystemFont, sans-serif',
	},
	{
		label: __('Arial / Helvetica', 'satori-manifest'),
		value: 'Arial, Helvetica, sans-serif',
	},
	{
		label: __('Verdana', 'satori-manifest'),
		value: 'Verdana, Geneva, sans-serif',
	},
	{
		label: __('Trebuchet MS', 'satori-manifest'),
		value: "'Trebuchet MS', Helvetica, sans-serif",
	},
	{
		label: __('Tahoma', 'satori-manifest'),
		value: 'Tahoma, Geneva, sans-serif',
	},
	{
		label: __('Gill Sans', 'satori-manifest'),
		value: "'Gill Sans', 'Gill Sans MT', Calibri, sans-serif",
	},
	{
		label: __('Optima', 'satori-manifest'),
		value: "Optima, Segoe, 'Segoe UI', Candara, sans-serif",
	},
	{
		label: __('Monospace', 'satori-manifest'),
		value: "ui-monospace, 'Courier New', Courier, monospace",
	},
	{
		label: __('Courier New', 'satori-manifest'),
		value: "'Courier New', Courier, monospace",
	},
];

const FONT_WEIGHT_OPTIONS = [
	{ label: __('Default', 'satori-manifest'), value: '' },
	{ label: __('Light (300)', 'satori-manifest'), value: '300' },
	{ label: __('Regular (400)', 'satori-manifest'), value: '400' },
	{ label: __('Medium (500)', 'satori-manifest'), value: '500' },
	{ label: __('Semi-bold (600)', 'satori-manifest'), value: '600' },
	{ label: __('Bold (700)', 'satori-manifest'), value: '700' },
];

const MANIFEST_QUERY = {
	per_page: 100,
	status: 'publish',
	orderby: 'title',
	order: 'asc',
};

/**
 * Inspector controls component for the price-list block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {boolean}  props.isAuto        True when showing the page's own manifest automatically.
 * @return {JSX.Element} Inspector controls panel.
 */
export default function Inspector({ attributes, setAttributes, isAuto }) {
	const {
		manifestIds,
		layout,
		colorScheme,
		showPrices,
		showDescriptions,
		showSectionDescriptions,
		showBadges,
		pricePrefix,
		customBgColor,
		customAccentColor,
		customTitleColor,
		customCardColor,
		showBackground,
		cardPadding,
		titlePadding,
		showItemBorder,
		titleBgColor,
		titleFontSize,
		titleFontWeight,
		titleFontFamily,
		itemFontSize,
		itemFontWeight,
		itemFontFamily,
	} = attributes;

	// Load all published manifests via the WP REST API.
	const { manifests, isLoading } = useSelect((select) => {
		const store = select('core');

		return {
			manifests: store.getEntityRecords(
				'postType',
				'sm_manifest',
				MANIFEST_QUERY
			),
			isLoading: !store.hasFinishedResolution('getEntityRecords', [
				'postType',
				'sm_manifest',
				MANIFEST_QUERY,
			]),
		};
	});

	/**
	 * Toggles a manifest ID in the manifestIds attribute array.
	 *
	 * @param {number}  id      Manifest post ID.
	 * @param {boolean} checked Whether the checkbox is now checked.
	 * @return {void}
	 */
	const toggleManifest = useCallback(
		(id, checked) => {
			const next = checked
				? [...manifestIds, id]
				: manifestIds.filter((m) => m !== id);
			setAttributes({ manifestIds: next });
		},
		[manifestIds, setAttributes]
	);

	return (
		<InspectorControls>
			<PanelBody
				title={__('Price lists', 'satori-manifest')}
				initialOpen={true}
			>
				{isAuto && (
					<p className="satori-manifest-auto-note">
						{__(
							"Showing this page's own price list automatically. Tick a list below only to show a different one.",
							'satori-manifest'
						)}
					</p>
				)}
				{isLoading && <Spinner />}
				{!isLoading && (!manifests || manifests.length === 0) && (
					<p>
						{__(
							'No manifests found. Create one under Manifest → All Manifests.',
							'satori-manifest'
						)}
					</p>
				)}
				{!isLoading &&
					manifests &&
					manifests.map((manifest) => (
						<CheckboxControl
							key={manifest.id}
							label={manifest.title.rendered}
							checked={manifestIds.includes(manifest.id)}
							onChange={(checked) =>
								toggleManifest(manifest.id, checked)
							}
						/>
					))}
			</PanelBody>

			<PanelBody
				title={__('Layout', 'satori-manifest')}
				initialOpen={true}
			>
				<SelectControl
					label={__('Display style', 'satori-manifest')}
					value={layout}
					options={LAYOUT_OPTIONS}
					onChange={(value) => setAttributes({ layout: value })}
				/>
			</PanelBody>

			<PanelBody
				title={__('Colour Scheme', 'satori-manifest')}
				initialOpen={true}
			>
				<div className="satori-manifest-scheme-picker">
					{SCHEME_OPTIONS.map((scheme) => (
						<Button
							key={scheme.value}
							className={`satori-manifest-scheme-option${colorScheme === scheme.value ? ' is-active' : ''}`}
							onClick={() =>
								setAttributes({ colorScheme: scheme.value })
							}
							aria-label={scheme.label}
							aria-pressed={colorScheme === scheme.value}
						>
							<span
								className={
									'satori-manifest-scheme-option__swatch' +
									(scheme.bg
										? ''
										: ` satori-manifest-scheme-option__swatch--${scheme.value}`)
								}
								style={
									scheme.bg
										? {
												background: scheme.bg,
												borderColor: scheme.accent,
											}
										: {}
								}
								aria-hidden="true"
							/>
							<span className="satori-manifest-scheme-option__label">
								{scheme.label}
							</span>
						</Button>
					))}
				</div>

				{colorScheme === 'custom' && (
					<div className="satori-manifest-custom-colors">
						<div className="satori-manifest-custom-color">
							<label
								htmlFor="sm-custom-bg-color"
								className="satori-manifest-custom-color__label"
							>
								{__('Background', 'satori-manifest')}
							</label>
							<input
								id="sm-custom-bg-color"
								type="color"
								className="satori-manifest-custom-color__input"
								value={customBgColor || '#f5f0e8'}
								onChange={(e) =>
									setAttributes({
										customBgColor: e.target.value,
									})
								}
							/>
						</div>
						<div className="satori-manifest-custom-color">
							<label
								htmlFor="sm-custom-accent-color"
								className="satori-manifest-custom-color__label"
							>
								{__('Accent', 'satori-manifest')}
							</label>
							<input
								id="sm-custom-accent-color"
								type="color"
								className="satori-manifest-custom-color__input"
								value={customAccentColor || '#1c1712'}
								onChange={(e) =>
									setAttributes({
										customAccentColor: e.target.value,
									})
								}
							/>
						</div>
						<div className="satori-manifest-custom-color">
							<label
								htmlFor="sm-custom-title-color"
								className="satori-manifest-custom-color__label"
							>
								{__('Title', 'satori-manifest')}
							</label>
							<input
								id="sm-custom-title-color"
								type="color"
								className="satori-manifest-custom-color__input"
								value={
									customTitleColor ||
									customAccentColor ||
									'#1c1712'
								}
								onChange={(e) =>
									setAttributes({
										customTitleColor: e.target.value,
									})
								}
							/>
						</div>
						<div className="satori-manifest-custom-color">
							<label
								htmlFor="sm-custom-card-color"
								className="satori-manifest-custom-color__label"
							>
								{__('Card', 'satori-manifest')}
							</label>
							<input
								id="sm-custom-card-color"
								type="color"
								className="satori-manifest-custom-color__input"
								value={customCardColor || '#ffffff'}
								onChange={(e) =>
									setAttributes({
										customCardColor: e.target.value,
									})
								}
							/>
						</div>
					</div>
				)}
			</PanelBody>

			<PanelBody
				title={__('Typography', 'satori-manifest')}
				initialOpen={false}
			>
				<p className="satori-manifest-typo-label">
					{__('Section title', 'satori-manifest')}
				</p>
				<SelectControl
					label={__('Font family', 'satori-manifest')}
					value={titleFontFamily}
					options={FONT_FAMILY_OPTIONS}
					onChange={(val) => setAttributes({ titleFontFamily: val })}
				/>
				<RangeControl
					label={__('Font size (px)', 'satori-manifest')}
					value={titleFontSize || 0}
					onChange={(val) =>
						setAttributes({ titleFontSize: val ?? 0 })
					}
					min={10}
					max={48}
					allowReset
					resetFallbackValue={0}
				/>
				<SelectControl
					label={__('Font weight', 'satori-manifest')}
					value={titleFontWeight}
					options={FONT_WEIGHT_OPTIONS}
					onChange={(val) => setAttributes({ titleFontWeight: val })}
				/>
				<p className="satori-manifest-typo-label satori-manifest-typo-label--spaced">
					{__('List items', 'satori-manifest')}
				</p>
				<SelectControl
					label={__('Font family', 'satori-manifest')}
					value={itemFontFamily}
					options={FONT_FAMILY_OPTIONS}
					onChange={(val) => setAttributes({ itemFontFamily: val })}
				/>
				<RangeControl
					label={__('Font size (px)', 'satori-manifest')}
					value={itemFontSize || 0}
					onChange={(val) =>
						setAttributes({ itemFontSize: val ?? 0 })
					}
					min={10}
					max={32}
					allowReset
					resetFallbackValue={0}
				/>
				<SelectControl
					label={__('Font weight', 'satori-manifest')}
					value={itemFontWeight}
					options={FONT_WEIGHT_OPTIONS}
					onChange={(val) => setAttributes({ itemFontWeight: val })}
				/>
			</PanelBody>

			<PanelBody
				title={__('Display', 'satori-manifest')}
				initialOpen={true}
			>
				<ToggleControl
					label={__('Show background', 'satori-manifest')}
					checked={showBackground}
					onChange={(value) =>
						setAttributes({ showBackground: value })
					}
				/>
				<div className="satori-manifest-title-bg-control">
					<label
						htmlFor="sm-title-bg-color"
						className="satori-manifest-title-bg-control__label"
					>
						{__('Section title background', 'satori-manifest')}
					</label>
					<div className="satori-manifest-title-bg-control__actions">
						<input
							id="sm-title-bg-color"
							type="color"
							className="satori-manifest-title-bg-control__input"
							value={titleBgColor || '#ffffff'}
							onChange={(e) =>
								setAttributes({ titleBgColor: e.target.value })
							}
						/>
						{titleBgColor && (
							<Button
								variant="tertiary"
								size="small"
								onClick={() =>
									setAttributes({ titleBgColor: '' })
								}
							>
								{__('Remove', 'satori-manifest')}
							</Button>
						)}
					</div>
				</div>
				<ToggleControl
					label={__('Card padding', 'satori-manifest')}
					checked={cardPadding}
					onChange={(value) => setAttributes({ cardPadding: value })}
				/>
				<ToggleControl
					label={__('Section title padding', 'satori-manifest')}
					checked={titlePadding}
					onChange={(value) => setAttributes({ titlePadding: value })}
				/>
				<ToggleControl
					label={__('Item underline', 'satori-manifest')}
					checked={showItemBorder}
					onChange={(value) =>
						setAttributes({ showItemBorder: value })
					}
				/>
				<ToggleControl
					label={__('Show prices', 'satori-manifest')}
					checked={showPrices}
					onChange={(value) => setAttributes({ showPrices: value })}
				/>
				<ToggleControl
					label={__('Show item descriptions', 'satori-manifest')}
					checked={showDescriptions}
					onChange={(value) =>
						setAttributes({ showDescriptions: value })
					}
				/>
				<ToggleControl
					label={__('Show section intros', 'satori-manifest')}
					checked={showSectionDescriptions}
					onChange={(value) =>
						setAttributes({ showSectionDescriptions: value })
					}
				/>
				<ToggleControl
					label={__('Show badges', 'satori-manifest')}
					checked={showBadges}
					onChange={(value) => setAttributes({ showBadges: value })}
				/>
				<TextControl
					label={__('Price prefix override', 'satori-manifest')}
					help={__(
						"Overrides the per-item prefix. Leave blank to use each item's own prefix.",
						'satori-manifest'
					)}
					value={pricePrefix}
					onChange={(value) => setAttributes({ pricePrefix: value })}
					placeholder={__('e.g. from', 'satori-manifest')}
				/>
			</PanelBody>
		</InspectorControls>
	);
}
