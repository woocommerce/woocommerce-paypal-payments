const path         = require('path');
const isProduction = process.env.NODE_ENV === 'production';

const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
    resolve: {
        alias: {
            '@ppcp-googlepay': path.resolve(__dirname, './resources/js'),
            '@ppcp-button': path.resolve(__dirname, '../ppcp-button/resources/js/modules'),
            '@ppcp-blocks': path.resolve(__dirname, '../ppcp-blocks/resources/js'),
        }
    },
    devtool: isProduction ? 'source-map' : 'eval-source-map',
    mode: isProduction ? 'production' : 'development',
    target: 'web',
    plugins: [ new DependencyExtractionWebpackPlugin() ],
    entry: {
        'boot': path.resolve('./resources/js/boot.js'),
        'boot-block': path.resolve('./resources/js/boot-block.js'),
        'boot-admin': path.resolve('./resources/js/boot-admin.js'),
        "styles": path.resolve('./resources/css/styles.scss')
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
