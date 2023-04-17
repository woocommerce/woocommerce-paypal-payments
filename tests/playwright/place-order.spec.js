const {test, expect} = require('@playwright/test');

const {
    CUSTOMER_EMAIL,
    CUSTOMER_PASSWORD,
    CREDIT_CARD_NUMBER,
    CREDIT_CARD_EXPIRATION,
    CREDIT_CARD_CVV,
    PRODUCT_URL,
    PRODUCT_ID,
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

async function openPaypalPopup(page) {
    await page.locator('.component-frame').scrollIntoViewIfNeeded();

    const [popup] = await Promise.all([
        page.waitForEvent('popup'),
        page.frameLocator('.component-frame').locator('[data-funding-source="paypal"]').click(),
    ]);

    await popup.waitForLoadState();

    return popup;
}

async function loginIntoPaypal(popup) {
    await popup.click("text=Log in");
    await popup.fill('[name="login_email"]', CUSTOMER_EMAIL);
    await popup.locator('#btnNext').click();
    await popup.fill('[name="login_password"]', CUSTOMER_PASSWORD);
    await popup.locator('#btnLogin').click();
}

async function expectOrderReceivedPage(page) {
    const title = await page.locator('.entry-title');
    await expect(title).toHaveText('Order received');
}

test('PayPal button place order from Product page', async ({page}) => {

    await page.goto(PRODUCT_URL);

    const popup = await openPaypalPopup(page);

    await loginIntoPaypal(popup);

    await popup.locator('#payment-submit-btn').click();

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

    await expectOrderReceivedPage(page);
});

test('PayPal express block', async ({page}) => {

    await page.goto('/cart?add-to-cart=' + PRODUCT_ID);

    await page.goto('/blocks-checkout')

    const popup = await openPaypalPopup(page);

    await loginIntoPaypal(popup);

    await popup.locator('#payment-submit-btn').click();

    await page.waitForNavigation();

    const title = await page.locator('.entry-title');
    await expect(title).toHaveText('Order received');
});
