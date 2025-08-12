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
const namespace = 'ppcpBlocksPaypalExpressButtons';
const config = wc.wcSettings.getSetting( 'ppcp-gateway_data' );

window.ppcpFundingSource = config.fundingSource;

let paypalScriptPromise = null;

const features = [ 'products' ];
let blockEnabled = true;

if ( cartHasSubscriptionProducts( config.scriptData ) ) {
	// Don't show buttons on block cart page if using vault v2 and user is not logged in
	if (
		! config.scriptData.user.is_logged &&
		config.scriptData.context === 'cart-block' &&
		! isPayPalSubscription( config.scriptData ) && // using vaulting
		! config.scriptData?.save_payment_methods?.id_token // not vault v3
	) {
		blockEnabled = false;
	}

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

	// Don't show buttons if cart contains free trial product and the store is not eligible for saving payment methods.
	if (
		! config.scriptData.vault_v3_enabled &&
		config.scriptData.is_free_trial_cart
	) {
		blockEnabled = false;
	}

	features.push( 'subscriptions' );
}

if ( blockEnabled ) {
	if ( config.placeOrderEnabled && ! config.scriptData.continuation ) {
		let descriptionElement = (
			<div
				dangerouslySetInnerHTML={ { __html: config.description } }
			></div>
		);
		if ( config.placeOrderButtonDescription ) {
			descriptionElement = (
				<div>
					<p
						dangerouslySetInnerHTML={ {
							__html: config.description,
						} }
					></p>
					<p
						style={ { textAlign: 'center' } }
						className={ 'ppcp-place-order-description' }
						dangerouslySetInnerHTML={ {
							__html: config.placeOrderButtonDescription,
						} }
					></p>
				</div>
			);
		}

		registerPaymentMethod( {
			name: config.id,
			label: <PaypalLabel config={ config } />,
			content: descriptionElement,
			edit: descriptionElement,
			placeOrderButtonLabel: config.placeOrderButtonText,
			ariaLabel: config.title,
			canMakePayment: () => {
				return true;
			},
			supports: {
				features,
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
				},
			} );
		}
	}
}
