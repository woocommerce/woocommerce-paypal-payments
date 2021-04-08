const path         = require('path');
const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
    devtool: 'sourcemap',
    mode: isProduction ? 'production' : 'development',
    target: 'web',
    entry: {
        'gateway-settings': path.resolve('./resources/js/gateway-settings.js'),
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
