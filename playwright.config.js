require('dotenv').config({ path: '.env.e2e' });

const config = {
    testDir: './tests/playwright',
    timeout: 50000,
    use: {
        baseURL: process.env.BASEURL,
    },
};

module.exports = config;
