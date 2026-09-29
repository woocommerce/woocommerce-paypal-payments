import { useState, useEffect } from '@wordpress/element';
import { loadEditorMessages } from '@ppcp-sdk-v6/messages/editorPreview';
import { buildMessageElement } from '@ppcp-sdk-v6/messages/renderer';

/**
 * Renders a PayPal SDK v6 `<paypal-message>` preview into a container.
 *
 * The SDK is loaded into the container's own window, not the top one: in the
 * Site Editor and the iframed post editor the block lives in the canvas iframe,
 * whose custom element registry the top window's SDK never reaches, so an
 * element there would stay an inert tag.
 *
 * v6 is unaffected by the v5 SDK instances other editor scripts load, whose
 * shared messaging state kept the v5 preview from rendering in the Site Editor.
 *
 * @param {Object} options          - The preview options.
 * @param {Object} options.sdkV6    - The localized sdkV6 data (sdkUrl, clientId, currency, locale).
 * @param {string} options.amount   - The sample amount to price.
 * @param {string} options.pageType - The v6 page type.
 * @param {Object} options.style    - The v6 style values.
 * @return {{containerRef: Function, loaded: boolean, failed: boolean}} Container ref and state.
 */
export function useV6MessagePreview( { sdkV6, amount, pageType, style } ) {
	const [ container, setContainer ] = useState( null );
	const [ loaded, setLoaded ] = useState( false );
	const [ failed, setFailed ] = useState( false );

	const { sdkUrl, clientId, currency, locale } = sdkV6;
	const { logoType, logoPosition, textColor, fontSize } = style;

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

		loadEditorMessages( targetWindow, {
			sdkUrl,
			clientId,
			currency,
			locale,
			pageType,
		} )
			.then( () => {
				if ( cancelled ) {
					return;
				}

				element = buildMessageElement( doc, {
					amount,
					currency,
					pageType,
					style: { logoType, logoPosition, textColor, fontSize },
				} );

				// The element gains height once the message content arrived.
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
				console.error( '[ppcp] Pay Later preview', error );
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
		currency,
		locale,
		amount,
		pageType,
		logoType,
		logoPosition,
		textColor,
		fontSize,
	] );

	return { containerRef: setContainer, loaded, failed };
}
