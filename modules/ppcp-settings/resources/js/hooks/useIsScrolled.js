/**
 * Taken from WooCommerce core:
 * https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/admin/client/hooks/useIsScrolled.js
 */

import { useEffect, useRef, useState } from '@wordpress/element';

const isAtBottom = () =>
	window.innerHeight + window.scrollY >= document.body.scrollHeight;

const useIsScrolled = () => {
	const [ isScrolled, setIsScrolled ] = useState( false );
	const [ atBottom, setAtBottom ] = useState( isAtBottom() );
	const rafHandle = useRef( null );
	useEffect( () => {
		const updateIsScrolled = () => {
			setIsScrolled( window.pageYOffset > 20 );
			setAtBottom( isAtBottom() );
		};

		const scrollListener = () => {
			rafHandle.current =
				window.requestAnimationFrame( updateIsScrolled );
		};

		window.addEventListener( 'scroll', scrollListener );

		window.addEventListener( 'resize', scrollListener );

		return () => {
			window.removeEventListener( 'scroll', scrollListener );
			window.removeEventListener( 'resize', scrollListener );
			window.cancelAnimationFrame( rafHandle.current );
		};
	}, [] );

	return {
		isScrolled,
		atBottom,
		atTop: ! isScrolled,
	};
};

export default useIsScrolled;
