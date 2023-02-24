/**
 * Read environment variables from file.
 * https://github.com/motdotla/dotenv
 */
require('dotenv').config();

const config = {
    testDir: './tests/playwright',
    timeout: 30000,
    use: {
        baseURL: process.env.BASEURL,
    },
};

module.exports = config;
