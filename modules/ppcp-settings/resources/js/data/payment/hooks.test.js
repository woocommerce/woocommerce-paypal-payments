import { renderHook } from '@testing-library/react';
import { useSelect, useDispatch } from '@wordpress/data';
import { createHooksForStore } from '@ppcp-settings/data/utils';
import { usePayLaterVaultingDependency } from './hooks';

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
} ) );

jest.mock( '@ppcp-settings/data/utils', () => ( {
	createHooksForStore: jest.fn(),
} ) );

/**
 * Builds the { useTransient, usePersistent } pair that useStoreData() derives
 * from createHooksForStore(), pre-seeded with the transient/persistent values
 * a test needs.
 */
const createStoreHooks = ( { isReady = true, payLater } = {} ) => {
	const transientMap = { isReady };
	const persistentMap = { 'pay-later': payLater };

	return {
		useTransient: jest.fn( ( key ) => [ transientMap[ key ], jest.fn() ] ),
		usePersistent: jest.fn( ( key ) => [
			persistentMap[ key ],
			jest.fn(),
		] ),
	};
};

describe( 'usePayLaterVaultingDependency()', () => {
	let persistentData;

	beforeEach( () => {
		jest.clearAllMocks();

		persistentData = jest.fn();
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( { persistentData } ) )
		);
		useDispatch.mockReturnValue( {} );
	} );

	test( 'reports payLaterDependsOnVaulting as true when the server sends the dependency', () => {
		createHooksForStore.mockReturnValue(
			createStoreHooks( {
				isReady: true,
				payLater: {
					depends_on_settings: {
						settings: {
							savePaypalAndVenmo: {
								id: 'savePaypalAndVenmo',
								value: false,
							},
						},
					},
				},
			} )
		);

		const { result } = renderHook( () => usePayLaterVaultingDependency() );

		expect( result.current.payLaterDependsOnVaulting ).toBe( true );
	} );

	test( 'reports payLaterDependsOnVaulting as false for an eligible merchant whose pay-later method omits the dependency', () => {
		createHooksForStore.mockReturnValue(
			createStoreHooks( {
				isReady: true,
				payLater: { id: 'pay-later' },
			} )
		);

		const { result } = renderHook( () => usePayLaterVaultingDependency() );

		expect( result.current.payLaterDependsOnVaulting ).toBe( false );
	} );

	test( 'reports payLaterDependsOnVaulting as false without crashing when the pay-later method is missing entirely', () => {
		createHooksForStore.mockReturnValue(
			createStoreHooks( { isReady: true, payLater: undefined } )
		);

		const { result } = renderHook( () => usePayLaterVaultingDependency() );

		expect( result.current.payLaterDependsOnVaulting ).toBe( false );
	} );

	test( 'surfaces isReady as false and triggers a persistentData load while the store has not loaded yet', () => {
		createHooksForStore.mockReturnValue(
			createStoreHooks( { isReady: false, payLater: undefined } )
		);

		const { result } = renderHook( () => usePayLaterVaultingDependency() );

		expect( result.current.isReady ).toBe( false );
		expect( persistentData ).toHaveBeenCalled();
	} );
} );
