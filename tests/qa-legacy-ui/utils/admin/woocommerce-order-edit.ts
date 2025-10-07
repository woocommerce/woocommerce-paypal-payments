/**
 * External dependencies
 */
import {
	WooCommerceOrderEdit as wooCommerceOrderEditBase,
	expect,
	formatMoney,
} from '@inpsyde/playwright-utils/build';

export class WooCommerceOrderEdit extends wooCommerceOrderEditBase {
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
		// await this.page.on('dialog', dialog => dialog.accept());
		await this.refundViaButton( 'PayPal' ).click();
	};

	// Assertions
	assertPayPalEmailAddress = async ( email: string ) => {
		await expect( this.payPalEmailAddress() ).toHaveText( email );
	};

	/**
	 * Asserts order note with Address Verification Result for ACDC
	 *
	 * @param payment
	 */
	assertAddressVerificationResult = async ( payment ) => {
		// await expect(this.cvv2MatchOrderNote()).toBeVisible();
		// const orderNote = await this.addressVerificationOrderNote();
		// await expect(orderNote).toContainText(`AVS: Y`);
		// await expect(orderNote).toContainText(`Address Match: N`);
		// await expect(orderNote).toContainText(`Postal Match: N`);
		// await expect(orderNote).toContainText(`Card Brand: ${payment.card_type}`);
		// await expect(orderNote).toContainText(`Card Last Digits: ${payment.card_number.slice(-4)}`);
	};

	/**
	 * Asserts data provided on the order page
	 *
	 * @param orderId
	 * @param orderData
	 * @param pcpData   = {
	 *                  transactionId: 'QW23134212341234',
	 *                  paypalFee: '1.00',
	 *                  paypalPayout: '29.00',
	 *
	 *                  paymentMethod: 'PayPal',
	 *                  itemsSubtotal: '20.00',
	 *                  totalCoupons: '10.00',
	 *                  totalFees: '10.00',
	 *                  totalShipping: '10.00',
	 *                  orderTotal: '30.00',
	 *                  currency: 'EUR'
	 *                  }
	 */
	assertOrderDetails = async (
		orderId: number,
		orderData: WooCommerce.ShopOrder,
		pcpData?
	) => {
		await super.assertOrderDetails( orderId, orderData );

		if ( ! pcpData ) {
			return;
		}

		// Transaction ID
		if (
			pcpData.transaction_id !== undefined &&
			pcpData.orderTotal !== undefined
		) {
			await expect(
				this.transactionIdLink( pcpData.transaction_id )
			).toBeVisible();
		}

		// PayPal fees
		if (
			pcpData.paypPalFee !== undefined &&
			pcpData.orderTotal !== undefined
		) {
			await expect( this.totalPayPalFee() ).toHaveText(
				'- ' +
					( await formatMoney(
						pcpData.payPalFee,
						orderData.currency
					) )
			);
		}

		//PayPal payout
		if (
			pcpData.payPalPayout !== undefined &&
			pcpData.orderTotal !== undefined
		) {
			await expect( this.totalPayPalPayout() ).toHaveText(
				await formatMoney( pcpData.payPalPayout, orderData.currency )
			);
		}

		if ( orderData.payment.dataFundingSource === 'oxxo' ) {
			await expect( this.seeOXXOVoucherButton() ).toBeVisible();
		}

		if ( orderData.payment.dataFundingSource === 'acdc' ) {
			await this.assertAddressVerificationResult(
				orderData.payment.card
			);
		}

		if (
			[ 'paypal', 'paylater', 'venmo' ].includes(
				orderData.payment.dataFundingSource
			)
		) {
			await this.assertPayPalEmailAddress(
				orderData.payment.payPalAccount.email
			);
		}
	};

	/**
	 * Asserts data provided on the order page
	 *
	 * @param data = {
	 *             refund_id: ,
	 *             refunded: ,
	 *             totalRefunded: ,
	 *             netPayment: ,
	 *             payPalFee: ,
	 *             payPalRefundFee: ,
	 *             payPalRefunded: ,
	 *             payPalPayout: ,
	 *             payPalNetTotal: ,
	 *             currency: 'EUR'
	 *             }
	 */
	assertRefundData = async ( data ) => {
		// Order status
		if ( data.orderStatus !== undefined ) {
			await expect( this.statusCombobox() ).toHaveText(
				data.orderStatus
			);
		}

		if ( data.refund_id !== undefined && data.refunded !== undefined ) {
			await expect( this.refundNumber() ).toContainText(
				`Refund #${ data.refund_id }`
			);
			await expect( this.refundAmount() ).toHaveText(
				'-' + ( await formatMoney( data.refunded, data.currency ) )
			);
		}

		if ( data.totalRefunded !== undefined ) {
			await expect( this.totalRefunded() ).toHaveText(
				'-' + ( await formatMoney( data.totalRefunded, data.currency ) )
			);
		}

		if ( data.netPayment !== undefined ) {
			await expect( this.totalNetPayment() ).toHaveText(
				await formatMoney( data.netPayment, data.currency )
			);
		}

		if ( data.payPalFee !== undefined ) {
			await expect( this.totalPayPalFee() ).toHaveText(
				'- ' + ( await formatMoney( data.payPalFee, data.currency ) )
			);
		}

		if ( data.payPalRefundFee !== undefined ) {
			await expect( this.totalPayPalRefundFee() ).toHaveText(
				'- ' +
					( await formatMoney( data.payPalRefundFee, data.currency ) )
			);
		}

		if ( data.payPalRefunded !== undefined ) {
			await expect( this.totalPayPalRefunded() ).toHaveText(
				'- ' +
					( await formatMoney( data.payPalRefunded, data.currency ) )
			);
		}

		if ( data.payPalPayout !== undefined ) {
			await expect( this.totalPayPalPayout() ).toHaveText(
				await formatMoney( data.payPalPayout, data.currency )
			);
		}

		if ( data.payPalNetTotal !== undefined ) {
			await expect( this.totalPayPalNetTotal() ).toHaveText(
				await formatMoney( data.payPalNetTotal, data.currency )
			);
		}
	};
}
