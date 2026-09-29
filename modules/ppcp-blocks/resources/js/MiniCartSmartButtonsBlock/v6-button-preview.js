import { useV6ButtonPreview } from './use-v6-button-preview';
import { usePreviewTimeout } from '@ppcp-paylater-block/hooks/use-preview-timeout';
import { PreviewPlaceholder } from '@ppcp-paylater-block/components/preview-placeholder';

/**
 * Mini-Cart PayPal button preview (SDK v6), for a `ppcp-overlay-parent` wrapper: the button
 * sits under an overlay that keeps it unclickable and shows the placeholder until it renders.
 *
 * @param {Object} props          - Component props.
 * @param {Object} props.sdkV6    - The localized sdkV6 data.
 * @param {string} props.pageType - The v6 page type.
 * @param {Object} props.style    - The v6 button style.
 * @return {Object} The preview.
 */
export function V6ButtonPreview( { sdkV6, pageType, style } ) {
	const { containerRef, loaded, failed } = useV6ButtonPreview( {
		sdkV6,
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
