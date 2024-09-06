const { test, expect } = require( '@playwright/test' );
const { serverExec } = require( './utils/server' );
const {
	fillCheckoutForm,
	expectOrderReceivedPage,
} = require( './utils/checkout' );
const {
	openPaypalPopup,
	loginIntoPaypal,
	completePaypalPayment,
} = require( './utils/paypal-popup' );

const {
	CREDIT_CARD_NUMBER,
	CREDIT_CARD_CVV,
	PRODUCT_URL,
	CHECKOUT_URL,
	CHECKOUT_PAGE_ID,
} = process.env;

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

test.beforeAll( async ( { browser } ) => {
	await serverExec(
		'wp option update woocommerce_checkout_page_id ' + CHECKOUT_PAGE_ID
	);
} );

test( 'PayPal button place order from Product page', async ( { page } ) => {
	await serverExec(
		'wp pcp settings update blocks_final_review_enabled true'
	);

	await page.goto( PRODUCT_URL );

	const popup = await openPaypalPopup( page );

	await loginIntoPaypal( popup );

	await completePaypalPayment( popup );

	await fillCheckoutForm( page );

	await completeContinuation( page );

	await expectOrderReceivedPage( page );
} );

test( 'Advanced Credit and Debit Card place order from Checkout page', async ( {
	page,
} ) => {
	await page.goto( PRODUCT_URL );
	await page.locator( '.single_add_to_cart_button' ).click();

	await page.goto( CHECKOUT_URL );
	await fillCheckoutForm( page );

	await page.click( 'text=Credit Cards' );

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

	await Promise.all( [
		page.waitForNavigation(),
		page.locator( '.ppcp-dcc-order-button' ).click(),
	] );

	await expectOrderReceivedPage( page );
} );
