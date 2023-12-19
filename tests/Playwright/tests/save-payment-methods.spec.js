const {test, expect} = require('@playwright/test');
const {loginAsCustomer} = require("./utils/user");
const {openPaypalPopup, loginIntoPaypal, completePaypalPayment} = require("./utils/paypal-popup");
const {fillCheckoutForm, expectOrderReceivedPage} = require("./utils/checkout");

const {
    PRODUCT_URL,
} = process.env;

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

test('Save during purchase', async ({page}) => {
    await loginAsCustomer(page)

    await page.goto(PRODUCT_URL);
    const popup = await openPaypalPopup(page);

    await loginIntoPaypal(popup);
    await completePaypalPayment(popup);
    await fillCheckoutForm(page);

    await completeContinuation(page);

    await expectOrderReceivedPage(page);
});




