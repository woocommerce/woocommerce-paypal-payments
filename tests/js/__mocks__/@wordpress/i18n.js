// Mock for @wordpress/i18n

const __ = jest.fn( ( text, domain ) => text );
const sprintf = jest.fn( ( format, ...args ) => {
	let index = 0;
	return format.replace( /%[sd]/g, () => {
		const arg = args[ index++ ];
		return arg !== undefined ? String( arg ) : '';
	} );
} );

export { __, sprintf };
