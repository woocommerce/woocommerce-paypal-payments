import { renderHook } from '@testing-library/react';
import { useSelect } from '@wordpress/data';
import useSettingDependencyState from './useSettingDependencyState';

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
} ) );

/**
 * A pay-later method as the server sends it for a non-eligible merchant:
 * it is only usable while vaulting ("Save PayPal and Venmo") is off.
 */
const payLaterDependentOnVaulting = () => ( {
	id: 'pay-later',
	depends_on_settings: {
		settings: {
			savePaypalAndVenmo: {
				id: 'savePaypalAndVenmo',
				value: false,
			},
		},
	},
} );

/**
 * A pay-later method as the server sends it for an eligible merchant:
 * no dependency at all, so vaulting cannot disable it.
 */
const payLaterWithoutDependency = () => ( { id: 'pay-later' } );

const renderWithPersistentData = ( methods, persistentData ) => {
	useSelect.mockImplementation( ( callback ) =>
		callback( () => ( {
			persistentData: () => persistentData,
		} ) )
	);

	return renderHook( () => useSettingDependencyState( methods ) );
};

describe( 'useSettingDependencyState()', () => {
	beforeEach( () => {
		jest.clearAllMocks();
	} );

	test( 'disables pay-later for a non-eligible merchant when vaulting is enabled', () => {
		const { result } = renderWithPersistentData(
			[ payLaterDependentOnVaulting() ],
			{ savePaypalAndVenmo: true }
		);

		expect( result.current[ 'pay-later' ] ).toEqual( {
			isDisabled: true,
			settingId: 'savePaypalAndVenmo',
			requiredValue: false,
		} );
	} );

	test( 'leaves pay-later enabled for a non-eligible merchant when vaulting is disabled', () => {
		const { result } = renderWithPersistentData(
			[ payLaterDependentOnVaulting() ],
			{ savePaypalAndVenmo: false }
		);

		expect( result.current[ 'pay-later' ] ).toBeUndefined();
	} );

	test( 'leaves pay-later enabled for an eligible merchant with vaulting on, since the server sends no dependency', () => {
		const { result } = renderWithPersistentData(
			[ payLaterWithoutDependency() ],
			{ savePaypalAndVenmo: true }
		);

		expect( result.current[ 'pay-later' ] ).toBeUndefined();
	} );

	test( 'returns null when the settings store is unavailable', () => {
		useSelect.mockImplementation( ( callback ) => callback( () => null ) );

		const { result } = renderHook( () =>
			useSettingDependencyState( [ payLaterDependentOnVaulting() ] )
		);

		expect( result.current ).toBeNull();
	} );

	test( 'returns null when no methods are given', () => {
		const { result } = renderWithPersistentData( [], {
			savePaypalAndVenmo: true,
		} );

		expect( result.current ).toBeNull();
	} );
} );
