import { useCallback, useEffect, useState } from '@wordpress/element';

const checkIfCurrentTab = ( id ) => {
	return id && window.location.hash === `#${ id }`;
};

const determineInitialState = ( id, initiallyOpen ) => {
	if ( initiallyOpen !== null ) {
		return initiallyOpen;
	}
	return checkIfCurrentTab( id );
};

/**
 * Allows managing a toggle-able component, such as an accordion or a modal dialog.
 *
 * @param {string}       [id='']              - If provided, the toggle can be opened via the URL.
 * @param {null|boolean} [initiallyOpen=null] - If provided, it defines the initial open state.
 *                                            If omitted, the initial open state is determined by using the "id" logic (inspecting the URL).
 * @return {{isOpen: unknown, toggleOpen: (function(*): boolean)}} Hook object.
 */
export function useToggleState( id = '', initiallyOpen = null ) {
	const [ isOpen, setIsOpen ] = useState(
		determineInitialState( id, initiallyOpen )
	);

	useEffect( () => {
		const handleHashChange = () => {
			if ( checkIfCurrentTab( id ) ) {
				setIsOpen( true );
			}
		};

		window.addEventListener( 'hashchange', handleHashChange );
		return () => {
			window.removeEventListener( 'hashchange', handleHashChange );
		};
	}, [ id ] );

	const toggleOpen = useCallback(
		( ev ) => {
			setIsOpen( ! isOpen );
			ev?.preventDefault();
			return false;
		},
		[ isOpen ]
	);

	return { isOpen, setIsOpen, toggleOpen };
}
