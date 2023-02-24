/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
require('dotenv').config();

const config = {
    timeout: 30000,
    use: {
        baseURL: process.env.BASEURL,
    },
};

module.exports = config;
