import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useRef,
	useState,
} from '@wordpress/element';

const HIGHLIGHT_DURATION = 2000;
const SCROLL_SETTLE_DELAY = 300;
const HIGHLIGHT_CLASS = 'ppcp-highlight';
const NAV_SELECTOR = '.ppcp-r-navigation-container';
const NAV_OFFSET = 55;

const ScrollHighlightContext = createContext( null );

/**
 * Provider that manages a registry of scrollable elements and highlight state.
 *
 * @param {Object}      props
 * @param {JSX.Element} props.children
 * @return {JSX.Element} Provider wrapper
 */
export const ScrollHighlightProvider = ( { children } ) => {
	const registry = useRef( new Map() );
	const [ highlightedId, setHighlightedId ] = useState( null );
	const highlightTimerRef = useRef( null );
	const highlightedElementRef = useRef( null );

	const register = useCallback( ( id, element ) => {
		if ( element ) {
			registry.current.set( id, element );
		} else {
			registry.current.delete( id );
		}
	}, [] );

	const scrollTo = useCallback( ( elementId, highlight = true ) => {
		return new Promise( ( resolve ) => {
			const element = registry.current.get( elementId );

			if ( ! element ) {
				resolve();
				return;
			}

			const navContainer = document.querySelector( NAV_SELECTOR );
			const navHeight = navContainer ? navContainer.offsetHeight : 0;
			const rect = element.getBoundingClientRect();
			const scrollPosition =
				rect.top + window.scrollY - ( navHeight + NAV_OFFSET );

			window.scrollTo( {
				top: scrollPosition,
				behavior: 'smooth',
			} );

			if ( highlight ) {
				if ( highlightTimerRef.current ) {
					clearTimeout( highlightTimerRef.current );
					// Clean up previous highlight from DOM.
					const prevElement = highlightedElementRef.current;
					if ( prevElement ) {
						prevElement.classList.remove( HIGHLIGHT_CLASS );
					}
				}

				// Apply highlight immediately via DOM for instant visual feedback.
				element.classList.add( HIGHLIGHT_CLASS );
				highlightedElementRef.current = element;
				setHighlightedId( elementId );

				highlightTimerRef.current = setTimeout( () => {
					element.classList.remove( HIGHLIGHT_CLASS );
					highlightedElementRef.current = null;
					setHighlightedId( null );
					highlightTimerRef.current = null;
				}, HIGHLIGHT_DURATION );
			}

			setTimeout( resolve, SCROLL_SETTLE_DELAY );
		} );
	}, [] );

	useEffect( () => {
		return () => {
			if ( highlightTimerRef.current ) {
				clearTimeout( highlightTimerRef.current );
			}
			if ( highlightedElementRef.current ) {
				highlightedElementRef.current.classList.remove(
					HIGHLIGHT_CLASS
				);
			}
		};
	}, [] );

	return (
		<ScrollHighlightContext.Provider
			value={ { register, scrollTo, highlightedId } }
		>
			{ children }
		</ScrollHighlightContext.Provider>
	);
};

/**
 * Hook for components that are scroll targets.
 * Returns a ref callback and whether the element is currently highlighted.
 *
 * @param {string} id - The unique ID of this scroll target
 * @return {{ ref: Function, isHighlighted: boolean }} Ref callback and highlight state
 */
export const useScrollTarget = ( id ) => {
	const context = useContext( ScrollHighlightContext );

	const ref = useCallback(
		( element ) => {
			if ( context ) {
				context.register( id, element );
			}
		},
		[ context, id ]
	);

	const isHighlighted = context ? context.highlightedId === id : false;

	return { ref, isHighlighted };
};

/**
 * Hook that provides a function to scroll to and highlight registered targets.
 *
 * @return {Function} scrollTo(elementId, highlight?) function
 */
export const useScrollTo = () => {
	const context = useContext( ScrollHighlightContext );

	if ( ! context ) {
		return () => Promise.resolve();
	}

	return context.scrollTo;
};
