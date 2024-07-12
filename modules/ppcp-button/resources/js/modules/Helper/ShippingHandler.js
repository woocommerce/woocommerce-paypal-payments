import { paypalAddressToWc } from '../../../../../ppcp-blocks/resources/js/Helper/Address.js';
import { convertKeysToSnakeCase } from '../../../../../ppcp-blocks/resources/js/Helper/Helper.js';

/**
 * Handles the shipping option change in PayPal.
 *
 * @param  data
 * @param  actions
 * @param  config
 * @return {Promise<void>}
 */
export const handleShippingOptionsChange = async ( data, actions, config ) => {
	try {
		const shippingOptionId = data.selectedShippingOption?.id;

		if ( shippingOptionId ) {
			await fetch(
				config.ajax.update_customer_shipping.shipping_options.endpoint,
				{
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WC-Store-API-Nonce':
							config.ajax.update_customer_shipping.wp_rest_nonce,
					},
					body: JSON.stringify( {
						rate_id: shippingOptionId,
					} ),
				}
			)
				.then( ( response ) => {
					return response.json();
				} )
				.then( ( cardData ) => {
					const shippingMethods =
						document.querySelectorAll( '.shipping_method' );

					shippingMethods.forEach( function ( method ) {
						if ( method.value === shippingOptionId ) {
							method.checked = true;
						}
					} );
				} );
		}

		if ( ! config.data_client_id.has_subscriptions ) {
			const res = await fetch( config.ajax.update_shipping.endpoint, {
				method: 'POST',
				credentials: 'same-origin',
				body: JSON.stringify( {
					nonce: config.ajax.update_shipping.nonce,
					order_id: data.orderID,
				} ),
			} );

			const json = await res.json();

			if ( ! json.success ) {
				throw new Error( json.data.message );
			}
		}
	} catch ( e ) {
		console.error( e );

		actions.reject();
	}
};

/**
 * Handles the shipping address change in PayPal.
 *
 * @param  data
 * @param  actions
 * @param  config
 * @return {Promise<void>}
 */
export const handleShippingAddressChange = async ( data, actions, config ) => {
	try {
		const address = paypalAddressToWc(
			convertKeysToSnakeCase( data.shippingAddress )
		);

		// Retrieve current cart contents
		await fetch(
			config.ajax.update_customer_shipping.shipping_address.cart_endpoint
		)
			.then( ( response ) => {
				return response.json();
			} )
			.then( ( cartData ) => {
				// Update shipping address in the cart data
				cartData.shipping_address.address_1 = address.address_1;
				cartData.shipping_address.address_2 = address.address_2;
				cartData.shipping_address.city = address.city;
				cartData.shipping_address.state = address.state;
				cartData.shipping_address.postcode = address.postcode;
				cartData.shipping_address.country = address.country;

				// Send update request
				return fetch(
					config.ajax.update_customer_shipping.shipping_address
						.update_customer_endpoint,
					{
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WC-Store-API-Nonce':
								config.ajax.update_customer_shipping
									.wp_rest_nonce,
						},
						body: JSON.stringify( {
							shipping_address: cartData.shipping_address,
						} ),
					}
				)
					.then( function ( res ) {
						return res.json();
					} )
					.then( function ( customerData ) {
						jQuery( '.cart_totals .shop_table' ).load(
							location.href +
								' ' +
								'.cart_totals .shop_table' +
								'>*',
							''
						);
					} );
			} );

		const res = await fetch( config.ajax.update_shipping.endpoint, {
			method: 'POST',
			credentials: 'same-origin',
			body: JSON.stringify( {
				nonce: config.ajax.update_shipping.nonce,
				order_id: data.orderID,
			} ),
		} );

		const json = await res.json();

		if ( ! json.success ) {
			throw new Error( json.data.message );
		}
	} catch ( e ) {
		console.error( e );

		actions.reject();
	}
};
