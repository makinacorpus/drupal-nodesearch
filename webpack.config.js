const CleanWebpackPlugin = require('clean-webpack-plugin');
const ExtractTextPlugin = require("extract-text-webpack-plugin");
const MinifyPlugin = require("babel-minify-webpack-plugin");
const path = require('path');
const webpack = require('webpack');

const distDirectory = path.resolve(__dirname, 'dist');
const extractLess = new ExtractTextPlugin({filename: "nodesearch.css"});

module.exports = {
  entry: ['core-js/modules/es6.promise', './src/index.js'],
  //devtool: 'source-map',
  plugins: [
    new CleanWebpackPlugin([distDirectory]),
    //new MinifyPlugin(),
    extractLess
  ],
  module: {
    rules: [{
      test: /\.tsx?$/,
      exclude: /node_modules/,
      use: [{
        loader: "babel-loader"
      }, {
        loader: "ts-loader"
      }],
    },{
      test: /\.jsx?$/,
      exclude: /node_modules/,
      use: [{
        loader: "babel-loader"
      }]
    },{
      test: /\.less$/,
      use: extractLess.extract({
        fallback: "style-loader",
        use: [{
          loader: "css-loader"
        },{
          loader: "less-loader"
        }]
      })
    }]
  },
  resolve: {
    extensions: [".ts", ".tsx", ".js"]
  },
  externals: {
    "react": "React",
    "react-dom": "ReactDOM"
  },
  output: {
    filename: 'nodesearch.js',
    library: 'NodeSearch',
    libraryTarget:'umd',
    path: distDirectory
  }
};
