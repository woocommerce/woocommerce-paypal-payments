const {test, expect} = require('@playwright/test');

const {loginAsAdmin, loginAsCustomer} = require('./utils/user');
const {openPaypalPopup, loginIntoPaypal, completePaypalPayment} = require("./utils/paypal-popup");
const {fillCheckoutForm, expectOrderReceivedPage} = require("./utils/checkout");
const {createProduct, deleteProduct, updateProduct, updateProductUi} = require("./utils/products");
const {
    AUTHORIZATION,
    SUBSCRIPTION_URL,
    CHECKOUT_URL,
    CART_URL,
} = process.env;

const longText = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ultricies integer quis auctor elit sed vulputate mi. Aliquam sem et tortor consequat id porta nibh venenatis cras. Massa enim nec dui nunc. Nulla porttitor massa id neque aliquam vestibulum morbi blandit cursus. Eu lobortis elementum nibh tellus molestie nunc. Euismod nisi porta lorem mollis aliquam ut porttitor. Ultrices tincidunt arcu non sodales neque sodales ut etiam. Urna cursus eget nunc scelerisque. Pulvinar sapien et ligula ullamcorper malesuada proin libero. Convallis a cras semper auctor neque vitae tempus quam pellentesque. Phasellus egestas tellus rutrum tellus pellentesque eu tincidunt tortor aliquam. Cras tincidunt lobortis feugiat vivamus. Nec ultrices dui sapien eget mi proin sed libero enim. Neque gravida in fermentum et sollicitudin ac orci phasellus egestas. Aliquam faucibus purus in massa. Viverra accumsan in nisl nisi scelerisque eu ultrices vitae. At augue eget arcu dictum varius duis. Commodo ullamcorper a lacus vestibulum sed arcu non odio.\n' +
    '\n' +
    'Id cursus metus aliquam eleifend mi in nulla. A diam sollicitudin tempor id eu nisl. Faucibus purus in massa tempor. Lacus luctus accumsan tortor posuere ac ut consequat. Mauris augue neque gravida in fermentum et sollicitudin ac. Venenatis tellus in metus vulputate. Consectetur libero id faucibus nisl tincidunt eget. Pellentesque eu tincidunt tortor aliquam nulla facilisi cras fermentum odio. Dolor sed viverra ipsum nunc aliquet bibendum. Turpis in eu mi bibendum neque. Ac tincidunt vitae semper quis lectus nulla at volutpat. Felis imperdiet proin fermentum leo vel orci porta. Sed sed risus pretium quam vulputate dignissim.\n' +
    '\n' +
    'Urna et pharetra pharetra massa massa ultricies mi quis. Egestas purus viverra accumsan in nisl nisi. Elit sed vulputate mi sit amet mauris commodo. Cras fermentum odio eu feugiat pretium nibh ipsum consequat. Justo laoreet sit amet cursus sit amet dictum. Nunc id cursus metus aliquam. Tortor at auctor urna nunc id. Quis lectus nulla at volutpat diam ut. Lorem ipsum dolor sit amet consectetur adipiscing elit pellentesque. Tincidunt lobortis feugiat vivamus at augue eget arcu dictum varius.\n' +
    '\n' +
    'Mattis nunc sed blandit libero. Vitae ultricies leo integer malesuada nunc vel risus. Dapibus ultrices in iaculis nunc. Interdum varius sit amet mattis. Tortor vitae purus faucibus ornare. Netus et malesuada fames ac turpis. Elit duis tristique sollicitudin nibh sit amet. Lacus suspendisse faucibus interdum posuere lorem. In pellentesque massa placerat duis. Fusce ut placerat orci nulla pellentesque dignissim. Dictum fusce ut placerat orci nulla pellentesque dignissim enim. Nibh sit amet commodo nulla facilisi. Maecenas sed enim ut sem. Non consectetur a erat nam at lectus urna duis convallis. Diam phasellus vestibulum lorem sed risus ultricies tristique nulla. Nunc congue nisi vitae suscipit. Tortor condimentum lacinia quis vel eros donec ac. Eleifend mi in nulla posuere.\n' +
    '\n' +
    'Vestibulum lectus mauris ultrices eros. Massa sed elementum tempus egestas sed sed risus. Ut placerat orci nulla pellentesque dignissim enim sit. Duis ut diam quam nulla porttitor. Morbi tincidunt ornare massa eget egestas purus. Commodo sed egestas egestas fringilla phasellus faucibus scelerisque eleifend donec. Arcu odio ut sem nulla pharetra diam sit. Risus sed vulputate odio ut enim. Faucibus et molestie ac feugiat. A scelerisque purus semper eget. Odio facilisis mauris sit amet massa vitae tortor. Condimentum vitae sapien pellentesque habitant morbi tristique senectus. Nec feugiat in fermentum posuere urna. Volutpat est velit egestas dui id ornare arcu odio ut. Ullamcorper malesuada proin libero nunc consequat interdum. Suspendisse in est ante in nibh mauris cursus mattis molestie. Vel eros donec ac odio tempor orci dapibus. Et tortor at risus viverra adipiscing at in tellus. Metus aliquam eleifend mi in.'

async function purchaseSubscriptionFromCart(page) {
    await loginAsCustomer(page);
    await page.goto(SUBSCRIPTION_URL);
    await page.click("text=Sign up now");
    await page.goto(CART_URL);

    const popup = await openPaypalPopup(page);
    await loginIntoPaypal(popup);

    await popup.getByText('Continue', {exact: true}).click();
    await popup.locator('#confirmButtonTop').click();

    await fillCheckoutForm(page);

    await Promise.all([
        page.waitForNavigation(),
        page.locator('text=Sign up now').click(),
    ]);

    await expectOrderReceivedPage(page);
}

test.describe.serial('Subscriptions Merchant', () => {
    const productTitle = (Math.random() + 1).toString(36).substring(7);
    const planName = (Math.random() + 1).toString(36).substring(7);
    let product_id = '';
    let plan_id = '';

    test('Create new subscription product', async ({page, request}) => {
        await loginAsAdmin(page);

        await page.goto('/wp-admin/post-new.php?post_type=product');
        await page.fill('#title', productTitle);
        await page.selectOption('select#product-type', 'subscription');
        await page.fill('#_subscription_price', '10');
        await page.locator('#ppcp_enable_subscription_product').check();
        await page.fill('#ppcp_subscription_plan_name', planName);

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
            return p.name === productTitle;
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
        await page.getByRole('link', {name: productTitle, exact: true}).click();

        await page.fill('#title', `Updated ${productTitle}`);
        await page.fill('#_subscription_price', '20');
        await page.fill('#content', longText)

        await Promise.all([
            page.waitForNavigation(),
            page.locator('#publish').click(),
        ]);

        const message = await page.locator('.notice-success');
        await expect(message).toContainText('Product updated.');

        const products = await request.get('https://api.sandbox.paypal.com/v1/catalogs/products?page_size=100&page=1&total_required=true', {
            headers: {
                'Authorization': AUTHORIZATION,
                'Content-Type': 'application/json'
            }
        });
        expect(products.ok()).toBeTruthy();

        const productList = await products.json();
        const product = productList.products.find((p) => {
            return p.name === `Updated ${productTitle}`;
        });
        await expect(product.id).toBeTruthy;

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

test('Create new free trial subscription product', async ({page, request}) => {
    const productTitle = (Math.random() + 1).toString(36).substring(7);
    const planName = (Math.random() + 1).toString(36).substring(7);
    await loginAsAdmin(page);

    await page.goto('/wp-admin/post-new.php?post_type=product');
    await page.fill('#title', productTitle);
    await page.selectOption('select#product-type', 'subscription');
    await page.fill('#_subscription_price', '42');
    await page.fill('#_subscription_trial_length', '15');

    await page.locator('#ppcp_enable_subscription_product').check();
    await page.fill('#ppcp_subscription_plan_name', planName);

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
        return p.name === productTitle;
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

    const planDetail = await request.get(`https://api.sandbox.paypal.com/v1/billing/plans/${plan.id}`, {
        headers: {
            'Authorization': AUTHORIZATION,
            'Content-Type': 'application/json'
        }
    });
    expect(planDetail.ok()).toBeTruthy();
    const planDetailContent = await planDetail.json();

    await expect(planDetailContent.billing_cycles[0].tenure_type).toBe('TRIAL');
    await expect(planDetailContent.billing_cycles[0].pricing_scheme.fixed_price.value).toBe('0.0');
    await expect(planDetailContent.billing_cycles[1].tenure_type).toBe('REGULAR');
    await expect(planDetailContent.billing_cycles[1].pricing_scheme.fixed_price.value).toBe('42.0');
});

test.describe('Subscriber purchase a Subscription', () => {
    test('Purchase Subscription from Checkout Page', async ({page}) => {
        await loginAsCustomer(page);

        await page.goto(SUBSCRIPTION_URL);
        await page.click("text=Sign up now");
        await page.goto(CHECKOUT_URL);
        await fillCheckoutForm(page);

        const popup = await openPaypalPopup(page);
        await loginIntoPaypal(popup);

        await popup.getByText('Continue', {exact: true}).click();

        await Promise.all([
            page.waitForNavigation(),
            await popup.locator('text=Agree & Subscribe').click(),
        ]);

        await expectOrderReceivedPage(page);
    });

    test('Purchase Subscription from Single Product Page', async ({page}) => {
        await loginAsCustomer(page);
        await page.goto(SUBSCRIPTION_URL);

        const popup = await openPaypalPopup(page);
        await loginIntoPaypal(popup);

        await popup.getByText('Continue', {exact: true}).click();
        await popup.locator('#confirmButtonTop').click();

        await fillCheckoutForm(page);

        await Promise.all([
            page.waitForNavigation(),
            page.locator('text=Sign up now').click(),
        ]);

        await expectOrderReceivedPage(page);
    });

    test('Purchase Subscription from Cart Page', async ({page}) => {
        await purchaseSubscriptionFromCart(page);
    });
});

test.describe('Subscriber my account actions', () => {
    test('Subscriber Suspend Subscription', async ({page, request}) => {
        await purchaseSubscriptionFromCart(page);
        await page.goto('/my-account/subscriptions');
        await page.locator('text=View').first().click();

        const subscriptionId = await page.locator('#ppcp-subscription-id').textContent();
        let subscription = await request.get(`https://api.sandbox.paypal.com/v1/billing/subscriptions/${subscriptionId}`, {
            headers: {
                'Authorization': AUTHORIZATION,
                'Content-Type': 'application/json'
            }
        });
        expect(subscription.ok()).toBeTruthy();
        let details = await subscription.json();
        await expect(details.status).toBe('ACTIVE');

        await page.locator('text=Suspend').click();
        const title = page.locator('.woocommerce-message');
        await expect(title).toHaveText('Your subscription has been cancelled.');

        subscription = await request.get(`https://api.sandbox.paypal.com/v1/billing/subscriptions/${subscriptionId}`, {
            headers: {
                'Authorization': AUTHORIZATION,
                'Content-Type': 'application/json'
            }
        });
        expect(subscription.ok()).toBeTruthy();

        details = await subscription.json();
        await expect(details.status).toBe('SUSPENDED');
    });

    test('Subscriber Cancel Subscription', async ({page, request}) => {
        await purchaseSubscriptionFromCart(page);
        await page.goto('/my-account/subscriptions');
        await page.locator('text=View').first().click();

        const subscriptionId = await page.locator('#ppcp-subscription-id').textContent();
        let subscription = await request.get(`https://api.sandbox.paypal.com/v1/billing/subscriptions/${subscriptionId}`, {
            headers: {
                'Authorization': AUTHORIZATION,
                'Content-Type': 'application/json'
            }
        });
        expect(subscription.ok()).toBeTruthy();
        let details = await subscription.json();
        await expect(details.status).toBe('ACTIVE');

        await page.locator('text=Cancel').click();
        const title = page.locator('.woocommerce-message');
        await expect(title).toHaveText('Your subscription has been cancelled.');

        subscription = await request.get(`https://api.sandbox.paypal.com/v1/billing/subscriptions/${subscriptionId}`, {
            headers: {
                'Authorization': AUTHORIZATION,
                'Content-Type': 'application/json'
            }
        });
        expect(subscription.ok()).toBeTruthy();

        details = await subscription.json();
        await expect(details.status).toBe('CANCELLED');
    });
});

test.describe('Plan connected display buttons', () => {
    test('Disable buttons if no plan connected', async ({page}) => {
        const data = {
            name: (Math.random() + 1).toString(36).substring(7),
            type: 'subscription',
            meta_data: [
                {
                    key: '_subscription_price',
                    value: '10'
                }
            ]
        }
        const productId = await createProduct(data)

        // for some reason product meta is not updated in frontend,
        // so we need to manually update the product
        await updateProductUi(productId, page);

        await page.goto(`/product/?p=${productId}`)
        await expect(page.locator('#ppc-button-ppcp-gateway')).not.toBeVisible();

        await page.locator('.single_add_to_cart_button').click();
        await page.goto('/cart');
        await expect(page.locator('#ppc-button-ppcp-gateway')).toBeVisible();
        await expect(page.locator('#ppc-button-ppcp-gateway')).toHaveCSS('cursor', 'not-allowed')

        await page.goto('/checkout');
        await expect(page.locator('#ppc-button-ppcp-gateway')).toBeVisible();
        await expect(page.locator('#ppc-button-ppcp-gateway')).toHaveCSS('cursor', 'not-allowed')

        await deleteProduct(productId)
    })

    test('Enable buttons if plan connected', async ({page}) => {
        const data = {
            name: (Math.random() + 1).toString(36).substring(7),
            type: 'subscription',
            meta_data: [
                {
                    key: '_subscription_price',
                    value: '10'
                }
            ]
        }
        const productId = await createProduct(data)

        await loginAsAdmin(page);
        await page.goto(`/wp-admin/post.php?post=${productId}&action=edit`)
        await page.locator('#ppcp_enable_subscription_product').check();
        await page.locator('#ppcp_subscription_plan_name').fill('Plan name');
        await page.locator('#publish').click();
        await expect(page.getByText('Product updated.')).toBeVisible();

        await page.goto(`/product/?p=${productId}`)
        await expect(page.locator('#ppc-button-ppcp-gateway')).toBeVisible();

        await page.locator('.single_add_to_cart_button').click();
        await page.goto('/cart');
        await expect(page.locator('#ppc-button-ppcp-gateway')).toBeVisible();

        await deleteProduct(productId)
    })
})

