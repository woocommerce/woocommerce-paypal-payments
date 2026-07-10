const path         = require('path');
const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
    devtool: isProduction ? 'source-map' : 'eval-source-map',
    mode: isProduction ? 'production' : 'development',
    target: 'web',
    entry: {
        boot: path.resolve('./resources/js/boot.js'),
    },
    output: {
        path: path.resolve(__dirname, 'assets/'),
        filename: 'js/[name].js',
    },
    module: {
        rules: [{
            test: /\.js?$/,
            exclude: /node_modules/,
            loader: 'babel-loader',
        }]
    }
};
