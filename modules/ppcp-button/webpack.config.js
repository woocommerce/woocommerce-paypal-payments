const path         = require('path');
const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
    devtool: 'sourcemap',
    mode: isProduction ? 'production' : 'development',
    target: 'web',
    entry: {
        button: path.resolve('./resources/js/button.js')
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
            options: {
                presets: [
                    [
                        '@babel/preset-env',
                        {
                            targets: {
                                ie: "11"
                            }
                        }
                    ]
                ],
                plugins: [
                    "babel-plugin-transform-object-rest-spread"
                ]
            }
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
