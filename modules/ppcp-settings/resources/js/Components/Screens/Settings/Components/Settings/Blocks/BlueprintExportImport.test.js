import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

import BlueprintExportImport from './BlueprintExportImport';

const mockExportBlueprint = jest.fn();
const mockUseMerchant = jest.fn();
const mockData = jest.fn();

jest.mock( '@ppcp-settings/data', () => ( {
	CommonHooks: {
		useMerchant: () => mockUseMerchant(),
	},
} ) );

jest.mock( '../../../../../../utils/data', () => ( {
	__esModule: true,
	default: () => mockData(),
} ) );

jest.mock( '../../../../../../hooks/useBlueprintExport', () => ( {
	useBlueprintExport: () => ( {
		exportBlueprint: mockExportBlueprint,
		isExporting: false,
	} ),
} ) );

// Render the action buttons flat so the Export button is clickable.
jest.mock(
	'../../../../../ReusableComponents/SettingsBlocks/FeatureSettingsBlock',
	() => ( {
		__esModule: true,
		default: ( { actionProps } ) => (
			<div>
				{ actionProps.buttons.map( ( button ) => (
					<button key={ button.text } onClick={ button.onClick }>
						{ button.text }
					</button>
				) ) }
			</div>
		),
	} )
);

jest.mock( '../Parts/BlueprintExportModal', () => ( {
	__esModule: true,
	default: () => <div data-testid="export-modal" />,
} ) );

const clickExport = () =>
	fireEvent.click( screen.getByRole( 'button', { name: 'Export' } ) );

describe( 'BlueprintExportImport', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockData.mockReturnValue( {
			blueprint: { isActive: true, importUrl: 'http://example.test' },
		} );
		mockUseMerchant.mockReturnValue( { isConnected: true } );
	} );

	it( 'asks for confirmation before exporting while connected', () => {
		render( <BlueprintExportImport /> );

		clickExport();

		expect( screen.getByTestId( 'export-modal' ) ).toBeInTheDocument();
		expect( mockExportBlueprint ).not.toHaveBeenCalled();
	} );

	it( 'exports straight away while disconnected, with no credentials to opt into', () => {
		mockUseMerchant.mockReturnValue( { isConnected: false } );

		render( <BlueprintExportImport /> );

		clickExport();

		expect( screen.queryByTestId( 'export-modal' ) ).not.toBeInTheDocument();
		expect( mockExportBlueprint ).toHaveBeenCalledWith();
	} );

	it( 'renders nothing when the Blueprint feature is disabled', () => {
		mockData.mockReturnValue( { blueprint: { isActive: false } } );

		const { container } = render( <BlueprintExportImport /> );

		expect( container ).toBeEmptyDOMElement();
	} );
} );
