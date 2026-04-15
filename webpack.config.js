const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		applicationAdmin: path.resolve(__dirname, 'src/Blocks/components/admin-settings/assets/index.js'),
		applicationEditor: path.resolve(__dirname, 'src/Blocks/components/editor-sidebar/assets/index.js'),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve(__dirname, 'public'),
		filename: '[name].js',
	},
};
