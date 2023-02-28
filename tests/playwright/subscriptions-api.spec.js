const {test, expect} = require('@playwright/test');
const {
    WP_MERCHANT_USER,
    WP_MERCHANT_PASSWORD,
    AUTHORIZATION
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

test.describe('Merchant', () => {
    test('Create new subscription product', async ({page, request}) => {
        await loginAsAdmin(page);

        const title = (Math.random() + 1).toString(36).substring(7);
        await page.goto('/wp-admin/post-new.php?post_type=product');
        await page.fill('#title', title);
        await page.selectOption('select#product-type', 'subscription');
        await page.fill('#_subscription_price', '10');

        await Promise.all([
            page.waitForNavigation(),
            page.locator('#publish').click(),
        ]);

        const message = await page.locator('.notice-success');
        await expect(message).toContainText('Product published.');

        const products = await request.get('https://api.sandbox.paypal.com/v1/catalogs/products?page_size=100&page=1&total_required=true', {
            headers: {
                'Authorization': AUTHORIZATION,
                'Content-Type': 'application/json'
            }
        });
        expect(products.ok()).toBeTruthy();

        const productList = await products.json();
        const product = productList.products.find((p) => {
            return p.name === title;
        });
        await expect(product.id).toBeTruthy;

        const plans = await request.get(`https://api.sandbox.paypal.com/v1/billing/plans?product_id=${product.id}&page_size=10&page=1&total_required=true`, {
            headers: {
                'Authorization': AUTHORIZATION,
                'Content-Type': 'application/json'
            }
        });
        expect(plans.ok()).toBeTruthy();

        const planList = await plans.json();
        const plan = planList.plans.find((p) => {
            return p.product_id === product.id;
        });
        await expect(plan.id).toBeTruthy;
    });
});
