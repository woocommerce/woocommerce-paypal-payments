const path         = require('path');
const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
    devtool: 'eval-source-map',
    mode: isProduction ? 'production' : 'development',
    target: 'web',
    entry: {
        'status-page': path.resolve('./resources/js/status-page.js'),
        'status-page-style': path.resolve('./resources/css/status-page.scss'),
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
