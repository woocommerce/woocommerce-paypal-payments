const { test, expect } = require( '@playwright/test' );
const { loginAsCustomer } = require( './utils/user' );
const { openPaypalPopup, loginIntoPaypal } = require( './utils/paypal-popup' );
const { serverExec } = require( './utils/server' );

test( 'PayPal logged-in user free trial subscription without payment token with shipping callback enabled', async ( {
	page,
} ) => {
	await serverExec(
		'wp pcp settings update blocks_final_review_enabled false'
	);

	await loginAsCustomer( page );

	await page.goto( '/product/free-trial' );
	await page.click( 'text=Sign up now' );
	await page.goto( '/classic-checkout' );

	const popup = await openPaypalPopup( page );
	await loginIntoPaypal( popup );
	popup.locator( '#consentButton' ).click();

	const title = await page.locator( '.entry-title' );
	await expect( title ).toHaveText( 'Order received' );
} );
