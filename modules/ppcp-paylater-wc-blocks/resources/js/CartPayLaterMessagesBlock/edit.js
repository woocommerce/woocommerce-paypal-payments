import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, Spinner } from '@wordpress/components';
import { PayPalScriptProvider, PayPalMessages } from '@paypal/react-paypal-js';
import { useScriptParams } from '../../../../ppcp-paylater-block/resources/js/hooks/script-params';

export default function Edit( { attributes, clientId, setAttributes } ) {
	const { ppcpId } = attributes;

	const [ loaded, setLoaded ] = useState( false );

	let amount;
	const postContent = String(
		wp.data.select( 'core/editor' )?.getEditedPostContent()
	);
	if (
		postContent.includes( 'woocommerce/checkout' ) ||
		postContent.includes( 'woocommerce/cart' )
	) {
		amount = 50.0;
	}

	const cartConfig = PcpCartPayLaterBlock.config.cart;

	// Dynamically setting previewStyle based on the layout attribute
	let previewStyle = {};
	if ( cartConfig.layout === 'flex' ) {
		previewStyle = {
			layout: cartConfig.layout,
			color: cartConfig.color,
			ratio: cartConfig.ratio,
		};
	} else {
		previewStyle = {
			layout: cartConfig.layout,
			logo: {
				position: cartConfig[ 'logo-position' ],
				type: cartConfig[ 'logo-type' ],
			},
			text: {
				color: cartConfig[ 'text-color' ],
				size: cartConfig[ 'text-size' ],
			},
		};
	}

	let classes = [ 'ppcp-paylater-block-preview', 'ppcp-overlay-parent' ];
	if (
		PcpCartPayLaterBlock.vaultingEnabled ||
		! PcpCartPayLaterBlock.placementEnabled
	) {
		classes = [
			...classes,
			'ppcp-paylater-unavailable',
			'block-editor-warning',
		];
	}
	const props = useBlockProps( { className: classes.join( ' ' ) } );

	useEffect( () => {
		if ( ! ppcpId ) {
			setAttributes( { ppcpId: `ppcp-${ clientId }` } );
		}
	}, [ ppcpId, clientId ] );

	if ( PcpCartPayLaterBlock.vaultingEnabled ) {
		return (
			<div { ...props }>
				<div className="block-editor-warning__contents">
					<p className="block-editor-warning__message">
						{ __(
							'Cart - Pay Later Messaging cannot be used while PayPal Vaulting is active. Disable PayPal Vaulting in the PayPal Payment settings to reactivate this block',
							'woocommerce-paypal-payments'
						) }
					</p>
					<div className="block-editor-warning__actions">
						<span className="block-editor-warning__action">
							<a href={ PcpCartPayLaterBlock.settingsUrl }>
								<button
									type="button"
									className="components-button is-primary"
								>
									{ __(
										'PayPal Payments Settings',
										'woocommerce-paypal-payments'
									) }
								</button>
							</a>
						</span>
					</div>
				</div>
			</div>
		);
	}

	if ( ! PcpCartPayLaterBlock.placementEnabled ) {
		return (
			<div { ...props }>
				<div className="block-editor-warning__contents">
					<p className="block-editor-warning__message">
						{ __(
							'Cart - Pay Later Messaging cannot be used while the “Cart” messaging placement is disabled. Enable the placement in the PayPal Payments Pay Later settings to reactivate this block.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<div className="block-editor-warning__actions">
						<span className="block-editor-warning__action">
							<a
								href={
									PcpCartPayLaterBlock.payLaterSettingsUrl
								}
							>
								<button
									type="button"
									className="components-button is-primary"
								>
									{ __(
										'PayPal Payments Settings',
										'woocommerce-paypal-payments'
									) }
								</button>
							</a>
						</span>
					</div>
				</div>
			</div>
		);
	}

	const scriptParams = useScriptParams(
		PcpCartPayLaterBlock.ajax.cart_script_params
	);
	if ( scriptParams === null ) {
		return (
			<div { ...props }>
				<Spinner />
			</div>
		);
	}

	const urlParams = {
		...scriptParams.url_params,
		components: 'messages',
		dataNamespace: 'ppcp-block-editor-cart-paylater-message',
	};

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Customize your messaging',
						'woocommerce-paypal-payments'
					) }
				>
					<p>
						{ __(
							'Choose the layout and color of your messaging in the PayPal Payments Pay Later settings for the “Cart” messaging placement.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<a href={ PcpCartPayLaterBlock.payLaterSettingsUrl }>
						<button
							type="button"
							className="components-button is-primary"
						>
							{ __(
								'PayPal Payments Settings',
								'woocommerce-paypal-payments'
							) }
						</button>
					</a>
				</PanelBody>
			</InspectorControls>
			<div { ...props }>
				<div className="ppcp-overlay-child">
					<PayPalScriptProvider options={ urlParams }>
						<PayPalMessages
							style={ previewStyle }
							onRender={ () => setLoaded( true ) }
							amount={ amount }
						/>
					</PayPalScriptProvider>
				</div>
				<div className="ppcp-overlay-child ppcp-unclicable-overlay">
					{ ' ' }
					{ /* make the message not clickable */ }
					{ ! loaded && <Spinner /> }
				</div>
			</div>
		</>
	);
}
