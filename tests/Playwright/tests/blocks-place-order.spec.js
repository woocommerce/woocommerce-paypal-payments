const { expect, test } = require( '@playwright/test' );
const { serverExec } = require( './utils/server' );
const {
	openPaypalPopup,
	loginIntoPaypal,
	completePaypalPayment,
	waitForPaypalShippingList,
} = require( './utils/paypal-popup' );
const { expectOrderReceivedPage } = require( './utils/checkout' );

const {
	PRODUCT_ID,
	BLOCK_CHECKOUT_URL,
	BLOCK_CHECKOUT_PAGE_ID,
	BLOCK_CART_URL,
} = process.env;

async function completeBlockContinuation( page ) {
	await expect(
		page.locator( '#radio-control-wc-payment-method-options-ppcp-gateway' )
	).toBeChecked();

	await expect( page.locator( '.component-frame' ) ).toHaveCount( 0 );

	await Promise.all(
		page.waitForNavigation(),
		page
			.locator( '.wc-block-components-checkout-place-order-button' )
			.click()
	);
}

test.beforeAll( async ( { browser } ) => {
	await serverExec(
		'wp option update woocommerce_checkout_page_id ' +
			BLOCK_CHECKOUT_PAGE_ID
	);
	await serverExec(
		'wp pcp settings update blocks_final_review_enabled true'
	);
} );

test( 'PayPal express block checkout', async ( { page } ) => {
	await page.goto( '?add-to-cart=' + PRODUCT_ID );

	await page.goto( BLOCK_CHECKOUT_URL );

	const popup = await openPaypalPopup( page );

	await loginIntoPaypal( popup );

	await completePaypalPayment( popup );

	await completeBlockContinuation( page );

	await expectOrderReceivedPage( page );
} );

test( 'PayPal express block cart', async ( { page } ) => {
	await page.goto( BLOCK_CART_URL + '?add-to-cart=' + PRODUCT_ID );

	const popup = await openPaypalPopup( page );

	await loginIntoPaypal( popup );

	await completePaypalPayment( popup );

	await completeBlockContinuation( page );

	await expectOrderReceivedPage( page );
} );

test.describe( 'Without review', () => {
	test.beforeAll( async ( { browser } ) => {
		await serverExec(
			'wp pcp settings update blocks_final_review_enabled false'
		);
	} );

	test( 'PayPal express block checkout', async ( { page } ) => {
		await page.goto( '?add-to-cart=' + PRODUCT_ID );

		await page.goto( BLOCK_CHECKOUT_URL );

		const popup = await openPaypalPopup( page );

		await loginIntoPaypal( popup );

		await waitForPaypalShippingList( popup );

		await completePaypalPayment( popup );

		await expectOrderReceivedPage( page );
	} );

	test( 'PayPal express block cart', async ( { page } ) => {
		await page.goto( BLOCK_CART_URL + '?add-to-cart=' + PRODUCT_ID );

		const popup = await openPaypalPopup( page );

		await loginIntoPaypal( popup );

		await waitForPaypalShippingList( popup );

		await completePaypalPayment( popup );

		await expectOrderReceivedPage( page );
	} );
} );
