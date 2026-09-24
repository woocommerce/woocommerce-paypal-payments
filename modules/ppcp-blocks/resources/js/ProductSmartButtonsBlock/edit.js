import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { PayPalScriptProvider, PayPalButtons } from '@paypal/react-paypal-js';
import { useScriptParams } from '@ppcp-paylater-block/hooks/script-params';
import { usePreviewTimeout } from '@ppcp-paylater-block/hooks/use-preview-timeout';
import { usePreviewController } from '@ppcp-paylater-block/hooks/use-preview-controller';
import { PreviewPlaceholder } from '@ppcp-paylater-block/components/preview-placeholder';

export default function Edit() {
	const [ loaded, setLoaded ] = useState( false );
	const timedOut = usePreviewTimeout( loaded );
	const { containerRef, renderKey } = usePreviewController(
		loaded,
		setLoaded
	);

	const classes = [ 'ppcp-paylater-block-preview', 'ppcp-overlay-parent' ];
	if ( ! PcpProductSmartButtonsBlock.placementEnabled ) {
		classes.push( 'ppcp-paylater-unavailable', 'block-editor-warning' );
	}
	const props = useBlockProps( { className: classes.join( ' ' ) } );

	if ( ! PcpProductSmartButtonsBlock.placementEnabled ) {
		return (
			<div { ...props }>
				<div className="block-editor-warning__contents">
					<p className="block-editor-warning__message">
						{ __(
							'PayPal buttons cannot be used while the “Product” buttons placement is disabled. Enable the placement in the PayPal Payments settings to reactivate this block.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<div className="block-editor-warning__actions">
						<span className="block-editor-warning__action">
							<a href={ PcpProductSmartButtonsBlock.settingsUrl }>
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
		PcpProductSmartButtonsBlock.ajax.cart_script_params
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
		components: 'buttons,funding-eligibility',
		dataNamespace: 'ppcp-block-editor-product-smart-buttons',
	};

	return (
		<div { ...props }>
			<div className="ppcp-overlay-child" ref={ containerRef }>
				<PayPalScriptProvider key={ renderKey } options={ urlParams }>
					<PayPalButtons
						forceReRender={ [ renderKey ] }
						onInit={ () => setLoaded( true ) }
						createOrder={ () => Promise.resolve( '' ) }
						onApprove={ () => Promise.resolve() }
					/>
				</PayPalScriptProvider>
			</div>
			<div className="ppcp-overlay-child ppcp-unclicable-overlay">
				{ ' ' }
				{ /* make the buttons not clickable in the editor */ }
				{ ! loaded && <PreviewPlaceholder timedOut={ timedOut } /> }
			</div>
		</div>
	);
}
