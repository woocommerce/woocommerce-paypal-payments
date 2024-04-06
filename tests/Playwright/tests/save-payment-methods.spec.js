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

test('PayPal add payment method', async ({page}) => {
    await loginAsCustomer(page);
    await page.goto('/my-account/add-payment-method');

    const popup = await openPaypalPopup(page);
    await loginIntoPaypal(popup);
    popup.locator('#consentButton').click();

    await page.waitForURL('/my-account/payment-methods');
});

test('ACDC add payment method', async ({page}) => {
    await loginAsCustomer(page);
    await page.goto('/my-account/add-payment-method');

    await page.click("text=Debit & Credit Cards");

    const creditCardNumber = await page.frameLocator('[title="paypal_card_number_field"]').locator('.card-field-number');
    await creditCardNumber.fill('4005519200000004');

    const expirationDate = await page.frameLocator('[title="paypal_card_expiry_field"]').locator('.card-field-expiry');
    await expirationDate.fill('01/25');

    const cvv = await page.frameLocator('[title="paypal_card_cvv_field"]').locator('.card-field-cvv');
    await cvv.fill('123');

    await page.waitForURL('/my-account/payment-methods');
});

test('PayPal logged-in user free trial subscription without payment token', async ({page}) => {
    await loginAsCustomer(page);

    await page.goto('/shop');
    await page.click("text=Sign up now");
    await page.goto('/classic-checkout');

    const popup = await openPaypalPopup(page);
    await loginIntoPaypal(popup);
    popup.locator('#consentButton').click();

    await page.click("text=Proceed to PayPal");

    const title = await page.locator('.entry-title');
    await expect(title).toHaveText('Order received');
})





