/**
 * Reducer: Defines store structure and state updates for this module.
 *
 * Manages both transient (temporary) and persistent (saved) state.
 * The initial state must define all properties, as dynamic additions are not supported.
 *
 * @file
 */

import { createReducer, createSetters } from '../utils';
import ACTION_TYPES from './action-types';
import { __ } from '@wordpress/i18n';

const defaultTransient = Object.freeze( {} );

const defaultPersistent = Object.freeze( {
	paymentMethods: Object.freeze( {
		paypalCheckout: [
			{
				id: 'paypal',
				title: __( 'PayPal', 'woocommerce-paypal-payments' ),
				description: __(
					'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximize conversion.',
					'woocommerce-paypal-payments'
				),
				icon: 'payment-method-paypal',
				isSelected: () => {
					return false;
				},
				onSelect: ( checked ) => {
					console.log( `update ${ checked } in data store` );
				},
			},
			{
				id: 'venmo',
				title: __( 'Venmo', 'woocommerce-paypal-payments' ),
				description: __(
					'Offer Venmo at checkout to millions of active users.',
					'woocommerce-paypal-payments'
				),
				icon: 'payment-method-venmo',
			},
			{
				id: 'paypal_credit',
				title: __( 'PayPal Credit', 'woocommerce-paypal-payments' ),
				description: __(
					'Get paid in full at checkout while giving your customers the option to pay interest free if paid within 6 months on orders over $99.',
					'woocommerce-paypal-payments'
				),
				icon: 'payment-method-paypal',
			},
			{
				id: 'credit_and_debit_card_payments',
				title: __(
					'Credit and debit card payments',
					'woocommerce-paypal-payments'
				),
				description: __(
					"Accept all major credit and debit cards - even if your customer doesn't have a PayPal account.",
					'woocommerce-paypal-payments'
				),
				icon: 'payment-method-cards',
			},
		],
	} ),
} );

const [ setTransient, setPersistent ] = createSetters(
	defaultTransient,
	defaultPersistent
);

const paymentReducer = createReducer( defaultTransient, defaultPersistent, {
	[ ACTION_TYPES.SET_TRANSIENT ]: ( state, action ) =>
		setTransient( state, action ),

	[ ACTION_TYPES.SET_PERSISTENT ]: ( state, action ) =>
		setPersistent( state, action ),
} );

export default paymentReducer;
