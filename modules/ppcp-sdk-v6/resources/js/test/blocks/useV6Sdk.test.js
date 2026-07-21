jest.mock( '../../sdkLoader', () => ( { loadSdkV6: jest.fn() } ) );
jest.mock( '../../eligibility', () => ( { checkEligibility: jest.fn() } ) );
jest.mock( '../../sessions/createSession', () => ( {
	createSession: jest.fn(),
} ) );

import { renderHook, waitFor } from '@testing-library/react';
import { loadSdkV6 } from '../../sdkLoader';
import { checkEligibility } from '../../eligibility';
import { createSession } from '../../sessions/createSession';
import { useV6Sdk } from '../../blocks/useV6Sdk';

const config = { currency: 'USD', buyer_country: 'US' };
const sdk = { id: 'sdk' };

function eligibility( overrides ) {
	return {
		paypal: true,
		venmo: false,
		paylater: false,
		payLaterDetails: null,
		...overrides,
	};
}

beforeEach( () => {
	loadSdkV6.mockReset().mockResolvedValue( sdk );
	checkEligibility.mockReset();
	createSession
		.mockReset()
		.mockImplementation( ( _sdk, method ) => ( { method } ) );
} );

describe( 'useV6Sdk', () => {
	test( 'builds a session only for each eligible method', async () => {
		checkEligibility.mockResolvedValue( eligibility() );

		const { result } = renderHook( () =>
			useV6Sdk( config, 'checkout-block', '10.00' )
		);

		await waitFor( () => expect( result.current.sessions ).not.toBeNull() );

		expect( result.current.sessions.map.paypal ).toEqual( {
			method: 'paypal',
		} );
		expect( result.current.sessions.map.venmo ).toBeUndefined();
		expect( createSession ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'reuses the same sessions object when the eligible set is unchanged', async () => {
		checkEligibility.mockResolvedValue( eligibility() );

		const { result, rerender } = renderHook(
			( { amount } ) => useV6Sdk( config, 'checkout-block', amount ),
			{ initialProps: { amount: '10.00' } }
		);

		await waitFor( () => expect( result.current.sessions ).not.toBeNull() );
		const firstSessions = result.current.sessions;

		rerender( { amount: '20.00' } );

		await waitFor( () =>
			expect( checkEligibility ).toHaveBeenCalledTimes( 2 )
		);
		expect( result.current.sessions ).toBe( firstSessions );
		expect( createSession ).toHaveBeenCalledTimes( 1 );
	} );

	test( 'rebuilds sessions when the eligible set changes', async () => {
		checkEligibility
			.mockResolvedValueOnce( eligibility() )
			.mockResolvedValueOnce(
				eligibility( {
					paylater: true,
					payLaterDetails: { productCode: 'PAYLATER' },
				} )
			);

		const { result, rerender } = renderHook(
			( { amount } ) => useV6Sdk( config, 'checkout-block', amount ),
			{ initialProps: { amount: '10.00' } }
		);

		await waitFor( () => expect( result.current.sessions ).not.toBeNull() );
		const firstSessions = result.current.sessions;

		rerender( { amount: '200.00' } );

		await waitFor( () =>
			expect( result.current.sessions.map.paylater ).toBeDefined()
		);
		expect( result.current.sessions ).not.toBe( firstSessions );
	} );

	test( 'exposes the error when loading fails', async () => {
		loadSdkV6.mockRejectedValue( new Error( 'boom' ) );

		const { result } = renderHook( () =>
			useV6Sdk( config, 'checkout-block', '10.00' )
		);

		await waitFor( () => expect( result.current.error ).not.toBeNull() );
		expect( result.current.error.message ).toBe( 'boom' );
	} );
} );
