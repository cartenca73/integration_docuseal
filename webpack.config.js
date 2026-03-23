const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

// Override entry points
webpackConfig.entry = {
	adminSettings: path.join(__dirname, 'src', 'adminSettings.js'),
	filesplugin: path.join(__dirname, 'src', 'filesplugin.js'),
	sidebar: path.join(__dirname, 'src', 'sidebar.js'),
}

module.exports = webpackConfig
