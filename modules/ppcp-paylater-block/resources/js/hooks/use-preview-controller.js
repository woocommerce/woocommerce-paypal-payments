import { useState, useRef, useEffect, useCallback } from '@wordpress/element';

export const RETRY_DELAY_MS = 2000;
export const MAX_RETRIES = 3;

/**
 * Drives the live PayPal message preview so it renders reliably in the editor.
 *
 * Two problems make the live message flaky inside the Site Editor:
 * - It renders into the editor-canvas iframe, which may not be laid out when
 *   the block first mounts, so the message is drawn into a zero-size box and
 *   never appears - and the PayPal SDK renders once and does not retry.
 * - The `onRender` callback does not fire reliably in that cross-document setup,
 *   so we cannot depend on it to know the message drew.
 *
 * This hook addresses both. It returns a callback ref for the message container
 * and a `renderKey`:
 * - Attach the ref to the container. A MutationObserver flips `loaded` (via the
 *   passed setter) the moment a visible message iframe appears, independent of
 *   `onRender`.
 * - Pass `renderKey` as the `key` of the PayPalScriptProvider. While the message
 *   has not loaded, the key is bumped a few times on a timer, remounting the SDK
 *   so it re-attempts the render once the canvas has settled.
 *
 * @param {boolean}  loaded    Whether the message has rendered.
 * @param {Function} setLoaded Setter called with `true` once a rendered message is detected.
 * @return {{containerRef: Function, renderKey: number}} Container ref and remount key.
 */
export function usePreviewController( loaded, setLoaded ) {
	const [ renderKey, setRenderKey ] = useState( 0 );
	const observerRef = useRef( null );
	const attemptsRef = useRef( 0 );

	const containerRef = useCallback(
		( node ) => {
			if ( observerRef.current ) {
				observerRef.current.disconnect();
				observerRef.current = null;
			}
			if ( ! node ) {
				return;
			}

			const markLoadedIfRendered = () => {
				const frame = node.querySelector( 'iframe' );
				if ( frame && frame.offsetHeight > 0 ) {
					setLoaded( true );
					if ( observerRef.current ) {
						observerRef.current.disconnect();
						observerRef.current = null;
					}
					return true;
				}
				return false;
			};

			if ( markLoadedIfRendered() ) {
				return;
			}

			observerRef.current = new window.MutationObserver(
				markLoadedIfRendered
			);
			observerRef.current.observe( node, {
				childList: true,
				subtree: true,
				attributes: true,
			} );
		},
		[ setLoaded ]
	);

	useEffect( () => {
		if ( loaded || attemptsRef.current >= MAX_RETRIES ) {
			return undefined;
		}
		const timer = setTimeout( () => {
			attemptsRef.current += 1;
			setRenderKey( ( key ) => key + 1 );
		}, RETRY_DELAY_MS );
		return () => clearTimeout( timer );
	}, [ loaded, renderKey ] );

	useEffect(
		() => () => {
			if ( observerRef.current ) {
				observerRef.current.disconnect();
			}
		},
		[]
	);

	return { containerRef, renderKey };
}
