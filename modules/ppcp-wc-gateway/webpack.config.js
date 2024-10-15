const path         = require('path');
const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
    devtool: isProduction ? 'source-map' : 'eval-source-map',
    mode: isProduction ? 'production' : 'development',
    target: 'web',
    entry: {
        'common': path.resolve('./resources/js/common.js'),
        'gateway-settings': path.resolve('./resources/js/gateway-settings.js'),
        'fraudnet': path.resolve('./resources/js/fraudnet.js'),
        'oxxo': path.resolve('./resources/js/oxxo.js'),
        'void-button': path.resolve('./resources/js/void-button.js'),
        'gateway-settings-style': path.resolve('./resources/css/gateway-settings.scss'),
        'common-style': path.resolve('./resources/css/common.scss'),
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
        },
        {
            test: /\.scss$/,
            exclude: /node_modules/,
            use: [
                {
                    loader: 'file-loader',
                    options: {
                        name: 'css/[name].css',
                    }
                },
                {loader:'sass-loader'}
            ]
        }]
    }
};
