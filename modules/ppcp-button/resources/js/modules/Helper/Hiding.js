/**
 * @param  selectorOrElement
 * @return {Element}
 */
const getElement = ( selectorOrElement ) => {
	if ( typeof selectorOrElement === 'string' ) {
		return document.querySelector( selectorOrElement );
	}
	return selectorOrElement;
};

const triggerHidden = ( handler, selectorOrElement, element ) => {
	jQuery( document ).trigger( 'ppcp-hidden', {
		handler,
		action: 'hide',
		selector: selectorOrElement,
		element,
	} );
};

const triggerShown = ( handler, selectorOrElement, element ) => {
	jQuery( document ).trigger( 'ppcp-shown', {
		handler,
		action: 'show',
		selector: selectorOrElement,
		element,
	} );
};

export const isVisible = ( element ) => {
	return !! (
		element.offsetWidth ||
		element.offsetHeight ||
		element.getClientRects().length
	);
};

export const setVisible = ( selectorOrElement, show, important = false ) => {
	const element = getElement( selectorOrElement );
	if ( ! element ) {
		return;
	}

	const currentValue = element.style.getPropertyValue( 'display' );

	if ( ! show ) {
		if ( currentValue === 'none' ) {
			return;
		}

		element.style.setProperty(
			'display',
			'none',
			important ? 'important' : ''
		);
		triggerHidden( 'Hiding.setVisible', selectorOrElement, element );
	} else {
		if ( currentValue === 'none' ) {
			element.style.removeProperty( 'display' );
			triggerShown( 'Hiding.setVisible', selectorOrElement, element );
		}

		// still not visible (if something else added display: none in CSS)
		if ( ! isVisible( element ) ) {
			element.style.setProperty( 'display', 'block' );
			triggerShown( 'Hiding.setVisible', selectorOrElement, element );
		}
	}
};

export const setVisibleByClass = ( selectorOrElement, show, hiddenClass ) => {
	const element = getElement( selectorOrElement );
	if ( ! element ) {
		return;
	}

	if ( show ) {
		element.classList.remove( hiddenClass );
		triggerShown( 'Hiding.setVisibleByClass', selectorOrElement, element );
	} else {
		element.classList.add( hiddenClass );
		triggerHidden( 'Hiding.setVisibleByClass', selectorOrElement, element );
	}
};

export const hide = ( selectorOrElement, important = false ) => {
	setVisible( selectorOrElement, false, important );
};

export const show = ( selectorOrElement ) => {
	setVisible( selectorOrElement, true );
};
