const path = require('path');
const CleanWebpackPlugin = require('clean-webpack-plugin');

module.exports = {
	mode: 'development',
	optimization: {
		usedExports: true
	},
	entry: {
		graphiql: './resources/src/graphiql.js'
	},
	plugins: [
		new CleanWebpackPlugin()
	],
	output: {
		path: path.resolve(__dirname, 'resources/dist'),
		filename: '[name].js',
	},
	externals: {
		oojs: 'OO',
		'oojs-ui': 'OO.ui',
	},
	module: {
		rules: [
			{
				test: /\.(js|js.flow)$/,
				exclude: /node_modules(?!\/graphql-language-service-interface)/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [
							'@babel/preset-env',
							'@babel/preset-flow'
						]
					}
				}
			},
			{
				test: /\.css$/,
				use: ['style-loader', 'css-loader'],
			},
		],
	},
};
