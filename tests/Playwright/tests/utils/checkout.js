import {expect} from "@playwright/test";

const {
    CUSTOMER_EMAIL,
    CUSTOMER_FIRST_NAME,
    CUSTOMER_LAST_NAME,
    CUSTOMER_COUNTRY,
    CUSTOMER_ADDRESS,
    CUSTOMER_POSTCODE,
    CUSTOMER_CITY,
    CUSTOMER_PHONE,
} = process.env;

export const fillCheckoutForm = async (page) => {
    await page.fill('#billing_first_name', CUSTOMER_FIRST_NAME);
    await page.fill('#billing_last_name', CUSTOMER_LAST_NAME);
    await page.selectOption('select#billing_country', CUSTOMER_COUNTRY);
    await page.fill('#billing_address_1', CUSTOMER_ADDRESS);
    await page.fill('#billing_postcode', CUSTOMER_POSTCODE);
    await page.fill('#billing_city', CUSTOMER_CITY);
    await page.fill('#billing_phone', CUSTOMER_PHONE);
    await page.fill('#billing_email', CUSTOMER_EMAIL);

    const differentShippingLocator = page.locator('[name="ship_to_different_address"]');
    if (await differentShippingLocator.count() > 0) {
        await differentShippingLocator.uncheck();
    }

    await acceptTerms(page);
}

export const acceptTerms = async (page) => {
    const termsLocator = page.locator('[name="terms"]');
    if (await termsLocator.count() > 0) {
        await termsLocator.check();
    }
}

export const expectOrderReceivedPage = async (page) => {
    const title = await page.locator('.entry-title');
    await expect(title).toHaveText('Order received');
}
