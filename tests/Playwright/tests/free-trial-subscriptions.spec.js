const { test, expect } = require( '@playwright/test' );
const { loginAsCustomer } = require( './utils/user' );
const { openPaypalPopup, loginIntoPaypal } = require( './utils/paypal-popup' );
const { serverExec } = require( './utils/server' );
const { expectOrderReceivedPage } = require( './utils/checkout' );

const { CREDIT_CARD_NUMBER, CREDIT_CARD_CVV } = process.env;

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

	await page.waitForURL( '**/order-received/**' );
} );

test( 'ACDC logged-in user free trial subscription without payment token', async ( {
	page,
} ) => {
	await loginAsCustomer( page );
	await page.goto( '/product/free-trial' );
	await page.click( 'text=Sign up now' );
	await page.goto( '/classic-checkout' );

	await page.click( 'text=Credit Cards' );

	const creditCardNumber = await page
		.frameLocator( '[title="paypal_card_number_field"]' )
		.locator( '.card-field-number' );
	await creditCardNumber.fill( CREDIT_CARD_NUMBER );

	const expirationDate = await page
		.frameLocator( 'iframe[title="paypal_card_expiry_field"]' )
		.locator( 'input.card-field-expiry' );
	await expirationDate.click();
	await page.keyboard.type( '01/42' );

	const cvv = await page
		.frameLocator( '[title="paypal_card_cvv_field"]' )
		.locator( '.card-field-cvv' );
	await cvv.fill( CREDIT_CARD_CVV );

	await Promise.all( [
		page.waitForNavigation(),
		page.locator( '.ppcp-dcc-order-button' ).click(),
	] );

	await expectOrderReceivedPage( page );
} );

test( 'ACDC purchase free trial in Block checkout page as logged-in without saved card payments', async ( {
	page,
} ) => {
	await loginAsCustomer( page );
	await page.goto( '/product/free-trial' );
	await page.click( 'text=Sign up now' );
	await page.goto( '/checkout' );

	await page
		.locator(
			'#radio-control-wc-payment-method-options-ppcp-credit-card-gateway'
		)
		.click();

	const expirationDate = await page
		.frameLocator( 'iframe[title="paypal_card_expiry_field"]' )
		.locator( 'input.card-field-expiry' );
	await expirationDate.click();
	await page.keyboard.type( '01/42' );

	const creditCardNumber = await page
		.frameLocator( '[title="paypal_card_number_field"]' )
		.locator( '.card-field-number' );
	await creditCardNumber.fill( CREDIT_CARD_NUMBER );

	const cvv = await page
		.frameLocator( '[title="paypal_card_cvv_field"]' )
		.locator( '.card-field-cvv' );
	await cvv.fill( CREDIT_CARD_CVV );

	await page
		.locator( '.wc-block-components-checkout-place-order-button' )
		.click();

	await page.waitForURL( '**/order-received/**' );
} );
