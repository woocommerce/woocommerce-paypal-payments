import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { PayPalScriptProvider, PayPalButtons } from '@paypal/react-paypal-js';
import { useScriptParams } from '@ppcp-paylater-block/hooks/script-params';
import { usePreviewTimeout } from '@ppcp-paylater-block/hooks/use-preview-timeout';
import { usePreviewController } from '@ppcp-paylater-block/hooks/use-preview-controller';
import { PreviewPlaceholder } from '@ppcp-paylater-block/components/preview-placeholder';
import { V6ButtonPreview } from '../MiniCartSmartButtonsBlock/v6-button-preview';

export default function Edit() {
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

	const { sdkV6, buttonStyle } = PcpProductSmartButtonsBlock;
	const useSdkV6 = Boolean(
		PcpProductSmartButtonsBlock.isSdkV6Active && sdkV6 && buttonStyle
	);

	return (
		<div { ...props }>
			{ useSdkV6 ? (
				<V6ButtonPreview
					sdkV6={ sdkV6 }
					pageType="product-details"
					style={ buttonStyle }
				/>
			) : (
				<V5ButtonPreview />
			) }
		</div>
	);
}

/**
 * The v5 SDK preview, used while the v6 module is not loaded.
 *
 * @return {Object} The preview.
 */
function V5ButtonPreview() {
	const [ loaded, setLoaded ] = useState( false );
	const timedOut = usePreviewTimeout( loaded );
	const { containerRef, renderKey } = usePreviewController(
		loaded,
		setLoaded
	);
	const scriptParams = useScriptParams(
		PcpProductSmartButtonsBlock.ajax.cart_script_params
	);

	if ( ! scriptParams ) {
		// `false` means the params request failed, so there is nothing to wait for.
		return (
			<PreviewPlaceholder
				timedOut={ timedOut || scriptParams === false }
			/>
		);
	}

	const urlParams = {
		...scriptParams.url_params,
		components: 'buttons,funding-eligibility',
		dataNamespace: 'ppcp-block-editor-product-smart-buttons',
	};

	return (
		<>
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
		</>
	);
}
