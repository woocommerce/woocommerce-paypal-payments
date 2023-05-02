const {test, expect} = require('@playwright/test');
const {serverExec} = require("./utils/server");

const {
    CUSTOMER_EMAIL,
    CUSTOMER_PASSWORD,
    CREDIT_CARD_NUMBER,
    CREDIT_CARD_EXPIRATION,
    CREDIT_CARD_CVV,
    PRODUCT_URL,
    PRODUCT_ID,
    CHECKOUT_URL,
    CHECKOUT_PAGE_ID,
    CART_URL,
    BLOCK_CHECKOUT_URL,
    BLOCK_CHECKOUT_PAGE_ID,
    BLOCK_CART_URL,
} = process.env;

async function fillCheckoutForm(page) {
    await page.fill('#billing_first_name', 'John');
    await page.fill('#billing_last_name', 'Doe');
    await page.selectOption('select#billing_country', 'DE');
    await page.fill('#billing_address_1', 'Badensche Str. 24');
    await page.fill('#billing_postcode', '10715');
    await page.fill('#billing_city', '10715');
    await page.fill('#billing_phone', '1234567890');
    await page.fill('#billing_email', CUSTOMER_EMAIL);

    const differentShippingLocator = page.locator('[name="ship_to_different_address"]');
    if (await differentShippingLocator.count() > 0) {
        await differentShippingLocator.uncheck();
    }

    const termsLocator = page.locator('[name="terms"]');
    if (await termsLocator.count() > 0) {
        await termsLocator.check();
    }
}

async function openPaypalPopup(page, retry = true) {
    try {
        await page.locator('.component-frame').scrollIntoViewIfNeeded();

        const [popup] = await Promise.all([
            page.waitForEvent('popup', {timeout: 5000}),
            page.frameLocator('.component-frame').locator('[data-funding-source="paypal"]').click(),
        ]);

        await popup.waitForLoadState();

        return popup;
    } catch (err) {
        if (retry) {
            return openPaypalPopup(page, false);
        }
        throw err;
    }
}

async function loginIntoPaypal(popup) {
    await Promise.any([
        popup.locator('[name="login_email"]'),
        popup.click("text=Log in"),
    ]);

    await popup.fill('[name="login_email"]', CUSTOMER_EMAIL);
    await popup.locator('#btnNext').click();
    await popup.fill('[name="login_password"]', CUSTOMER_PASSWORD);
    await popup.locator('#btnLogin').click();
}

async function waitForPaypalShippingList(popup) {
    await expect(popup.locator('#shippingMethodsDropdown')).toBeVisible({timeout: 15000});
}

async function completePaypalPayment(popup) {
    await Promise.all([
        popup.waitForEvent('close', {timeout: 20000}),
        popup.click('#payment-submit-btn'),
    ]);
}

async function expectOrderReceivedPage(page) {
    const title = await page.locator('.entry-title');
    await expect(title).toHaveText('Order received');
}

async function completeBlockContinuation(page) {
    await expect(page.locator('#radio-control-wc-payment-method-options-ppcp-gateway')).toBeChecked();

    await expect(page.locator('.component-frame')).toHaveCount(0);

    await page.locator('.wc-block-components-checkout-place-order-button').click();

    await page.waitForNavigation();

    await expectOrderReceivedPage(page);
}

test.describe('Classic checkout', () => {
    test.beforeAll(async ({ browser }) => {
        await serverExec('wp option update woocommerce_checkout_page_id ' + CHECKOUT_PAGE_ID);
    });

    test('PayPal button place order from Product page', async ({page}) => {
        await page.goto(PRODUCT_URL);

        const popup = await openPaypalPopup(page);

        await loginIntoPaypal(popup);

        await completePaypalPayment(popup);

        await fillCheckoutForm(page);

        await Promise.all([
            page.waitForNavigation(),
            page.locator('#place_order').click(),
        ]);

        await expectOrderReceivedPage(page);
    });

    test('Advanced Credit and Debit Card (ACDC) place order from Checkout page', async ({page}) => {
        await page.goto(PRODUCT_URL);
        await page.locator('.single_add_to_cart_button').click();

        await page.goto(CHECKOUT_URL);
        await fillCheckoutForm(page);

        await page.click("text=Credit Cards");

        const creditCardNumber = page.frameLocator('#braintree-hosted-field-number').locator('#credit-card-number');
        await creditCardNumber.fill(CREDIT_CARD_NUMBER);

        const expirationDate = page.frameLocator('#braintree-hosted-field-expirationDate').locator('#expiration');
        await expirationDate.fill(CREDIT_CARD_EXPIRATION);

        const cvv = page.frameLocator('#braintree-hosted-field-cvv').locator('#cvv');
        await cvv.fill(CREDIT_CARD_CVV);

        await Promise.all([
            page.waitForNavigation(),
            page.locator('.ppcp-dcc-order-button').click(),
        ]);

        await expectOrderReceivedPage(page);
    });
});

test.describe('Block checkout', () => {
    test.beforeAll(async ({browser}) => {
        await serverExec('wp option update woocommerce_checkout_page_id ' + BLOCK_CHECKOUT_PAGE_ID);
        await serverExec('wp pcp settings update blocks_final_review_enabled true');
    });

    test('PayPal express block checkout', async ({page}) => {
        await page.goto('?add-to-cart=' + PRODUCT_ID);

        await page.goto(BLOCK_CHECKOUT_URL)

        const popup = await openPaypalPopup(page);

        await loginIntoPaypal(popup);

        await completePaypalPayment(popup);

        await completeBlockContinuation(page);
    });

    test('PayPal express block cart', async ({page}) => {
        await page.goto(BLOCK_CART_URL + '?add-to-cart=' + PRODUCT_ID)

        const popup = await openPaypalPopup(page);

        await loginIntoPaypal(popup);

        await completePaypalPayment(popup);

        await completeBlockContinuation(page);
    });

    test.describe('Without review', () => {
        test.beforeAll(async ({browser}) => {
            await serverExec('wp pcp settings update blocks_final_review_enabled false');
        });

        test('PayPal express block checkout', async ({page}) => {
            await page.goto('?add-to-cart=' + PRODUCT_ID);

            await page.goto(BLOCK_CHECKOUT_URL)

            const popup = await openPaypalPopup(page);

            await loginIntoPaypal(popup);

            await waitForPaypalShippingList(popup);

            await completePaypalPayment(popup);

            await expectOrderReceivedPage(page);
        });

        test('PayPal express block cart', async ({page}) => {
            await page.goto(BLOCK_CART_URL + '?add-to-cart=' + PRODUCT_ID)

            const popup = await openPaypalPopup(page);

            await loginIntoPaypal(popup);

            await waitForPaypalShippingList(popup);

            await completePaypalPayment(popup);

            await expectOrderReceivedPage(page);
        });
    });
});
