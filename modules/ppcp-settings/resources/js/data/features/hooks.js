/**
 * Hooks: Provide the main API for components to interact with the features store.
 *
 * These encapsulate store interactions, offering a consistent interface.
 * Hooks simplify data access and manipulation for components.
 *
 * @file
 */

import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { STORE_NAME, REST_PATH } from './constants';

export const useFeatures = () => {
	const { features, isReady } = useSelect( ( select ) => {
		const store = select( STORE_NAME );

		return {
			features: store.getFeatures() || [],
			isReady: select( STORE_NAME ).transientData()?.isReady || false,
		};
	}, [] );

	const { setFeatures, setIsReady } = useDispatch( STORE_NAME );

	useEffect( () => {
		const loadInitialFeatures = async () => {
			try {
				const response = await apiFetch( { path: REST_PATH } );

				if ( response?.data?.features ) {
					const featuresData = response.data.features;

					if ( featuresData.length > 0 ) {
						await setFeatures( featuresData );
						await setIsReady( true );
					}
				}
			} catch ( error ) {}
		};

		if ( ! isReady ) {
			loadInitialFeatures();
		}
	}, [ isReady, setFeatures, setIsReady ] );

	return {
		features,
		isReady,
		fetchFeatures: async () => {
			try {
				const response = await apiFetch( { path: REST_PATH } );
				const featuresData = response.data?.features || [];

				if ( featuresData.length > 0 ) {
					await setFeatures( featuresData );
					await setIsReady( true );
					return { success: true, features: featuresData };
				}
				return { success: false, features: [] };
			} catch ( error ) {
				return { success: false, error, message: error.message };
			}
		},
	};
};
