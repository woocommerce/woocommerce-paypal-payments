const { test, expect } = require('@playwright/test');

test('has ngrok url', async ({page, baseURL}) => {
    await page.goto('/');
    await expect(page).toHaveURL(baseURL);
});
