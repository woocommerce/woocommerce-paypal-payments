/* global jQuery */

/**
 * Returns a Map with all input fields that are relevant to render the preview of the
 * given payment button.
 *
 * @param {string} apmName - Value of the custom attribute `data-ppcp-apm-name`.
 * @return {Map<string, {val:Function, el:HTMLInputElement}>} List of input elements found on the current admin page.
 */
export function getButtonFormFields( apmName ) {
	const inputFields = document.querySelectorAll(
		`[data-ppcp-apm-name="${ apmName }"]`
	);

	return [ ...inputFields ].reduce( ( fieldMap, el ) => {
		const key = el.dataset.ppcpFieldName;
		let getter = () => el.value;

		if ( 'LABEL' === el.tagName ) {
			el = el.querySelector( 'input[type="checkbox"]' );
			getter = () => el.checked;
		}

		return fieldMap.set( key, {
			val: getter,
			el,
		} );
	}, new Map() );
}

/**
 * Returns a function that triggers an update of the specified preview button, when invoked.
 *
 * @param {string} apmName
 * @return {((object) => void)} Trigger-function; updates preview buttons when invoked.
 */
export function buttonRefreshTriggerFactory( apmName ) {
	const eventName = `ppcp_paypal_render_preview_${ apmName }`;

	return ( settings ) => {
		jQuery( document ).trigger( eventName, settings );
	};
}

/**
 * Returns a function that gets the current form values of the specified preview button.
 *
 * @param {string} apmName
 * @return {() => {button: {wrapper:string, is_enabled:boolean, style:{}}}} Getter-function; returns preview config details when invoked.
 */
export function buttonSettingsGetterFactory( apmName ) {
	const fields = getButtonFormFields( apmName );

	return () => {
		const buttonConfig = {
			wrapper: `#ppcp${ apmName }ButtonPreview`,
			is_enabled: true,
			style: {},
		};

		fields.forEach( ( item, name ) => {
			if ( 'is_enabled' === name ) {
				buttonConfig[ name ] = item.val();
			} else {
				buttonConfig.style[ name ] = item.val();
			}
		} );

		return { button: buttonConfig };
	};
}
