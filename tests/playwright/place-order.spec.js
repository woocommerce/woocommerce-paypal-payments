const {test, expect} = require('@playwright/test');
const {fillCheckoutForm} = require('./utils');
const {
    CUSTOMER_EMAIL,
    CUSTOMER_PASSWORD,
    CREDIT_CARD_NUMBER,
    CREDIT_CARD_EXPIRATION,
    CREDIT_CARD_CVV
} = process.env;

test('PayPal button place order from Product page', async ({page}) => {
    await page.goto('/product/product/');

    const [popup] = await Promise.all([
        page.waitForEvent('popup'),
        page.frameLocator('.component-frame').locator('[data-funding-source="paypal"]').click(),
    ]);
    await popup.waitForLoadState();

    await popup.click("text=Log in");
    await popup.fill('#email', CUSTOMER_EMAIL);
    await popup.locator('#btnNext').click();
    await popup.fill('#password', CUSTOMER_PASSWORD);
    await popup.locator('#btnLogin').click();

    await popup.locator('#payment-submit-btn').click();
    await fillCheckoutForm(page);

    await Promise.all([
        page.waitForNavigation(),
        page.locator('#place_order').click(),
    ]);

    const title = await page.locator('.entry-title');
    await expect(title).toHaveText('Order received');
});

test('Advanced Credit and Debit Card (ACDC) place order from Checkout page @ci', async ({page}) => {

    await page.goto('/product/product/');
    await page.locator('.single_add_to_cart_button').click();

    await page.goto('/checkout/');
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

    const title = await page.locator('.entry-title');
    await expect(title).toHaveText('Order received');
});
