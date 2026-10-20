import { renderHook, act } from '@testing-library/react';
import apiFetch from '@wordpress/api-fetch';

import {
	useBlueprintExport,
	EXPORTER_ALIAS,
	EXPORTER_ALIAS_WITH_CONNECTION,
} from './useBlueprintExport';

jest.mock( '@wordpress/api-fetch' );

jest.mock( '@wordpress/blob', () => ( {
	downloadBlob: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( {
		createErrorNotice: jest.fn(),
		createSuccessNotice: jest.fn(),
	} ),
} ) );

jest.mock( '@wordpress/notices', () => ( { store: 'core/notices' } ) );

/**
 * Reads the step aliases from the single apiFetch call.
 *
 * @return {string[]} Requested setting step aliases.
 */
const requestedSteps = () => apiFetch.mock.calls[ 0 ][ 0 ].data.steps.settings;

describe( 'useBlueprintExport', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		apiFetch.mockResolvedValue( { data: { steps: [] } } );
	} );

	it( 'requests the credential-free exporter by default', async () => {
		const { result } = renderHook( () => useBlueprintExport() );

		await act( async () => {
			await result.current.exportBlueprint();
		} );

		expect( requestedSteps() ).toContain( EXPORTER_ALIAS );
		expect( requestedSteps() ).not.toContain(
			EXPORTER_ALIAS_WITH_CONNECTION
		);
	} );

	it( 'requests the connection exporter only when explicitly asked', async () => {
		const { result } = renderHook( () => useBlueprintExport() );

		await act( async () => {
			await result.current.exportBlueprint( { includeConnection: true } );
		} );

		expect( requestedSteps() ).toContain( EXPORTER_ALIAS_WITH_CONNECTION );
		expect( requestedSteps() ).not.toContain( EXPORTER_ALIAS );
	} );

	it( 'keeps exporting the payment gateway settings either way', async () => {
		const { result } = renderHook( () => useBlueprintExport() );

		await act( async () => {
			await result.current.exportBlueprint( { includeConnection: true } );
		} );

		expect( requestedSteps() ).toContain( 'wcPaymentGateways' );
	} );

	it( 'clears the busy state after a failed export', async () => {
		apiFetch.mockRejectedValue( new Error( 'nope' ) );

		const { result } = renderHook( () => useBlueprintExport() );

		await act( async () => {
			await result.current.exportBlueprint();
		} );

		expect( result.current.isExporting ).toBe( false );
	} );
} );
