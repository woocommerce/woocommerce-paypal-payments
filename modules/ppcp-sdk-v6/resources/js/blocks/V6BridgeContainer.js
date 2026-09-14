/**
 * Bridges a wallet button into React.
 *
 * Like V6ButtonContainer, React renders an empty container and never reconciles
 * what the bridge puts inside. These bridges are asynchronous, so a resolve can
 * arrive after the component is gone, and they may decline to render at all.
 *
 * @package
 */

import { createElement, useEffect, useRef } from '@wordpress/element';
import { renderMethodInto } from '../methods/renderMethods';

/**
 * Mounts one wallet button into a container div.
 *
 * The button is rebuilt only when the session or `rebuildKey` changes, so
 * ordinary React re-renders leave the mounted button untouched.
 *
 * @param {Object}  props              - Component props.
 * @param {string}  props.method       - The wallet's funding source.
 * @param {Object}  props.config       - The localized sdk-v6 config.
 * @param {string}  props.context      - The page context.
 * @param {?Object} props.session      - The wallet's payment session.
 * @param {Object}  props.overrides    - Overrides for the bridge; see
 *                                     renderMethodInto().
 * @param {string}  [props.rebuildKey] - Changes to rebuild the button when a
 *                                     captured override changed.
 * @return {Object} The container element.
 */
export function V6BridgeContainer( {
	method,
	config,
	context,
	session,
	overrides,
	rebuildKey = '',
} ) {
	const containerRef = useRef( null );

	const overridesRef = useRef( overrides );
	useEffect( () => {
		overridesRef.current = overrides;
	} );

	useEffect( () => {
		const container = containerRef.current;
		if ( ! container || ! session ) {
			return undefined;
		}

		let active = true;

		renderMethodInto( method, {
			/*
			 * overrides: Spread values are captured for the button's lifetime;
			 *   the callbacks fire later, hence through the ref.
			 * overrides.isObsolete: Owned here, not by the caller, since only
			 *   the side that tore the render down knows it is stale.
			 */
			wrapper: container,
			config,
			context,
			session,
			overrides: {
				...overridesRef.current,
				isObsolete: () => ! active,
				onClick: () => overridesRef.current.onClick?.(),
				onUnavailable: () => {
					if ( active ) {
						overridesRef.current.onUnavailable?.();
					}
				},
				onSheetClosed: () => overridesRef.current.onSheetClosed?.(),
			},
		} ).catch( ( error ) => {
			// eslint-disable-next-line no-console
			console.error( '[ppcp-sdk-v6] wallet render failed', error );

			// Not onUnavailable: a throw on the way to the button says nothing
			// about whether this shopper could have paid.
			if ( active ) {
				overridesRef.current.onRenderFailed?.( error );
			}
		} );

		return () => {
			active = false;
			// Also empties the box a still-in-flight render is about to fill.
			container.replaceChildren();
		};
		// Everything else is captured at build time; rebuildKey asks for a
		// fresh button.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ method, session, rebuildKey ] );

	return createElement( 'div', { ref: containerRef } );
}
