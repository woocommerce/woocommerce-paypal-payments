import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { PreviewPlaceholder } from '@ppcp-paylater-block/components/preview-placeholder';
import { V6ButtonPreview } from './v6-button-preview';

export default function Edit() {
	const classes = [ 'ppcp-paylater-block-preview', 'ppcp-overlay-parent' ];
	if ( ! PcpMiniCartSmartButtonsBlock.placementEnabled ) {
		classes.push( 'ppcp-paylater-unavailable', 'block-editor-warning' );
	}
	const props = useBlockProps( { className: classes.join( ' ' ) } );

	if ( ! PcpMiniCartSmartButtonsBlock.placementEnabled ) {
		return (
			<div { ...props }>
				<div className="block-editor-warning__contents">
					<p className="block-editor-warning__message">
						{ __(
							'PayPal buttons cannot be used while the “Mini cart” buttons placement is disabled. Enable the placement in the PayPal Payments settings to reactivate this block.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<div className="block-editor-warning__actions">
						<span className="block-editor-warning__action">
							<a
								href={
									PcpMiniCartSmartButtonsBlock.settingsUrl
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

	const { sdkV6, buttonStyle } = PcpMiniCartSmartButtonsBlock;
	const useSdkV6 = Boolean(
		PcpMiniCartSmartButtonsBlock.isSdkV6Active && sdkV6 && buttonStyle
	);

	return (
		<div { ...props }>
			{ useSdkV6 ? (
				<V6ButtonPreview
					sdkV6={ sdkV6 }
					pageType="cart"
					style={ buttonStyle }
				/>
			) : (
				<PreviewPlaceholder timedOut={ false } />
			) }
		</div>
	);
}
