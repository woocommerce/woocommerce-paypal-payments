/**
 * External dependencies
 */
import {
	WooCommerceOrderEdit as WooCommerceOrderEditBase,
	expect,
	formatMoney,
} from '@inpsyde/playwright-utils/build';

export class WooCommerceOrderEdit extends WooCommerceOrderEditBase {
	// Locators
	paymentVia = ( method ) =>
		this.orderNumberContainer().getByText( `Payment via ${ method }` );
	transactionIdLink = ( transactionId ) =>
		this.orderNumberContainer().getByRole( 'link', {
			name: transactionId,
		} );
	payPalEmailAddress = () =>
		this.page
			.locator( 'p' )
			.filter( { hasText: 'PayPal email address:' } )
			.getByRole( 'link' );

	totalPayPalFee = () => this.totalsTableRow( 'PayPal Fee:' );
	totalPayPalPayout = () => this.totalsTableRow( 'PayPal Payout' );

	refundViaButton = ( paymentMethod ) =>
		this.page.locator( '.do-api-refund', {
			hasText: `via ${ paymentMethod }`,
		} );

	productsTable = () => this.page.locator( '#order_line_items' );
	productRow = ( name ) => this.productsTable().getByRole( 'row', { name } );
	productRefundQtyInput = ( name ) =>
		this.productRow( name ).locator( '.refund_order_item_qty' );
	productRefundTotalInput = ( name ) =>
		this.productRow( name ).locator( '.refund_line_total' );
	productRefundTaxInput = ( name ) =>
		this.productRow( name ).locator( '.refund_line_tax' );

	firstRefundTotalInput = () =>
		this.productsTable().locator( '.refund_line_total' ).first();

	totalPayPalRefundFee = () => this.totalsTableRow( 'PayPal Refund Fee:' );
	totalPayPalRefunded = () => this.totalsTableRow( 'PayPal Refunded:' );
	totalPayPalNetTotal = () => this.totalsTableRow( 'PayPal Net Total:' );

	seeOXXOVoucherButton = () =>
		this.page.getByRole( 'link', { name: 'See OXXO voucher' } ).first();

	payPalPackageTrackingSection = () =>
		this.page.locator( '#ppcp_order-tracking' );

	cvv2MatchOrderNote = () =>
		this.orderNoteContent().filter( { hasText: 'CVV2 Match: Y' } );
	addressVerificationOrderNote = () =>
		this.orderNoteContent().filter( {
			hasText: 'Address Verification Result',
		} );

	notCapturedIndicator = () => this.page.getByText( 'Not captured' );
	voidButton = () => this.page.locator( '#pcpVoid' );

	// Actions

	/**
	 * Performs refund
	 *
	 * @param amount
	 */
	makePayPalRefund = async ( amount?: string ) => {
		// Make full-amount refund if amount is not specified
		if ( ! amount ) {
			const totalAmount =
				( await this.totalAvailableToRefund().textContent() ) || '';
			amount = parseFloat(
				totalAmount.replace( /[^\d.-]+/g, '' ).trim()
			).toFixed( 2 );
		}

		await this.firstRefundTotalInput().fill( amount );
		await this.page.on( 'dialog', ( dialog ) => dialog.accept() );
		await this.refundViaButton( 'PayPal' ).click();
		await this.page.waitForLoadState( 'networkidle' );
	};

	// Assertions
	assertPayPalEmailAddress = async ( email: string ) => {
		await expect(
			this.payPalEmailAddress(),
			`Assert PayPal email address is ${ email }`
		).toHaveText( email );
	};

	/**
	 * Asserts order note with Address Verification Result for ACDC
	 *
	 * @param payment
	 */
	assertAddressVerificationResult = async ( payment ) => {
		// TODO: clarify expected order notes format
		// await expect(this.cvv2MatchOrderNote()).toBeVisible();
		// const orderNote = await this.addressVerificationOrderNote();
		// await expect(orderNote).toContainText(`AVS: Y`);
		// await expect(orderNote).toContainText(`Address Match: N`);
		// await expect(orderNote).toContainText(`Postal Match: N`);
		// await expect(orderNote).toContainText(`Card Brand: ${payment.card_type}`);
		// await expect(orderNote).toContainText(`Card Last Digits: ${payment.card_number.slice(-4)}`);
	};

	/**
	 * Asserts intent authorized state elements on order edit page
	 */
	assertIntentAuthorizedState = async () => {
		await expect(
			this.notCapturedIndicator(),
			'Assert not captured indicator is visible'
		).toBeVisible();
		await expect(
			this.voidButton(),
			'Assert void button is visible'
		).toBeVisible();
		await expect(
			this.voidButton(),
			'Assert void button is enabled'
		).toBeEnabled();
	};

	/**
	 * Asserts order edit page including PayPal related fields
	 *
	 * @param orderData
	 * @param pcpData
	 * @param pcpData.transactionId
	 * @param pcpData.payPalFee
	 * @param pcpData.payPalPayout
	 * @param pcpData.paymentMethod
	 * @param pcpData.itemsSubtotal
	 * @param pcpData.totalCoupons
	 * @param pcpData.totalFees
	 * @param pcpData.totalShipping
	 * @param pcpData.orderTotal
	 * @param pcpData.currency
	 */
	assertOrderDetails = async (
		orderData: WooCommerce.ShopOrder,
		pcpData?: {
			transactionId?: string;
			payPalFee?: string;
			payPalPayout?: string;
			paymentMethod?: string;
			itemsSubtotal?: string;
			totalCoupons?: string;
			totalFees?: string;
			totalShipping?: string;
			orderTotal?: string;
			currency?: string;
		}
	) => {
		await super.assertOrderDetails( orderData );

		if ( ! pcpData || Object.keys( pcpData ).length === 0 ) {
			return;
		}

		// Transaction ID
		if (
			pcpData.transactionId !== undefined &&
			pcpData.orderTotal !== undefined
		) {
			await expect(
				this.transactionIdLink( pcpData.transactionId ),
				`Assert transaction ID link with ID ${ pcpData.transactionId } is visible`
			).toBeVisible();
		}

		// PayPal fees
		if (
			pcpData.payPalFee !== undefined &&
			pcpData.orderTotal !== undefined
		) {
			await expect(
				this.totalPayPalFee(),
				'Assert total PayPal fee is expected'
			).toHaveText(
				'- ' +
					( await formatMoney(
						Number( pcpData.payPalFee ),
						orderData.currency
					) )
			);
		}

		//PayPal payout
		if (
			pcpData.payPalPayout !== undefined &&
			pcpData.orderTotal !== undefined
		) {
			await expect(
				this.totalPayPalPayout(),
				'Assert total PayPal payout is expected'
			).toHaveText(
				await formatMoney(
					Number( pcpData.payPalPayout ),
					orderData.currency
				)
			);
		}

		if ( orderData.payment.gateway.shortcut === 'oxxo' ) {
			await expect(
				this.seeOXXOVoucherButton(),
				'Assert OXXO voucher button is visible'
			).toBeVisible();
		}

		if ( orderData.payment.gateway.shortcut === 'acdc' ) {
			await this.assertAddressVerificationResult(
				orderData.payment.card
			);
		}

		if (
			[ 'paypal', 'paylater', 'venmo' ].includes(
				orderData.payment.gateway.shortcut
			)
		) {
			await this.assertPayPalEmailAddress(
				orderData.payment.payPalAccount.email
			);
		}

		// Intent Authorization assertions
		if ( orderData.payment.isAuthorized ) {
			await this.assertIntentAuthorizedState();
		}
	};

	/**
	 * Asserts refund fields on the order edit page
	 *
	 * @param data
	 * @param data.orderStatus
	 * @param data.refundId
	 * @param data.refundAmount
	 * @param data.refundTotal
	 * @param data.netPayment
	 * @param data.payPalFee
	 * @param data.payPalRefundFee
	 * @param data.payPalRefunded
	 * @param data.payPalPayout
	 * @param data.payPalNetTotal
	 * @param data.currency
	 */
	assertRefundData = async (
		data: {
			orderStatus?: WooCommerce.OrderStatus;
			refundId?: number;
			refundAmount?: number;
			refundTotal?: number;
			netPayment?: number;
			payPalFee?: number;
			payPalRefundFee?: number;
			payPalRefunded?: number;
			payPalPayout?: number;
			payPalNetTotal?: number;
			currency?: string;
		} = {
			currency: 'EUR',
		}
	) => {
		const {
			payPalFee,
			payPalRefundFee,
			payPalRefunded,
			payPalPayout,
			payPalNetTotal,
			currency,
		} = data;

		await super.assertRefundData( data );

		if ( payPalFee !== undefined ) {
			await expect( this.totalPayPalFee(), 'Assert total PayPal fee is expected' ).toHaveText(
				'- ' + ( await formatMoney( payPalFee, currency ) )
			);
		}

		if ( payPalRefundFee !== undefined ) {
			await expect( this.totalPayPalRefundFee(), 'Assert total PayPal refund fee is expected' ).toHaveText(
				'- ' + ( await formatMoney( payPalRefundFee, currency ) )
			);
		}

		if ( payPalRefunded !== undefined ) {
			await expect( this.totalPayPalRefunded(), 'Assert total PayPal refunded is expected' ).toHaveText(
				'- ' + ( await formatMoney( payPalRefunded, currency ) )
			);
		}

		if ( payPalPayout !== undefined ) {
			await expect( this.totalPayPalPayout(), 'Assert total PayPal payout is expected' ).toHaveText(
				await formatMoney( payPalPayout, currency )
			);
		}

		if ( payPalNetTotal !== undefined ) {
			await expect( this.totalPayPalNetTotal(), 'Assert total PayPal net total is expected' ).toHaveText(
				await formatMoney( payPalNetTotal, currency )
			);
		}
	};
}
