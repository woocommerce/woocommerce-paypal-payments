import { useState, useEffect } from '@wordpress/element';

export const useScriptParams = ( requestConfig ) => {
	const [ data, setData ] = useState( null );

	useEffect( () => {
		( async () => {
			try {
				const response = await fetch( requestConfig.endpoint );
				const json = await response.json();
				if ( json.success && json?.data?.url_params ) {
					setData( json.data );
				} else {
					setData( false );
				}
			} catch ( e ) {
				console.error( e );
				setData( false );
			}
		} )();
	}, [ requestConfig ] );

	return data;
};
