export const inputValue = ( element ) => {
	const $el = jQuery( element );

	if ( $el.is( ':checkbox' ) || $el.is( ':radio' ) ) {
		if ( $el.is( ':checked' ) ) {
			return $el.val();
		}
		return null;
	}
	return $el.val();
};
