export const normalizeStyleForFundingSource = ( style, fundingSource ) => {
	const commonProps = {};
	[ 'shape', 'height' ].forEach( ( prop ) => {
		if ( style[ prop ] ) {
			commonProps[ prop ] = style[ prop ];
		}
	} );

	switch ( fundingSource ) {
		case 'paypal':
			return style;
		case 'paylater':
			return {
				color: style.color,
				...commonProps,
			};
		default:
			return commonProps;
	}
};
