const { test, expect } = require( '@playwright/test' );
const {
	fillCheckoutForm,
	expectOrderReceivedPage,
	acceptTerms,
} = require( './utils/checkout' );
const {
	openPaypalPopup,
	completePaypalPayment,
} = require( './utils/paypal-popup' );

const { PRODUCT_ID, CHECKOUT_URL, CART_URL, APM_ID } = process.env;

async function expectContinuation( page ) {
	await expect(
		page.locator( '#payment_method_ppcp-gateway' )
	).toBeChecked();

	await expect( page.locator( '.component-frame' ) ).toHaveCount( 0 );
}

async function completeContinuation( page ) {
	await expectContinuation( page );

	await Promise.all( [
		page.waitForNavigation(),
		page.locator( '#place_order' ).click(),
	] );
}

test( 'PayPal APM button place order', async ( { page } ) => {
	await page.goto( CART_URL + '?add-to-cart=' + PRODUCT_ID );

	await page.goto( CHECKOUT_URL );

	await fillCheckoutForm( page );

	const popup = await openPaypalPopup( page, { fundingSource: APM_ID } );

	await popup.getByText( 'Continue', { exact: true } ).click();
	await completePaypalPayment( popup, {
		selector: '[name="Successful"]',
	} );

	await expectOrderReceivedPage( page );
} );

test( 'PayPal APM button place order when redirect fails', async ( {
	page,
} ) => {
	await page.goto( CART_URL + '?add-to-cart=' + PRODUCT_ID );

	await page.goto( CHECKOUT_URL );

	await fillCheckoutForm( page );

	await page.evaluate( 'PayPalCommerceGateway.ajax.approve_order = null' );

	const popup = await openPaypalPopup( page, { fundingSource: APM_ID } );

	await popup.getByText( 'Continue', { exact: true } ).click();
	await completePaypalPayment( popup, {
		selector: '[name="Successful"]',
	} );

	await expect( page.locator( '.woocommerce-error' ) ).toBeVisible();

	await page.reload();
	await expectContinuation( page );

	await acceptTerms( page );

	await completeContinuation( page );

	await expectOrderReceivedPage( page );
} );
