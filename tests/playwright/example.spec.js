const { test, expect } = require('@playwright/test');

test('has title', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/WooCommerce PayPal Payments/);
});
