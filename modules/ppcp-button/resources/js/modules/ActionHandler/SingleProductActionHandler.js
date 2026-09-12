import Product from '../Entity/Product';
import BookingProduct from '../Entity/BookingProduct';
import onApprove from '../OnApproveHandler/onApproveForContinue';
import { payerData } from '../Helper/PayerData';
import { PaymentMethods } from '../Helper/CheckoutMethodState';
import CartHelper from '../Helper/CartHelper';
import FormHelper from '../Helper/FormHelper';
import ResumeFlowHelper from '../Helper/ResumeFlowHelper';

class SingleProductActionHandler {
	constructor( config, cartUpdater, formElement, errorHandler ) {
		this.config = config;
		this.cartUpdater = cartUpdater;
		this.formElement = formElement;
		this.errorHandler = errorHandler;
		this.cartHelper = null;
	}

	subscriptionsConfiguration( subscription_plan ) {
		return {
			createSubscription: ( data, actions ) => {
				return actions.subscription.create( {
					plan_id: subscription_plan,
					custom_id: this.config.subscription_custom_id,
				} );
			},
			onApprove: async ( data ) => {
				// The cart has to hold the subscription product before the
				// approval is sent: the approve endpoint builds the WC order
				// from WC()->cart, and the change-cart endpoint empties the
				// cart before adding the product to it.
				const cartResult = await this.changeCartToSubscription();
				if ( ! cartResult.success ) {
					console.log( cartResult );
					throw Error( cartResult.data.message );
				}

				const res = await fetch(
					this.config.ajax.approve_subscription.endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						body: JSON.stringify( {
							nonce: this.config.ajax.approve_subscription.nonce,
							order_id: data.orderID,
							subscription_id: data.subscriptionID,
							should_create_wc_order:
								! this.config.vaultingEnabled ||
								data.paymentSource !== 'venmo',
						} ),
					}
				);
				location.href = this.approvalRedirectUrl( await res.json() );
			},
			onError: ( err ) => {
				console.error( err );

				ResumeFlowHelper.reloadButtonsIfRequired(
					this.config.button.wrapper
				);
			},
		};
	}

	/**
	 * Where to send the shopper once the subscription has been approved.
	 *
	 * The approve endpoint returns an order received URL only when it created
	 * and paid the order itself, which it does when Pay Now is enabled.
	 * Otherwise the shopper confirms the payment on the checkout page.
	 *
	 * @param {Object} response - The approve endpoint response.
	 * @return {string} The URL to navigate to.
	 */
	approvalRedirectUrl( response ) {
		return response.data?.order_received_url || this.config.redirect;
	}

	/**
	 * Replaces the cart contents with the subscription product being viewed.
	 *
	 * @return {Promise<Object>} The change-cart endpoint response.
	 */
	async changeCartToSubscription() {
		const res = await fetch( this.config.ajax.change_cart.endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			credentials: 'same-origin',
			body: JSON.stringify( {
				nonce: this.config.ajax.change_cart.nonce,
				products: this.getSubscriptionProducts(),
			} ),
		} );

		return res.json();
	}

	getSubscriptionProducts() {
		const id = document.querySelector( '[name="add-to-cart"]' ).value;
		return [ new Product( id, 1, this.variations(), this.extraFields() ) ];
	}

	configuration() {
		return {
			createOrder: this.createOrder(),
			onApprove: onApprove( this, this.errorHandler ),
			onError: ( error ) => {
				this.refreshMiniCart();

				if ( ! error || error.type !== 'create-order-error' ) {
					this.errorHandler.genericError();
				}

				ResumeFlowHelper.reloadButtonsIfRequired(
					this.config.button.wrapper
				);
			},
			onCancel: () => {
				// Could be used for every product type,
				// but only clean the cart for Booking products for now.
				if ( this.isBookingProduct() ) {
					this.cleanCart();
				} else {
					this.refreshMiniCart();
				}

				ResumeFlowHelper.reloadButtonsIfRequired(
					this.config.button.wrapper
				);
			},
		};
	}

	getProducts() {
		if ( this.isBookingProduct() ) {
			const id = document.querySelector( '[name="add-to-cart"]' ).value;
			return [
				new BookingProduct(
					id,
					1,
					FormHelper.getPrefixedFields(
						this.formElement,
						'wc_bookings_field'
					),
					this.extraFields()
				),
			];
		} else if ( this.isGroupedProduct() ) {
			const products = [];
			this.formElement
				.querySelectorAll( 'input[type="number"]' )
				.forEach( ( element ) => {
					if ( ! element.value ) {
						return;
					}
					const elementName = element
						.getAttribute( 'name' )
						.match( /quantity\[([\d]*)\]/ );
					if ( elementName.length !== 2 ) {
						return;
					}
					const id = parseInt( elementName[ 1 ] );
					const quantity = parseInt( element.value );
					products.push(
						new Product( id, quantity, null, this.extraFields() )
					);
				} );
			return products;
		}
		const id = document.querySelector( '[name="add-to-cart"]' ).value;
		const qty = document.querySelector( '[name="quantity"]' ).value;
		const variations = this.variations();
		return [ new Product( id, qty, variations, this.extraFields() ) ];
	}

	extraFields() {
		return FormHelper.getFilteredFields(
			this.formElement,
			[ 'add-to-cart', 'quantity', 'product_id', 'variation_id' ],
			[ 'attribute_', 'wc_bookings_field' ]
		);
	}

	createOrder() {
		this.cartHelper = null;
		const errorHandler = this.errorHandler;

		return ( data, actions, options = {} ) => {
			errorHandler.clear();

			const onResolve = ( purchase_units ) => {
				this.cartHelper = new CartHelper().addFromPurchaseUnits(
					purchase_units
				);

				const payer = payerData();
				const bnCode =
					typeof this.config.bn_codes[ this.config.context ] !==
					'undefined'
						? this.config.bn_codes[ this.config.context ]
						: '';
				return fetch( this.config.ajax.create_order.endpoint, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
					},
					credentials: 'same-origin',
					body: JSON.stringify( {
						nonce: this.config.ajax.create_order.nonce,
						purchase_units,
						payer,
						bn_code: bnCode,
						payment_method: PaymentMethods.PAYPAL,
						funding_source: window.ppcpFundingSource,
						context: this.config.context,
					} ),
				} )
					.then( function ( res ) {
						return res.json();
					} )
					.then( function ( data ) {
						if ( ! data.success ) {
							console.error( data );
							errorHandler.clear();
							errorHandler.message( data.data.message );
							throw { type: 'create-order-error' };
						}
						return data.data.id;
					} );
			};

			return this.cartUpdater.update(
				onResolve,
				this.getProducts(),
				options.updateCartOptions || {}
			);
		};
	}

	updateCart( updateCartOptions ) {
		return this.cartUpdater.update(
			( data ) => data,
			this.getProducts(),
			updateCartOptions
		);
	}

	variations() {
		if ( ! this.hasVariations() ) {
			return null;
		}
		return [
			...this.formElement.querySelectorAll( "[name^='attribute_']" ),
		].map( ( element ) => {
			return {
				value: element.value,
				name: element.name,
			};
		} );
	}

	hasVariations() {
		return this.formElement.classList.contains( 'variations_form' );
	}

	isGroupedProduct() {
		return this.formElement.classList.contains( 'grouped_form' );
	}

	isBookingProduct() {
		// detection for "woocommerce-bookings" plugin
		return !! this.formElement.querySelector( '.wc-booking-product-id' );
	}

	cleanCart() {
		this.cartHelper
			.removeFromCart()
			.then( () => {
				this.refreshMiniCart();
			} )
			.catch( ( error ) => {
				this.refreshMiniCart();
			} );
	}

	refreshMiniCart() {
		jQuery( document.body ).trigger( 'wc_fragment_refresh' );
	}
}
export default SingleProductActionHandler;
