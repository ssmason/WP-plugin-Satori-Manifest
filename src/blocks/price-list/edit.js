/**
 * Satori Manifest - Block edit component.
 *
 * Renders a live preview of the selected manifests in the editor, matching
 * the front-end markup in block/render.php so design styles apply 1:1.
 *
 * Manifest resolution mirrors render.php: with no manifests selected, a
 * client page previews its own manifest (passed in via
 * window.satoriManifestEditor.defaultManifestId); anywhere else a styled
 * demo is shown so pattern previews look right.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

import { useBlockProps } from '@wordpress/block-editor';
import { Notice, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

import Inspector from './inspector';
import {
	DEMO_SECTIONS,
	editorData,
	parseSections,
	formatPrice,
	shownSections,
} from './utils';

/**
 * Fields requested from the REST API for each manifest record.
 *
 * Module-level so the object reference is stable across renders -
 * hasFinishedResolution() matches arguments by reference.
 *
 * @type {Object}
 */
const ENTITY_FIELDS = { _fields: 'id,title,meta' };

/**
 * Builds the wrapper class list from block attributes.
 *
 * @param {Object} attributes Block attributes.
 * @return {string} Space-separated class names.
 */
function wrapperClasses(attributes) {
	return [
		'satori-manifest-price-list',
		`is-layout-${attributes.layout}`,
		`is-scheme-${attributes.colorScheme}`,
		!attributes.showBackground && 'has-no-background',
		attributes.titleBgColor && 'has-title-bg',
		!attributes.cardPadding && 'has-no-card-padding',
		!attributes.titlePadding && 'has-no-title-padding',
		!attributes.showItemBorder && 'has-no-item-border',
	]
		.filter(Boolean)
		.join(' ');
}

/**
 * Builds the wrapper CSS custom properties from block attributes.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Style object.
 */
function wrapperStyle(attributes) {
	const vars = {};
	const custom = 'custom' === attributes.colorScheme;

	const map = [
		[custom && attributes.customBgColor, '--sm-custom-bg'],
		[custom && attributes.customAccentColor, '--sm-custom-accent'],
		[custom && attributes.customTitleColor, '--sm-custom-title'],
		[custom && attributes.customCardColor, '--sm-custom-card'],
		[attributes.titleBgColor, '--sm-title-bg'],
		[attributes.titleFontFamily, '--sm-title-font'],
		[
			attributes.titleFontSize && `${attributes.titleFontSize}px`,
			'--sm-title-size',
		],
		[attributes.titleFontWeight, '--sm-title-weight'],
		[attributes.itemFontFamily, '--sm-item-font'],
		[
			attributes.itemFontSize && `${attributes.itemFontSize}px`,
			'--sm-item-size',
		],
		[attributes.itemFontWeight, '--sm-item-weight'],
	];

	map.forEach(([value, property]) => {
		if (value) {
			vars[property] = value;
		}
	});

	return vars;
}

/**
 * Renders one manifest's sections (same markup as render.php).
 *
 * @param {Object} props            Component props.
 * @param {Array}  props.sections   Sections array.
 * @param {Object} props.attributes Block attributes.
 * @return {JSX.Element} Manifest markup.
 */
function Manifest({ sections, attributes }) {
	const {
		showPrices,
		showDescriptions,
		showSectionDescriptions,
		showBadges,
		pricePrefix,
	} = attributes;
	const { currency } = editorData();

	return (
		<div className="satori-manifest-price-list__manifest">
			{sections.map((section, si) => {
				if ('heading' === section.type) {
					if (false === section.visible) {
						return null;
					}

					const style = section.style || {};

					return (
						<header
							key={`${section.id}-${si}`}
							className={`satori-manifest-price-list__heading is-size-${
								style.size || 'm'
							}${
								style.background || style.text
									? ' has-own-colours'
									: ''
							}`}
							style={{
								'--sm-heading-bg':
									style.background || undefined,
								'--sm-heading-text': style.text || undefined,
							}}
						>
							{section.title && (
								<h2 className="satori-manifest-price-list__heading-title">
									{section.title}
								</h2>
							)}
							{section.description && (
								<p className="satori-manifest-price-list__heading-note">
									{section.description}
								</p>
							)}
						</header>
					);
				}

				const items = Array.isArray(section.items) ? section.items : [];

				if (!section.title && 0 === items.length) {
					return null;
				}

				return (
					<section
						key={`${section.title}-${si}`}
						className="satori-manifest-price-list__section"
					>
						{section.title && (
							<h3 className="satori-manifest-price-list__section-title">
								{section.title}
							</h3>
						)}

						{showSectionDescriptions && section.description && (
							<p className="satori-manifest-price-list__section-desc">
								{section.description}
							</p>
						)}

						{items.length > 0 && (
							<ul className="satori-manifest-price-list__items">
								{items.map((item, ii) => {
									if (!item.label) {
										return null;
									}

									const price = String(
										item.price ?? ''
									).trim();
									const isSubheader = '' === price;
									const prefix =
										pricePrefix || item.price_prefix || '';

									return (
										<li
											key={`${item.label}-${ii}`}
											className={`satori-manifest-price-list__item${
												isSubheader
													? ' is-subsection-header'
													: ''
											}`}
										>
											<div className="satori-manifest-price-list__item-label">
												<span className="satori-manifest-price-list__item-name">
													{item.label}
													{showBadges &&
														item.badge && (
															<span className="satori-manifest-price-list__item-badge">
																{item.badge}
															</span>
														)}
												</span>
												{showDescriptions &&
													item.description && (
														<span className="satori-manifest-price-list__item-desc">
															{item.description}
														</span>
													)}
											</div>

											{showPrices && !isSubheader && (
												<>
													<span
														className="satori-manifest-price-list__item-leader"
														aria-hidden="true"
													/>
													<span className="satori-manifest-price-list__item-price">
														{prefix && (
															<span className="satori-manifest-price-list__item-prefix">
																{prefix}
															</span>
														)}
														{formatPrice(
															price,
															currency
														)}
													</span>
												</>
											)}
										</li>
									);
								})}
							</ul>
						)}
					</section>
				);
			})}

			{0 === sections.length && (
				<p className="satori-manifest-price-list__empty">
					{__(
						'This price list has no sections yet. Add some under Price list.',
						'satori-manifest'
					)}
				</p>
			)}
		</div>
	);
}

/**
 * Edit component for the satori-manifest/price-list block.
 *
 * @param {Object}   props               Component props passed by the block editor.
 * @param {Object}   props.attributes    Block attribute values.
 * @param {Function} props.setAttributes Setter for block attributes.
 * @return {JSX.Element} The editor UI for this block.
 */
export default function Edit({ attributes, setAttributes }) {
	const { manifestIds, excludedSectionIds = [], headingId = '' } = attributes;
	const { defaultManifestId } = editorData();

	const ids =
		manifestIds && manifestIds.length > 0
			? manifestIds
			: [defaultManifestId].filter(Boolean);
	const isAuto = !manifestIds?.length && ids.length > 0;

	const blockProps = useBlockProps({
		className: wrapperClasses(attributes),
		style: wrapperStyle(attributes),
	});

	// Fetch manifest records including meta from the REST API.
	const { manifests, isLoading } = useSelect(
		(select) => {
			if (0 === ids.length) {
				return { manifests: [], isLoading: false };
			}

			const store = select('core');

			return {
				manifests: ids
					.map((id) =>
						store.getEntityRecord(
							'postType',
							'sm_manifest',
							id,
							ENTITY_FIELDS
						)
					)
					.filter(Boolean),
				isLoading: !ids.every((id) =>
					store.hasFinishedResolution('getEntityRecord', [
						'postType',
						'sm_manifest',
						id,
						ENTITY_FIELDS,
					])
				),
			};
		},
		// eslint-disable-next-line react-hooks/exhaustive-deps -- ids is derived from these.
		[manifestIds, defaultManifestId]
	);

	// Every section of the resolved price lists, for the section picker.
	const allSections = manifests.flatMap((manifest) =>
		parseSections(manifest.meta?._satori_manifest_sections)
	);

	// What every price list block on the page hides (as JSON, so the selector stays stable).
	const pageSetsJson = useSelect((select) => {
		const editor = select('core/block-editor');
		const blocks = editor.getBlocksByName
			? editor.getBlocksByName('satori-manifest/price-list')
			: [];

		return JSON.stringify(
			blocks.map((clientId) => {
				const attrs = editor.getBlockAttributes(clientId) || {};

				return {
					hidden: attrs.excludedSectionIds || [],
					heading: attrs.headingId || '',
				};
			})
		);
	}, []);

	/**
	 * The sections this block shows, as on the page (see shownSections()).
	 *
	 * @param {Array} sections Sections of one manifest.
	 * @return {Array} Sections to display.
	 */
	const pick = (sections) =>
		shownSections(
			sections,
			{ hidden: excludedSectionIds, heading: headingId },
			JSON.parse(pageSetsJson)
		);

	const inspector = (
		<Inspector
			attributes={attributes}
			setAttributes={setAttributes}
			isAuto={isAuto}
			sections={allSections}
		/>
	);

	if (0 === ids.length) {
		return (
			<>
				{inspector}
				<div {...blockProps}>
					<Notice
						status="info"
						isDismissible={false}
						className="satori-manifest-price-list__demo-notice"
					>
						{__(
							'Preview. Choose a price list in the block settings to show real prices.',
							'satori-manifest'
						)}
					</Notice>
					<Manifest
						sections={DEMO_SECTIONS}
						attributes={attributes}
					/>
				</div>
			</>
		);
	}

	if (isLoading) {
		return (
			<>
				{inspector}
				<div {...blockProps}>
					<Spinner />
				</div>
			</>
		);
	}

	return (
		<>
			{inspector}
			<div {...blockProps}>
				{manifests.map((manifest) => (
					<Manifest
						key={manifest.id}
						sections={pick(
							parseSections(
								manifest.meta?._satori_manifest_sections
							)
						)}
						attributes={attributes}
					/>
				))}
			</div>
		</>
	);
}
