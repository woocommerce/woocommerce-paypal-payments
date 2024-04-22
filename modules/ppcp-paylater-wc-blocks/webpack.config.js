const path = require("path");
const isProduction = process.env.NODE_ENV === "production";

const DependencyExtractionWebpackPlugin = require("@woocommerce/dependency-extraction-webpack-plugin");

module.exports = {
    devtool: isProduction ? "source-map" : "eval-source-map",
    mode: isProduction ? "production" : "development",
    target: "web",
    plugins: [new DependencyExtractionWebpackPlugin()],
    entry: {
        "cart-paylater-block": path.resolve(
            process.cwd(),
            "resources",
            "js",
            "CartPayLaterMessagesBlock",
            "cart-paylater-block.js"
        ),
        "checkout-paylater-block": path.resolve(
            process.cwd(),
            "resources",
            "js",
            "CheckoutPayLaterMessagesBlock",
            "checkout-paylater-block.js"
        ),
    },
    output: {
        path: path.resolve(__dirname, "assets/"),
        filename: "js/[name].js",
    },
    module: {
        rules: [
            {
                test: /\.js?$/,
                exclude: /node_modules/,
                loader: "babel-loader",
                options: {
                    presets: ["@babel/preset-env", "@babel/preset-react"],
                },
            },
            {
                test: /\.scss$/,
                exclude: /node_modules/,
                use: [
                    {
                        loader: "file-loader",
                        options: {
                            name: "css/[name].css",
                        },
                    },
                    { loader: "sass-loader" },
                ],
            },
        ],
    },
};
