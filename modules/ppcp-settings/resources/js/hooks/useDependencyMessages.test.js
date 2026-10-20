import { renderHook } from '@testing-library/react';
import useDependencyMessages from './useDependencyMessages';

jest.mock(
	'@ppcp-settings/Components/Screens/Settings/Components/Payment/PaymentDependencyMessage',
	() => () => 'parent-dependency-message'
);
jest.mock(
	'@ppcp-settings/Components/Screens/Settings/Components/Payment/PaymentMethodValueDependencyMessage',
	() => () => 'value-dependency-message'
);
jest.mock(
	'@ppcp-settings/Components/Screens/Settings/Components/Payment/SettingDependencyMessage',
	() => () => 'setting-dependency-message'
);

const payLaterMethod = () => ( { id: 'pay-later' } );

describe( 'useDependencyMessages()', () => {
	test( 'marks pay-later as disabled when the setting dependency reports it disabled (non-eligible merchant)', () => {
		const settingDependencies = {
			'pay-later': {
				isDisabled: true,
				settingId: 'savePaypalAndVenmo',
				requiredValue: false,
			},
		};

		const { result } = renderHook( () =>
			useDependencyMessages(
				[ payLaterMethod() ],
				{},
				settingDependencies
			)
		);

		expect( result.current[ 'pay-later' ].isMethodDisabled ).toBe( true );
		expect(
			result.current[ 'pay-later' ].dependencyMessage
		).not.toBeNull();
	} );

	test( 'leaves pay-later enabled when there is no setting dependency (eligible merchant)', () => {
		const { result } = renderHook( () =>
			useDependencyMessages( [ payLaterMethod() ], {}, {} )
		);

		expect( result.current[ 'pay-later' ].isMethodDisabled ).toBe(
			false
		);
		expect( result.current[ 'pay-later' ].dependencyMessage ).toBeNull();
	} );

	test( 'returns an empty map when there are no methods', () => {
		const { result } = renderHook( () =>
			useDependencyMessages( [], {}, {} )
		);

		expect( result.current ).toEqual( {} );
	} );
} );
