const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
	'files-integration': path.join(__dirname, 'src', 'files-integration.js'),
}

webpackConfig.module = webpackConfig.module || { rules: [] }
webpackConfig.module.rules = webpackConfig.module.rules || []
webpackConfig.module.rules.push({
	test: /\.m?js$/,
	include: /node_modules/,
	resolve: {
		fullySpecified: false,
	},
})

// The NC webpack-vue-config hardcodes publicPath to '/apps/{appName}/js/' which
// breaks dynamic chunk loading when the app is installed in custom_apps/ instead
// of apps/.  Setting 'auto' makes webpack compute the base URL at runtime from
// the <script> src attribute that Nextcloud generates (which already points at
// the correct path, be it /apps/ or /custom_apps/).
webpackConfig.output = Object.assign({}, webpackConfig.output, {
	publicPath: 'auto',
})

module.exports = webpackConfig
