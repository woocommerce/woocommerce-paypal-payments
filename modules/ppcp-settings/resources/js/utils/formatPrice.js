const priceFormatInfo = {
	USD: {
		prefix: '$',
		suffix: 'USD',
	},
	CAD: {
		prefix: '$',
		suffix: 'CAD',
	},
	AUD: {
		prefix: '$',
		suffix: 'AUD',
	},
	EUR: {
		prefix: '€',
		suffix: '',
	},
	GBP: {
		prefix: '£',
		suffix: '',
	},
};

export const formatPrice = ( value, currency ) => {
	const currencyInfo = priceFormatInfo[ currency ];
	const amount = value.toFixed( 2 );

	if ( ! currencyInfo ) {
		console.error( `Unsupported currency: ${ currency }` );
		return amount;
	}

	return `${ currencyInfo.prefix }${ amount } ${ currencyInfo.suffix }`;
};
