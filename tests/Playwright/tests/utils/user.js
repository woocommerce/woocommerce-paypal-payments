const {
    WP_MERCHANT_USER,
    WP_MERCHANT_PASSWORD,
    WP_CUSTOMER_USER,
    WP_CUSTOMER_PASSWORD,
} = process.env;

export const loginAsAdmin = async (page) => {
    await page.goto('/wp-admin');
    await page.locator('input[name="log"]').fill(WP_MERCHANT_USER);
    await page.locator('input[name="pwd"]').fill(WP_MERCHANT_PASSWORD);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Log In').click()
    ]);
}

export const loginAsCustomer = async (page) => {
    await page.goto('/wp-admin');
    await page.locator('input[name="log"]').fill(WP_CUSTOMER_USER);
    await page.locator('input[name="pwd"]').fill(WP_CUSTOMER_PASSWORD);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Log In').click()
    ]);
}
