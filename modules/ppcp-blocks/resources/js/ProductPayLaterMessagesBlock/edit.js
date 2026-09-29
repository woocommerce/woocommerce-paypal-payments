import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { PayPalScriptProvider, PayPalMessages } from '@paypal/react-paypal-js';
import { useScriptParams } from '@ppcp-paylater-block/hooks/script-params';
import { usePreviewTimeout } from '@ppcp-paylater-block/hooks/use-preview-timeout';
import { usePreviewController } from '@ppcp-paylater-block/hooks/use-preview-controller';
import { PreviewPlaceholder } from '@ppcp-paylater-block/components/preview-placeholder';

export default function Edit( { attributes, clientId, setAttributes } ) {
	const { ppcpId } = attributes;

	const [ loaded, setLoaded ] = useState( false );
	const timedOut = usePreviewTimeout( loaded );
	const { containerRef, renderKey } = usePreviewController(
		loaded,
		setLoaded
	);

	const productConfig = PcpProductPayLaterBlock.config.product;

	// v6 renders this as text regardless of settings, so the preview follows.
	const layout = PcpProductPayLaterBlock.isSdkV6Active
		? 'text'
		: productConfig.layout;

	let previewStyle = {};
	if ( layout === 'flex' ) {
		previewStyle = {
			layout,
			color: productConfig.color,
			ratio: productConfig.ratio,
		};
	} else {
		previewStyle = {
			layout,
			logo: {
				position: productConfig[ 'logo-position' ],
				type: productConfig[ 'logo-type' ],
			},
			text: {
				color: productConfig[ 'text-color' ],
				size: productConfig[ 'text-size' ],
			},
		};
	}

	let classes = [ 'ppcp-paylater-block-preview', 'ppcp-overlay-parent' ];
	if ( ! PcpProductPayLaterBlock.placementEnabled ) {
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

	if ( ! PcpProductPayLaterBlock.placementEnabled ) {
		return (
			<div { ...props }>
				<div className="block-editor-warning__contents">
					<p className="block-editor-warning__message">
						{ __(
							'Product - Pay Later Messaging cannot be used while the “Product” messaging placement is disabled. Enable the placement in the PayPal Payments Pay Later settings to reactivate this block.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<div className="block-editor-warning__actions">
						<span className="block-editor-warning__action">
							<a
								href={
									PcpProductPayLaterBlock.payLaterSettingsUrl
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
		PcpProductPayLaterBlock.ajax.cart_script_params
	);
	if ( scriptParams === null ) {
		return (
			<div { ...props }>
				<PreviewPlaceholder timedOut={ timedOut } />
			</div>
		);
	}

	const urlParams = {
		...scriptParams.url_params,
		components: 'messages',
		dataNamespace: 'ppcp-block-editor-product-paylater-message',
	};

	// The preview reuses the button SDK params, which disable the `paylater` funding
	// source when the Pay Later *button* is off for this location. Messaging is
	// independent of the button, so keep paylater enabled here or nothing renders.
	if ( urlParams[ 'disable-funding' ] ) {
		urlParams[ 'disable-funding' ] = urlParams[ 'disable-funding' ]
			.split( ',' )
			.filter(
				( source ) => source !== 'paylater' && source !== 'credit'
			)
			.join( ',' );
	}

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
							'Choose the layout and color of your messaging in the PayPal Payments Pay Later settings for the “Product” messaging placement.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<a href={ PcpProductPayLaterBlock.payLaterSettingsUrl }>
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
				<div className="ppcp-overlay-child" ref={ containerRef }>
					<PayPalScriptProvider
						key={ renderKey }
						options={ urlParams }
					>
						<PayPalMessages
							style={ previewStyle }
							onRender={ () => setLoaded( true ) }
							amount={ 50.0 }
						/>
					</PayPalScriptProvider>
				</div>
				<div className="ppcp-overlay-child ppcp-unclicable-overlay">
					{ ' ' }
					{ /* make the message not clickable */ }
					{ ! loaded && <PreviewPlaceholder timedOut={ timedOut } /> }
				</div>
			</div>
		</>
	);
}
