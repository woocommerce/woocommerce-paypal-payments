import Product from '../Entity/Product';
import BookingProduct from '../Entity/BookingProduct';
import onApprove from '../OnApproveHandler/onApproveForContinue';
import { payerData } from '../Helper/PayerData';
import { PaymentMethods } from '../Helper/CheckoutMethodState';
import CartHelper from '../Helper/CartHelper';
import FormHelper from '../Helper/FormHelper';

class SingleProductActionHandler {
	constructor( config, updateCart, formElement, errorHandler ) {
		this.config = config;
		this.updateCart = updateCart;
		this.formElement = formElement;
		this.errorHandler = errorHandler;
		this.cartHelper = null;
	}

	subscriptionsConfiguration( subscription_plan ) {
		return {
			createSubscription: ( data, actions ) => {
				return actions.subscription.create( {
					plan_id: subscription_plan,
				} );
			},
			onApprove: ( data, actions ) => {
				fetch( this.config.ajax.approve_subscription.endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					body: JSON.stringify( {
						nonce: this.config.ajax.approve_subscription.nonce,
						order_id: data.orderID,
						subscription_id: data.subscriptionID,
					} ),
				} )
					.then( ( res ) => {
						return res.json();
					} )
					.then( () => {
						const products = this.getSubscriptionProducts();

						fetch( this.config.ajax.change_cart.endpoint, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/json',
							},
							credentials: 'same-origin',
							body: JSON.stringify( {
								nonce: this.config.ajax.change_cart.nonce,
								products,
							} ),
						} )
							.then( ( result ) => {
								return result.json();
							} )
							.then( ( result ) => {
								if ( ! result.success ) {
									console.log( result );
									throw Error( result.data.message );
								}

								location.href = this.config.redirect;
							} );
					} );
			},
			onError: ( err ) => {
				console.error( err );
			},
		};
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

				if ( this.isBookingProduct() && error.message ) {
					this.errorHandler.clear();
					this.errorHandler.message( error.message );
					return;
				}
				this.errorHandler.genericError();
			},
			onCancel: () => {
				// Could be used for every product type,
				// but only clean the cart for Booking products for now.
				if ( this.isBookingProduct() ) {
					this.cleanCart();
				} else {
					this.refreshMiniCart();
				}
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

		return ( data, actions, options = {} ) => {
			this.errorHandler.clear();

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
							throw Error( data.data.message );
						}
						return data.data.id;
					} );
			};

			return this.updateCart.update(
				onResolve,
				this.getProducts(),
				options.updateCartOptions || {}
			);
		};
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
