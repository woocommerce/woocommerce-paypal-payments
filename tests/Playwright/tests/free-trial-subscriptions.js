const { test, expect } = require( '@playwright/test' );
const { loginAsCustomer } = require( './utils/user' );
const { openPaypalPopup, loginIntoPaypal } = require( './utils/paypal-popup' );

test( 'PayPal logged-in user free trial subscription without payment token', async ( {
	page,
} ) => {
	await loginAsCustomer( page );

	await page.goto( '/product/free-trial' );
	await page.click( 'text=Sign up now' );
	await page.goto( '/classic-checkout' );

	const popup = await openPaypalPopup( page );
	await loginIntoPaypal( popup );
	popup.locator( '#consentButton' ).click();

	await page.click( 'text=Proceed to PayPal' );

	const title = await page.locator( '.entry-title' );
	await expect( title ).toHaveText( 'Order received' );
} );
