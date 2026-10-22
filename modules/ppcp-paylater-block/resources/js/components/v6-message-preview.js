import { useV6MessagePreview } from '../hooks/use-v6-message-preview';
import { usePreviewTimeout } from '../hooks/use-preview-timeout';
import { PreviewPlaceholder } from './preview-placeholder';

/**
 * Pay Later message preview rendered with the PayPal SDK v6.
 *
 * Meant for a `ppcp-overlay-parent` block wrapper: the message sits under an
 * overlay that keeps it unclickable and shows the placeholder until it renders.
 *
 * @param {Object} props          - Component props.
 * @param {Object} props.sdkV6    - The localized sdkV6 data.
 * @param {string} props.amount   - The sample amount to price.
 * @param {string} props.pageType - The v6 page type.
 * @param {Object} props.style    - The v6 style values.
 * @return {Object} The preview.
 */
export function V6MessagePreview( { sdkV6, amount, pageType, style } ) {
	const { containerRef, loaded, failed } = useV6MessagePreview( {
		sdkV6,
		amount,
		pageType,
		style,
	} );
	const timedOut = usePreviewTimeout( loaded );

	return (
		<>
			<div className="ppcp-overlay-child" ref={ containerRef } />
			<div className="ppcp-overlay-child ppcp-unclicable-overlay">
				{ ! loaded && (
					<PreviewPlaceholder timedOut={ timedOut || failed } />
				) }
			</div>
		</>
	);
}
