import {expect} from "@playwright/test";

const {
    CUSTOMER_EMAIL,
    CUSTOMER_PASSWORD,
} = process.env;

/**
 * Opens the PayPal popup by pressing the button, and returns the popup object.
 * @param page
 * @param {boolean} retry Retries the button click if the popup did not appear after 5 sec.
 */
export const openPaypalPopup = async (page, retry = true) => {
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

export const loginIntoPaypal = async (popup) => {
    await Promise.any([
        popup.locator('[name="login_email"]'),
        popup.click("text=Log in"),
    ]);

    await popup.fill('[name="login_email"]', CUSTOMER_EMAIL);
    await popup.locator('#btnNext').click();
    await popup.fill('[name="login_password"]', CUSTOMER_PASSWORD);
    await popup.locator('#btnLogin').click();
}

/**
 * Waits up to 15 sec for the shipping methods list to load.
 * @param popup
 * @returns {Promise<void>}
 */
export const waitForPaypalShippingList = async (popup) => {
    await expect(popup.locator('#shippingMethodsDropdown')).toBeVisible({timeout: 15000});
}

export const completePaypalPayment = async (popup) => {
    await Promise.all([
        popup.waitForEvent('close', {timeout: 20000}),
        popup.click('#payment-submit-btn'),
    ]);
}
