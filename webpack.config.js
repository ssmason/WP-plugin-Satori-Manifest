/**
 * Webpack configuration for Satori Manifest.
 *
 * Compiles every source into build/:
 *
 *   build/block/index.js        ← block/src/index.js          (block editor)
 *   build/js/*.js               ← src/js/*.js                 (admin + front end)
 *   build/css/*.css             ← src/scss/{name}/{name}.scss (admin, editor, block, client page)
 *   build/designs/{slug}.css    ← designs/{slug}/style.scss   (one stylesheet per design)
 *
 * Style-only entries emit no JavaScript.
 *
 * @package
 * @author  Stephen Mason <steve@satori-digital.com>
 * @since   1.0.0
 */

const fs = require('fs');
const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

const isProduction = process.env.NODE_ENV === 'production';

const STYLE_ENTRIES = [
	'admin',
	'editor',
	'frontend',
	'client-page',
	'registration',
];

/**
 * Builds the entry map, discovering designs from the designs/ folder.
 *
 * @return {Object} Webpack entry map.
 */
function entries() {
	const map = {
		'build/block/index': './block/src/index.js',
		'build/js/manifest-admin': './src/js/manifest-admin.js',
		'build/js/client-page': './src/js/client-page.js',
	};

	STYLE_ENTRIES.forEach((name) => {
		map[`build/css/${name}`] = `./src/scss/${name}/${name}.scss`;
	});

	fs.readdirSync(path.resolve(__dirname, 'designs'), {
		withFileTypes: true,
	})
		.filter((entry) => entry.isDirectory())
		.filter((entry) =>
			fs.existsSync(
				path.resolve(__dirname, 'designs', entry.name, 'style.scss')
			)
		)
		.forEach((entry) => {
			map[`build/designs/${entry.name}`] =
				`./designs/${entry.name}/style.scss`;
		});

	return map;
}

/**
 * Script dependencies of the block editor bundle (WordPress script handles).
 *
 * Written to build/block/index.asset.php so WordPress loads them before
 * the block's editorScript.
 *
 * @type {string[]}
 */
const BLOCK_DEPENDENCIES = [
	'wp-block-editor',
	'wp-blocks',
	'wp-components',
	'wp-data',
	'wp-element',
	'wp-i18n',
];

/**
 * Build housekeeping:
 * - removes the empty .js files webpack emits for style-only entries;
 * - writes the block script's index.asset.php dependency file.
 */
class BuildHousekeeping {
	/**
	 * @param {Object} compiler Webpack compiler.
	 */
	apply(compiler) {
		const { RawSource } = compiler.webpack.sources;

		compiler.hooks.thisCompilation.tap(
			'BuildHousekeeping',
			(compilation) => {
				compilation.hooks.processAssets.tap(
					{
						name: 'BuildHousekeeping',
						stage: compilation.constructor
							.PROCESS_ASSETS_STAGE_OPTIMIZE,
					},
					(assets) => {
						Object.keys(assets)
							.filter((name) =>
								/^build\/(css|designs)\/.+\.js(\.map)?$/.test(
									name
								)
							)
							.forEach((name) => compilation.deleteAsset(name));

						const deps = BLOCK_DEPENDENCIES.map(
							(dep) => `'${dep}'`
						).join(', ');
						compilation.emitAsset(
							'build/block/index.asset.php',
							new RawSource(
								`<?php return array( 'dependencies' => array( ${deps} ), 'version' => '${compilation.hash}' );\n`
							)
						);
					}
				);
			}
		);
	}
}

module.exports = {
	entry: entries(),
	output: {
		path: path.resolve(__dirname),
		filename: '[name].js',
	},
	module: {
		rules: [
			{
				test: /\.jsx?$/,
				exclude: /node_modules/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [
							'@babel/preset-env',
							// Classic runtime against wp.element: uses WordPress's
							// own React instead of bundling a second copy.
							[
								'@babel/preset-react',
								{
									runtime: 'classic',
									pragma: 'wp.element.createElement',
									pragmaFrag: 'wp.element.Fragment',
								},
							],
						],
					},
				},
			},
			{
				test: /\.scss$/,
				use: [
					MiniCssExtractPlugin.loader,
					{ loader: 'css-loader', options: { url: false } },
					'sass-loader',
				],
			},
		],
	},
	plugins: [
		new MiniCssExtractPlugin({ filename: '[name].css' }),
		new BuildHousekeeping(),
	],
	externals: {
		'@wordpress/blocks': ['wp', 'blocks'],
		'@wordpress/block-editor': ['wp', 'blockEditor'],
		'@wordpress/components': ['wp', 'components'],
		'@wordpress/element': ['wp', 'element'],
		'@wordpress/i18n': ['wp', 'i18n'],
		'@wordpress/data': ['wp', 'data'],
		'@wordpress/icons': ['wp', 'icons'],
	},
	resolve: {
		extensions: ['.js', '.jsx'],
	},
	mode: isProduction ? 'production' : 'development',
	devtool: isProduction ? false : 'source-map',
};
