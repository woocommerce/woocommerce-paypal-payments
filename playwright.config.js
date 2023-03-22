require('dotenv').config({ path: '.env.e2e' });

const config = {
    testDir: './tests/playwright',
    timeout: 30000,
    use: {
        baseURL: process.env.BASEURL,
        ignoreHTTPSErrors: true,
    },
};

module.exports = config;
