/**
 * External dependencies
 */
import {
	WooCommerceOrderEdit as WooCommerceOrderEditBase,
	countTotals,
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
	 * @param paymentMethod
	 * @param amount
	 */
	makeRefundVia = async ( paymentMethod: string, amount?: string ) => {
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
		await this.refundViaButton( paymentMethod ).click();
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
	 * @param pcpData.payPalTotal
	 * @param pcpData.payPalFee
	 * @param pcpData.payPalPayout
	 */
	assertOrderDetails = async (
		orderData: WooCommerce.ShopOrder,
		pcpData?: {
			transactionId?: string;
			payPalTotal?: string;
			payPalFee?: string;
			payPalPayout?: string;
		}
	) => {
		await super.assertOrderDetails( orderData );

		const total = await countTotals( orderData );
		const { payment, currency } = orderData;
		const fundingSource = payment.gateway.shortcut;

		if ( ! pcpData || Object.keys( pcpData ).length === 0 ) {
			return;
		}

		const { transactionId, payPalTotal, payPalFee, payPalPayout } = pcpData;

		// For example: Payment via PayPal
		await expect(
			this.paymentVia( payment?.gateway?.titleInModal ),
			`Assert payment via text is visible`
		).toBeVisible();

		if ( total.order > 0 ) {
			// Transaction ID
			if ( transactionId ) {
				await expect(
					this.transactionIdLink( transactionId ),
					`Assert transaction ID link with ID ${ transactionId } is visible`
				).toBeVisible();
			}

			// WooCommerce order total should equal to PayPal total
			await expect(
				this.orderTotal(),
				'Assert WooCommerce order total order is equal to PayPal payment total'
			).toHaveText(
				await formatMoney(
					Number( payPalTotal ),
					currency,
				)
			);

			// PayPal fees
			if ( payPalFee ) {
				await expect(
					this.totalPayPalFee(),
					'Assert total PayPal fee is expected'
				).toHaveText(
					'- ' +
						( await formatMoney(
							Number( payPalFee ),
							currency
						) )
				);
			}

			// PayPal payout
			if ( payPalPayout ) {
				await expect(
					this.totalPayPalPayout(),
					'Assert total PayPal payout is expected'
				).toHaveText(
					await formatMoney(
						Number( payPalPayout ),
						currency
					)
				);
			}
		}

		if ( fundingSource === 'oxxo' ) {
			await expect(
				this.seeOXXOVoucherButton(),
				'Assert OXXO voucher button is visible'
			).toBeVisible();
		}

		if ( [ 'paypal', 'paylater', 'venmo' ].includes( fundingSource ) ) {
			await this.assertPayPalEmailAddress(
				payment.payPalAccount.email
			);
		}

		// Intent Authorization assertions
		if ( payment.isAuthorized ) {
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
			await expect(
				this.totalPayPalFee(),
				'Assert total PayPal fee is expected'
			).toHaveText( '- ' + ( await formatMoney( payPalFee, currency ) ) );
		}

		if ( payPalRefundFee !== undefined ) {
			await expect(
				this.totalPayPalRefundFee(),
				'Assert total PayPal refund fee is expected'
			).toHaveText(
				'- ' + ( await formatMoney( payPalRefundFee, currency ) )
			);
		}

		if ( payPalRefunded !== undefined ) {
			await expect(
				this.totalPayPalRefunded(),
				'Assert total PayPal refunded is expected'
			).toHaveText(
				'- ' + ( await formatMoney( payPalRefunded, currency ) )
			);
		}

		if ( payPalPayout !== undefined ) {
			await expect(
				this.totalPayPalPayout(),
				'Assert total PayPal payout is expected'
			).toHaveText( await formatMoney( payPalPayout, currency ) );
		}

		if ( payPalNetTotal !== undefined ) {
			await expect(
				this.totalPayPalNetTotal(),
				'Assert total PayPal net total is expected'
			).toHaveText( await formatMoney( payPalNetTotal, currency ) );
		}
	};
}
