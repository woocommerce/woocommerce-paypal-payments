/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import {
	storeConfigClassic,
	pcpConfigDefault,
	orders,
	products,
	coupons,
	acdc,
	payPal,
} from '../../resources';

const customer = storeConfigClassic.customer;
const popupTitle = 'Pay with PayPal';

test.describe( 'Frontend UI', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( storeConfigClassic );
		await utils.configurePcp( pcpConfigDefault );
	} );

	test( 'PCP-1201 | Frontend UI - Classic checkout - PayPal - Cancel payment in popup @Critical', async ( {
		utils,
		classicCheckout,
	} ) => {
		const tested = {
			...orders.default,
			payment: payPal,
		};

		// Preconditions
		await utils.fillVisitorsCart( tested.products );

		// Test
		await classicCheckout.visit();
		await expect( classicCheckout.ppui.payPalButton() ).toBeVisible();

		await classicCheckout.fillBillingDetails( tested.customer.billing );
		await classicCheckout.selectShippingMethod(
			tested.shipping.settings.title
		);
		await expect( classicCheckout.ppui.payPalGateway() ).toBeVisible();
		await classicCheckout.ppui.payPalGateway().click();
		await expect( classicCheckout.ppui.payPalButton() ).toBeVisible();

		let payPalPopup = await classicCheckout.ppui.openPayPalPupup();
		await expect( payPalPopup.popup ).toHaveTitle(
			popupTitle
		);
		await payPalPopup.popup.close();
		await expect( payPalPopup.popup.isClosed() ).toBeTruthy();
		await classicCheckout.assertUrl();

		payPalPopup = await classicCheckout.ppui.openPayPalPupup();
		await expect( payPalPopup.popup ).toHaveTitle(
			popupTitle
		);
	} );

	test( 'PCP-2047 | Frontend UI - Classic cart - Pay Later - Disabled Pay Later button is NOT displayed @Critical', async ( {
		utils,
		payLater,
		classicCart,
		classicCheckout,
		checkout,
		cart,
		product,
	} ) => {
		await payLater.visit();
		await payLater.enableGatewayCheckbox().check();
		await payLater.removeItemsFromSelectBox( 'Pay Later Button Locations', [
			'Cart',
			'Classic Cart',
			'Express Checkout',
			'Classic Checkout',
			'Single Product',
		] );
		await payLater.saveChanges();
		await utils.fillVisitorsCart( [ products.simple10 ] );

		await product.visit( products.simple10.slug );
		await product.ppui.assertPayLaterButtonVisibility( false );

		await cart.visit();
		await cart.ppui.assertPayLaterButtonVisibility( false );

		await classicCart.visit();
		await classicCart.ppui.assertPayLaterButtonVisibility( false );

		await checkout.visit();
		await checkout.ppui.assertPayLaterButtonVisibility( false );

		await classicCheckout.visit();
		await classicCheckout.ppui.assertPayLaterButtonVisibility( false );
	} );

	const fullPriceCoupons = [
		{
			title: 'PCP-1175 | Frontend UI - Classic cart - PayPal buttons are not displayed for 0 total with fixed coupon @Critical',
			product: products.simple10,
			coupon: {
				code: 'fixed1175',
				discount_type: 'fixed_cart',
				amount: products.simple10.regular_price,
			},
		},
		{
			title: 'PCP-1178 | Frontend UI - Classic cart - PayPal buttons are not displayed with fixed coupon higher than total amount @Critical',
			product: products.simple10,
			coupon: {
				code: 'fixed1178',
				discount_type: 'fixed_cart',
				amount: (
					parseFloat( products.simple10.regular_price ) + 1
				).toFixed( 2 ),
			},
		},
		{
			title: 'PCP-1182 | Frontend UI - Classic cart - PayPal buttons are not displayed for 0 total with percentage coupon @Critical',
			product: products.simple10,
			coupon: coupons.percent100,
		},
	];

	for ( const tested of fullPriceCoupons ) {
		test(
			tested.title,
			async ( { utils, wooCommerceUtils, classicCart } ) => {
				// Preconditions
				await wooCommerceUtils.createCoupon( tested.coupon );
				await utils.fillVisitorsCart( [ tested.product ] );

				await classicCart.visit();
				await classicCart.applyCoupon( tested.coupon.code );
				await classicCart.ppui.assertPayPalButtonVisibility( false );
			}
		);
	}

	const couponsWithExclusions = [
		{
			title: 'PCP-1177 | Frontend UI - Classic cart - PayPal buttons are displayed if product is excluded from fixed coupon @Critical',
			product: products.simple10,
			coupon: {
				code: 'fixed restricted 1177',
				discount_type: 'fixed_cart',
				amount: '8.00',
				excluded_product_ids: [ undefined ],
			},
			couponMessage: `Sorry, coupon "fixed restricted 1177" is not applicable to the products: ${ products.simple10.name }`,
		},
		{
			title: 'PCP-1184 | Frontend UI - Classic cart - PayPal buttons are displayed if product is excluded from percentage coupon @Critical',
			product: products.simple10,
			coupon: {
				code: 'percent restricted 1184',
				discount_type: 'percent',
				amount: '8.00',
				excluded_product_ids: [ undefined ],
			},
			couponMessage:
				'Sorry, coupon "percent restricted 1184" is not applicable to selected products.',
		},
	];

	for ( const tested of couponsWithExclusions ) {
		test(
			tested.title,
			async ( {
				wooCommerceUtils,
				classicCart,
				wooCommerceApi,
				utils,
			} ) => {
				// Preconditions
				const product = await wooCommerceApi.getProductBySlug(
					tested.product.slug
				);
				tested.coupon.excluded_product_ids[ 0 ] = product.id;

				await wooCommerceUtils.createCoupon( tested.coupon );
				await utils.fillVisitorsCart( [ tested.product ] );

				await classicCart.visit();
				await classicCart.applyCoupon(
					tested.coupon.code,
					tested.couponMessage
				);
				await classicCart.ppui.assertPayPalButtonVisibility( true );
			}
		);
	}

	test( 'PCP-1301 | Frontend UI - Pay by link - PayPal - Customer - Order with 0 total amount @Critical', async ( {
		wooCommerceUtils,
		classicPayForOrder,
		customerAccount,
	} ) => {
		const tested = {
			...orders.fixedCoupon0Total,
			payment: payPal,
			customer,
		};
		const order = await wooCommerceUtils.createApiOrder( tested );

		// Test
		await customerAccount.loginCustomer(
			tested.customer.email,
			tested.customer.password
		);

		await classicPayForOrder.visit( order.id, order.order_key );

		await expect(
			classicPayForOrder.page.getByText(
				'This order’s status is “Pending payment”—it cannot be paid for. Please contact us if you need assistance.'
			)
		).toBeVisible();
		await expect(
			classicPayForOrder.ppui.payPalButton()
		).not.toBeVisible();
	} );

	test( 'PCP-1302 | Frontend UI - Pay by link - PayPal - Guest - Order with 0 total amount @Critical', async ( {
		wooCommerceUtils,
		classicPayForOrder,
	} ) => {
		const tested = {
			...orders.fixedCoupon0Total,
			payment: payPal,
		};
		const order = await wooCommerceUtils.createApiOrder( tested );

		// Test
		await classicPayForOrder.visit( order.id, order.order_key );
		await expect(
			classicPayForOrder.page.getByText(
				'cannot be paid for. Please contact us if you need assistance.'
			)
		).toBeVisible();
		await expect(
			classicPayForOrder.ppui.payPalButton()
		).not.toBeVisible();
	} );

	test( 'PCP-2062 | Frontend UI - Pay by link - PayPal - Cancel payment in popup @Critical', async ( {
		wooCommerceUtils,
		wooCommerceApi,
		classicPayForOrder,
	} ) => {
		const tested = {
			...orders.default,
			payment: payPal,
		};

		// Preconditions
		let order = await wooCommerceUtils.createApiOrder( tested );

		// Test
		await classicPayForOrder.visit( order.id, order.order_key );
		await expect( classicPayForOrder.ppui.payPalButton() ).toBeVisible();

		let payPalPopup = await classicPayForOrder.ppui.openPayPalPupup();
		await expect( payPalPopup.popup ).toHaveTitle(
			popupTitle
		);
		await payPalPopup.popup.close();
		await expect( payPalPopup.popup.isClosed() ).toBeTruthy();
		await classicPayForOrder.assertUrl( order.id, order.order_key );

		payPalPopup = await classicPayForOrder.ppui.openPayPalPupup();
		await expect( payPalPopup.popup ).toHaveTitle(
			popupTitle
		);

		order = await wooCommerceApi.getOrder( order.id );
		await expect( order.status ).toEqual( 'pending' );
		await expect( order.transaction_id ).toHaveLength( 0 );
	} );

	test.describe( 'Terms and conditions', () => {
		test.beforeAll( async ( { customizer } ) => {
			await customizer.setWooCommerceTermsAndConditions( 'Shop' );
		} );

		test( 'PCP-2063 | Frontend UI - Pay by link - PayPal - Unchecked Terms and conditions @Critical', async ( {
			wooCommerceUtils,
			wooCommerceApi,
			classicPayForOrder,
		} ) => {
			const tested = {
				...orders.default,
				payment: payPal,
			};
			let order = await wooCommerceUtils.createApiOrder( tested );

			await classicPayForOrder.visit( order.id, order.order_key );
			await expect(
				classicPayForOrder.ppui.payPalButton()
			).toBeVisible();

			const payPalPopup = await classicPayForOrder.ppui.openPayPalPupup();
			await payPalPopup.popup.waitForEvent( 'close' );

			await expect( payPalPopup.popup.isClosed() ).toBeTruthy();
			await classicPayForOrder.assertUrl( order.id, order.order_key );
			await expect(
				classicPayForOrder.page.getByText(
					'Please read and accept the terms and conditions to proceed with your order.'
				)
			).toBeVisible();

			order = await wooCommerceApi.getOrder( order.id );
			await expect( order.status ).toEqual( 'pending' );
			await expect( order.transaction_id ).toHaveLength( 0 );
		} );

		test( 'PCP-2064 | Frontend UI - Pay by link - ACDC - Unchecked Terms and conditions @Critical', async ( {
			wooCommerceUtils,
			utils,
			wooCommerceApi,
			classicPayForOrder,
		} ) => {
			const tested = {
				...orders.default,
				payment: acdc,
			};
			let order = await wooCommerceUtils.createApiOrder( tested );

			await utils.advancedCardProcessing.setup( { enableGateway: true } );

			await classicPayForOrder.visit( order.id, order.order_key );
			await classicPayForOrder.ppui.acdcGateway().click();
			await classicPayForOrder.ppui
				.cardNumberInput()
				.fill( tested.payment.card.card_number );
			await classicPayForOrder.ppui.cardExpirationInput().click();
			await classicPayForOrder.ppui.page.keyboard.type(
				tested.payment.card.expiration_date
			);
			await classicPayForOrder.ppui
				.cardCVVInput()
				.fill( tested.payment.card.card_cvv );
			await classicPayForOrder.payForOrderButton().click();

			await classicPayForOrder.assertUrl( order.id, order.order_key );
			await expect(
				classicPayForOrder.page.getByText(
					'Please read and accept the terms and conditions to proceed with your order.'
				)
			).toBeVisible();

			order = await wooCommerceApi.getOrder( order.id );
			await expect( order.status ).toEqual( 'pending' );
			await expect( order.transaction_id ).toHaveLength( 0 );
		} );

		test.afterAll( async ( { customizer } ) => {
			await customizer.setWooCommerceTermsAndConditions( 'No page set' );
		} );
	} );
} );
