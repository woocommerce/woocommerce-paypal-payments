const {test, expect} = require('@playwright/test');

const {
    BASEURL,
} = process.env;

test('Visit home page', async ({page}) => {
    await page.goto(BASEURL);
});
