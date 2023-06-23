const {test, expect} = require('@playwright/test');
const {serverExec} = require("./utils/server");
const {fillCheckoutForm, expectOrderReceivedPage} = require("./utils/checkout");
const {openPaypalPopup, loginIntoPaypal, waitForPaypalShippingList, completePaypalPayment} = require("./utils/paypal-popup");

const {
    CREDIT_CARD_NUMBER,
    CREDIT_CARD_EXPIRATION,
    CREDIT_CARD_CVV,
    PRODUCT_URL,
    PRODUCT_ID,
    CHECKOUT_URL,
    CHECKOUT_PAGE_ID,
    BLOCK_CHECKOUT_URL,
    BLOCK_CHECKOUT_PAGE_ID,
    BLOCK_CART_URL,
} = process.env;

async function completeBlockContinuation(page) {
    await expect(page.locator('#radio-control-wc-payment-method-options-ppcp-gateway')).toBeChecked();

    await expect(page.locator('.component-frame')).toHaveCount(0);

    await Promise.all(
        page.waitForNavigation(),
        page.locator('.wc-block-components-checkout-place-order-button').click(),
    );
}

async function expectContinuation(page) {
    await expect(page.locator('#payment_method_ppcp-gateway')).toBeChecked();

    await expect(page.locator('.component-frame')).toHaveCount(0);
}

async function completeContinuation(page) {
    await expectContinuation(page);

    await Promise.all([
        page.waitForNavigation(),
        page.locator('#place_order').click(),
    ]);
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

        await completeContinuation(page);

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

        await expectOrderReceivedPage(page);
    });

    test('PayPal express block cart', async ({page}) => {
        await page.goto(BLOCK_CART_URL + '?add-to-cart=' + PRODUCT_ID)

        const popup = await openPaypalPopup(page);

        await loginIntoPaypal(popup);

        await completePaypalPayment(popup);

        await completeBlockContinuation(page);

        await expectOrderReceivedPage(page);
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
