import {expect} from "@playwright/test";

const {
    CUSTOMER_EMAIL,
    CUSTOMER_PASSWORD,
} = process.env;

/**
 * Opens the PayPal popup by pressing the button, and returns the popup object.
 * @param page
 * @param {boolean} retry Retries the button click if the popup did not appear after timeout.
 * @param {int} timeout
 */
export const openPaypalPopup = async (page, retry = true, timeout = 5000) => {
    try {
        await page.locator('.component-frame').scrollIntoViewIfNeeded();

        const [popup] = await Promise.all([
            page.waitForEvent('popup', {timeout}),
            page.frameLocator('.component-frame').locator('[data-funding-source="paypal"]').click(),
        ]);

        await popup.waitForLoadState();

        return popup;
    } catch (err) {
        try {
            for (const f of page.mainFrame().childFrames()) {
                if (f.name().startsWith('__paypal_checkout')) {
                    for (const f2 of f.childFrames()) {
                        if (f.name().includes('__paypal_checkout')) {
                            await f2.waitForLoadState();
                            await expect(await f2.locator('#main')).toBeVisible();
                            return f2;
                        }
                    }
                }
            }
        } catch (frameErr) {
             console.log(frameErr)
        }

        if (retry) {
            return openPaypalPopup(page, false);
        }
        throw err;
    }
}

export const loginIntoPaypal = async (popup, retry = true) => {
    await Promise.any([
        popup.locator('[name="login_email"]'),
        popup.click("text=Log in"),
    ]);

    await popup.fill('[name="login_email"]', CUSTOMER_EMAIL);

    const nextButtonLocator = popup.locator('#btnNext');
    // Sometimes we get a popup with email and password fields at the same screen
    if (await nextButtonLocator.count() > 0) {
        await nextButtonLocator.click();
    }

    try {
        await popup.fill('[name="login_password"]', CUSTOMER_PASSWORD, {timeout: 5000});
    } catch (err) {
        console.log('Failed to fill password, possibly need to enter email again, retrying')
        if (retry) {
            return loginIntoPaypal(popup, false);
        }
        throw err;
    }

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
