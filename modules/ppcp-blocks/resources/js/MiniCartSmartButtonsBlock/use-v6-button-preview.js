import { useState, useEffect } from '@wordpress/element';
import { loadEditorButtons } from '@ppcp-sdk-v6/blocks/editorButtonPreview';
import { createPreviewButton } from '@ppcp-sdk-v6/components/buttonRenderer';

/**
 * Renders a PayPal SDK v6 `<paypal-button>` preview into a container. Mirrors
 * useV6MessagePreview: the SDK loads into the container's own window (the Site Editor canvas
 * iframe), and the button binds no session so it only shows its appearance.
 *
 * @param {Object} options          - The preview options.
 * @param {Object} options.sdkV6    - The localized sdkV6 data (sdkUrl, clientId, locale).
 * @param {string} options.pageType - The v6 page type.
 * @param {Object} options.style    - The v6 button style ({ colorClass, borderRadius, height }).
 * @return {{containerRef: Function, loaded: boolean, failed: boolean}} Container ref and state.
 */
export function useV6ButtonPreview( { sdkV6, pageType, style } ) {
	const [ container, setContainer ] = useState( null );
	const [ loaded, setLoaded ] = useState( false );
	const [ failed, setFailed ] = useState( false );

	const { sdkUrl, clientId, locale } = sdkV6;
	const { colorClass, borderRadius, height } = style;

	useEffect( () => {
		if ( ! container ) {
			return undefined;
		}

		const doc = container.ownerDocument;
		const targetWindow = doc.defaultView;
		let cancelled = false;
		let element = null;
		let observer = null;

		setLoaded( false );
		setFailed( false );

		loadEditorButtons( targetWindow, {
			sdkUrl,
			clientId,
			locale,
			pageType,
		} )
			.then( () => {
				if ( cancelled ) {
					return;
				}

				element = createPreviewButton( doc, {
					method: 'paypal',
					styles: { colorClass, borderRadius, height },
				} );
				if ( ! element ) {
					setFailed( true );
					return;
				}

				// Loaded once the button gains height.
				observer = new targetWindow.ResizeObserver( () => {
					if ( element.offsetHeight > 0 ) {
						setLoaded( true );
						observer.disconnect();
					}
				} );
				observer.observe( element );

				container.appendChild( element );
			} )
			.catch( ( error ) => {
				if ( cancelled ) {
					return;
				}

				console.error( '[ppcp] Mini-Cart button preview', error );
				setFailed( true );
			} );

		return () => {
			cancelled = true;
			if ( observer ) {
				observer.disconnect();
			}
			if ( element ) {
				element.remove();
			}
		};
	}, [
		container,
		sdkUrl,
		clientId,
		locale,
		pageType,
		colorClass,
		borderRadius,
		height,
	] );

	return { containerRef: setContainer, loaded, failed };
}
