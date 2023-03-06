const {
    WP_MERCHANT_USER,
    WP_MERCHANT_PASSWORD,
    WP_CUSTOMER_USER,
    WP_CUSTOMER_PASSWORD,
    CUSTOMER_EMAIL,
    CUSTOMER_FIRST_NAME,
    CUSTOMER_LAST_NAME,
    CUSTOMER_COUNTRY,
    CUSTOMER_ADDRESS,
    CUSTOMER_POSTCODE,
    CUSTOMER_CITY,
    CUSTOMER_PHONE
} = process.env;

async function loginAsAdmin(page) {
    await page.goto('/wp-admin');
    await page.locator('input[name="log"]').fill(WP_MERCHANT_USER);
    await page.locator('input[name="pwd"]').fill(WP_MERCHANT_PASSWORD);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Log In').click()
    ]);
}

async function loginAsCustomer(page) {
    await page.goto('/wp-admin');
    await page.locator('input[name="log"]').fill(WP_CUSTOMER_USER);
    await page.locator('input[name="pwd"]').fill(WP_CUSTOMER_PASSWORD);
    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Log In').click()
    ]);
}

async function fillCheckoutForm(page) {
    await page.fill('#billing_first_name', CUSTOMER_FIRST_NAME);
    await page.fill('#billing_last_name', CUSTOMER_LAST_NAME);
    await page.selectOption('select#billing_country', CUSTOMER_COUNTRY);
    await page.fill('#billing_address_1', CUSTOMER_ADDRESS);
    await page.fill('#billing_postcode', CUSTOMER_POSTCODE);
    await page.fill('#billing_city', CUSTOMER_CITY);
    await page.fill('#billing_phone', CUSTOMER_PHONE);
    await page.fill('#billing_email', CUSTOMER_EMAIL);
}

module.exports = {loginAsAdmin, loginAsCustomer, fillCheckoutForm};
