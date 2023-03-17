const {test, expect} = require('@playwright/test');
const {fillCheckoutForm, loginAsAdmin, loginAsCustomer} = require('./utils');
const {
    AUTHORIZATION,
    CUSTOMER_EMAIL,
    CUSTOMER_PASSWORD
} = process.env;

test.describe.serial('Subscriptions Merchant', () => {
    const title = (Math.random() + 1).toString(36).substring(7);
    let product_id = '';
    let plan_id = '';

    test('Create new subscription product', async ({page, request}) => {
        await loginAsAdmin(page);

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

        product_id = product.id;

        const plans = await request.get(`https://api.sandbox.paypal.com/v1/billing/plans?product_id=${product_id}&page_size=10&page=1&total_required=true`, {
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

        plan_id = plan.id;
    });

    test('Update subscription product', async ({page, request}) => {
        await loginAsAdmin(page);

        await page.goto('/wp-admin/edit.php?post_type=product');
        await page.getByRole('link', { name: title, exact: true }).click();

        await page.fill('#_subscription_price', '20');

        await Promise.all([
            page.waitForNavigation(),
            page.locator('#publish').click(),
        ]);

        const message = await page.locator('.notice-success');
        await expect(message).toContainText('Product updated.');

        const plan = await request.get(`https://api.sandbox.paypal.com/v1/billing/plans/${plan_id}`, {
            headers: {
                'Authorization': AUTHORIZATION,
                'Content-Type': 'application/json'
            }
        });
        expect(plan.ok()).toBeTruthy();

        const plan_content = await plan.json();
        await expect(plan_content.billing_cycles[0].pricing_scheme.fixed_price.value).toBe('20.0')
    });
});

test.describe('Subscriptions Customer', () => {
    test('Purchase subscription', async ({page}) => {
        await loginAsCustomer(page);

        await page.goto('/product/another-sub');
        await page.click("text=Sign up now");
        await page.goto('/checkout');
        await fillCheckoutForm(page);

        const [popup] = await Promise.all([
            page.waitForEvent('popup'),
            page.frameLocator('.component-frame').locator('[data-funding-source="paypal"]').click(),
        ]);
        await popup.waitForLoadState();

        await popup.fill('#email', CUSTOMER_EMAIL);
        await popup.locator('#btnNext').click();
        await popup.fill('#password', CUSTOMER_PASSWORD);
        await popup.locator('#btnLogin').click();
        await popup.locator('text=Continue').click();
        await popup.locator('text=Agree & Subscribe').click();

        await page.waitForTimeout(10000);

        const title = page.locator('.entry-title');
        await expect(title).toHaveText('Order received');
    });
});