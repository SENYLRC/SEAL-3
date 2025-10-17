const defaults = require('@wordpress/scripts/config/webpack.config');
const { merge } = require('webpack-merge');
const path = require('path');
module.exports = merge(defaults, {
  externals: {
    react: 'React',
    'react-dom': 'ReactDOM',
  },

  entry: {
    index: './src/index.js',
    functions: './src/functions.js'
  },
  output: {
      path: path.resolve(__dirname, 'build'),
  },
  
  module: {
    rules: [
      // Rule for TinyMCE JavaScript files
      {
        test: /\.svg$/,
        issuer: /\.jsx?$/,
        use: [
          {
            loader: '@svgr/webpack',
            options: {
              icon: true,
            },
          },
          'file-loader',
        ],
      },

      {
        test: /\.(png|jpe?g|gif|svg)$/i,
        type: 'asset/resource',
       
      },
     
    ],
  },
});