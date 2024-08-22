export const cardFieldStyles = ( field ) => {
	const allowedProperties = [
		'appearance',
		'color',
		'direction',
		'font',
		'font-family',
		'font-size',
		'font-size-adjust',
		'font-stretch',
		'font-style',
		'font-variant',
		'font-variant-alternates',
		'font-variant-caps',
		'font-variant-east-asian',
		'font-variant-ligatures',
		'font-variant-numeric',
		'font-weight',
		'letter-spacing',
		'line-height',
		'opacity',
		'outline',
		'padding',
		'padding-bottom',
		'padding-left',
		'padding-right',
		'padding-top',
		'text-shadow',
		'transition',
		'-moz-appearance',
		'-moz-osx-font-smoothing',
		'-moz-tap-highlight-color',
		'-moz-transition',
		'-webkit-appearance',
		'-webkit-osx-font-smoothing',
		'-webkit-tap-highlight-color',
		'-webkit-transition',
	];

	const stylesRaw = window.getComputedStyle( field );
	const styles = {};
	Object.values( stylesRaw ).forEach( ( prop ) => {
		if ( ! stylesRaw[ prop ] || ! allowedProperties.includes( prop ) ) {
			return;
		}
		styles[ prop ] = '' + stylesRaw[ prop ];
	} );

	return styles;
};
