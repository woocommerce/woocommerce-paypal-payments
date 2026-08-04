import {
	registerExpressPaymentMethod,
	registerPaymentMethod,
} from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import {
	cartHasSubscriptionProducts,
	paypalPaymentMethodAllowed,
	paypalSubscriptionButtonAllowed,
} from './Helper/Subscription';
import { loadPayPalScript } from '../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import BlockCheckoutMessagesBootstrap from './Bootstrap/BlockCheckoutMessagesBootstrap';
import { PayPalComponent } from './Components/paypal';
import { BlockEditorPayPalComponent } from './Components/block-editor-paypal';
import { PaypalLabel } from './Components/paypal-label';
import { PayPalPlaceOrderContent } from './Components/paypal-place-order-content';
import { PayPalSavedToken } from './Components/paypal-saved-token';
const namespace = 'ppcpBlocksPaypalExpressButtons';
const config = wc.wcSettings.getSetting( 'ppcp-gateway_data' );

window.ppcpFundingSource = config.fundingSource;

let paypalScriptPromise = null;

const features = [ 'products' ];
let blockEnabled = true;

if ( cartHasSubscriptionProducts( config.scriptData ) ) {
	// Show the button only for subscription carts PayPal can process
	// (shared rule used by the classic cart and mini-cart as well).
	blockEnabled = paypalSubscriptionButtonAllowed( config.scriptData );

	features.push( 'subscriptions' );
}

if ( blockEnabled ) {
	if ( config.placeOrderEnabled && ! config.scriptData.continuation ) {
		registerPaymentMethod( {
			name: config.id,
			label: <PaypalLabel config={ config } />,
			content: (
				<PayPalPlaceOrderContent
					config={ config }
					description={ config.description }
					placeOrderButtonDescription={
						config.placeOrderButtonDescription
					}
				/>
			),
			savedTokenComponent: <PayPalSavedToken config={ config } />,
			edit: (
				<div
					dangerouslySetInnerHTML={ {
						__html: config.description,
					} }
				/>
			),
			placeOrderButtonLabel: config.placeOrderButtonText,
			ariaLabel: config.title,
			canMakePayment: ( cartData ) =>
				paypalPaymentMethodAllowed( config.scriptData, cartData ),
			supports: {
				features,
				showSavedCards: true,
			},
		} );

		const { registerCheckoutFilters } = window.wc.blocksCheckout;
		registerCheckoutFilters( config.id, {
			placeOrderButtonLabel: ( value ) => {
				const store = window.wp?.data?.select( 'wc/store/payment' );
				if ( store?.getActivePaymentMethod() === config.id ) {
					return config.placeOrderButtonText;
				}
				return value;
			},
		} );
	}

	if ( config.scriptData.continuation ) {
		registerPaymentMethod( {
			name: config.id,
			label: <div dangerouslySetInnerHTML={ { __html: config.title } } />,
			content: <PayPalComponent config={ config } isEditing={ false } />,
			edit: (
				<BlockEditorPayPalComponent
					config={ config }
					fundingSource={ 'paypal' }
				/>
			),
			ariaLabel: config.title,
			canMakePayment: () => {
				return true;
			},
			supports: {
				features: [ ...features, 'ppcp_continuation' ],
			},
		} );
	} else if ( config.smartButtonsEnabled ) {
		const fundingSources = config.scriptData.is_free_trial_cart
			? [ 'paypal' ]
			: [ 'paypal', ...config.enabledFundingSources ];

		for ( const fundingSource of fundingSources ) {
			registerExpressPaymentMethod( {
				name: `${ config.id }-${ fundingSource }`,
				title: 'PayPal',
				description: __(
					'Eligible users will see the PayPal button.',
					'woocommerce-paypal-payments'
				),
				gatewayId: 'ppcp-gateway',
				paymentMethodId: config.id,
				label: (
					<div dangerouslySetInnerHTML={ { __html: config.title } } />
				),
				content: (
					<PayPalComponent
						config={ config }
						isEditing={ false }
						fundingSource={ fundingSource }
					/>
				),
				edit: (
					<BlockEditorPayPalComponent
						config={ config }
						fundingSource={ fundingSource }
					/>
				),
				ariaLabel: config.title,
				canMakePayment: async () => {
					if ( ! paypalScriptPromise ) {
						paypalScriptPromise = loadPayPalScript(
							namespace,
							config.scriptData
						);
						paypalScriptPromise.then( () => {
							const messagesBootstrap =
								new BlockCheckoutMessagesBootstrap(
									config.scriptData
								);
							messagesBootstrap.init();
						} );
					}
					await paypalScriptPromise;

					return ppcpBlocksPaypalExpressButtons
						.Buttons( { fundingSource } )
						.isEligible();
				},
				supports: {
					features,
					style: [ 'height', 'borderRadius' ],
					showSavedCards: true,
				},
			} );
		}
	}
}
