import {
	registerExpressPaymentMethod,
	registerPaymentMethod,
} from '@woocommerce/blocks-registry';
import { __ } from '@wordpress/i18n';
import {
	cartHasSubscriptionProducts,
	isPayPalSubscription,
} from './Helper/Subscription';
import { loadPayPalScript } from '../../../ppcp-button/resources/js/modules/Helper/PayPalScriptLoading';
import BlockCheckoutMessagesBootstrap from './Bootstrap/BlockCheckoutMessagesBootstrap';
import { PayPalComponent } from './Components/paypal';
import { BlockEditorPayPalComponent } from './Components/block-editor-paypal';
import { PaypalLabel } from './Components/paypal-label';
import { PayPalPlaceOrderContent } from './Components/paypal-place-order-content';
const namespace = 'ppcpBlocksPaypalExpressButtons';
const config = wc.wcSettings.getSetting( 'ppcp-gateway_data' );

window.ppcpFundingSource = config.fundingSource;

let paypalScriptPromise = null;

const features = [ 'products' ];
let blockEnabled = true;

if ( cartHasSubscriptionProducts( config.scriptData ) ) {
	// Don't show buttons on block cart page if user is not logged in and cart contains free trial product
	if (
		! config.scriptData.user.is_logged &&
		config.scriptData.context === 'cart-block' &&
		cartHasSubscriptionProducts( config.scriptData ) &&
		config.scriptData.is_free_trial_cart
	) {
		blockEnabled = false;
	}

	// Don't render if vaulting disabled and is in vault subscription mode
	if (
		! isPayPalSubscription( config.scriptData ) &&
		! config.scriptData.can_save_vault_token
	) {
		blockEnabled = false;
	}

	// Don't render buttons if in subscription mode and product not associated with a PayPal subscription
	if (
		isPayPalSubscription( config.scriptData ) &&
		! config.scriptData.subscription_product_allowed
	) {
		blockEnabled = false;
	}

	features.push( 'subscriptions' );
}

if ( blockEnabled ) {
	if ( config.placeOrderEnabled && ! config.scriptData.continuation ) {
		registerPaymentMethod( {
			name: config.id,
			label: <PaypalLabel config={ config } />,
			content: (
				<PayPalPlaceOrderContent
					description={ config.description }
					placeOrderButtonDescription={
						config.placeOrderButtonDescription
					}
				/>
			),
			edit: (
				<div
					dangerouslySetInnerHTML={ {
						__html: config.description,
					} }
				/>
			),
			placeOrderButtonLabel: config.placeOrderButtonText,
			ariaLabel: config.title,
			canMakePayment: ( cartData ) => {
				const total = cartData?.cartTotals?.total_price;
				return parseInt( total ) > 0;
			},
			supports: {
				features,
				showSavedCards: true,
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
