/**
 * External dependencies
 */
import { WooCommerceAdminPage } from '@inpsyde/playwright-utils/build'; // TODO: fix export in pw-utils

export class PcpSettingsPage extends WooCommerceAdminPage {
	// Locators
	enableGatewayCheckbox = () =>
		this.page.getByLabel( 'Enable/Disable', { exact: true } );

	selectBox = ( label ) =>
		this.page
			.locator( 'tr', {
				has: this.page.getByRole( 'rowheader', {
					name: label,
					exact: true,
				} ),
			} )
			.getByRole( 'list' );

	selectBoxChosenItem = ( label, itemName ) =>
		this.selectBox( label ).getByRole( 'listitem', {
			name: itemName,
			exact: true,
		} );
	selectBoxItemRemoveButton = ( label, itemName ) =>
		this.selectBoxChosenItem( label, itemName ).locator(
			'.select2-selection__choice__remove'
		);
	selectBoxDropdown = () => this.page.locator( '.select2-results__options' );
	selectBoxDropdownItem = ( itemName ) =>
		this.page
			.locator( '.select2-results__option' )
			.getByText( itemName, { exact: true } );

	// Select box actions
	// Select box = input field like 'Pay Later Button Locations' - a mix ot textarea and combobox

	/**
	 * (Re)opens dropdown of select box
	 *
	 * @param boxLabel name of table row where select box is located
	 */
	openSelectBoxDropdown = async ( boxLabel ) => {
		const selectBox = this.selectBox( boxLabel );

		// click on 1,1 position to avoid hitting chosen items
		await selectBox.click( { position: { x: 1, y: 1 } } );

		// click again if select box dropdown was closed
		if ( ! ( await this.selectBoxDropdown().isVisible() ) ) {
			await selectBox.click( { position: { x: 1, y: 1 } } );
		}
	};

	/**
	 * Closes select box dropdown (before clicking elsewhere)
	 *
	 * @param boxLabel name of table row where select box is located
	 */
	closeSelectBoxDropdown = async ( boxLabel ) => {
		const selectBox = this.selectBox( boxLabel );

		// click on 1,1 position to avoid hitting chosen items
		await selectBox.click( { position: { x: 1, y: 1 } } );

		// click again if select box dropdown was closed
		if ( await this.selectBoxDropdown().isVisible() ) {
			await selectBox.click( { position: { x: 1, y: 1 } } );
		}
	};

	/**
	 * Removes chosen items from select box
	 * Select box - input field like 'Pay Later Button Locations' - a mix ot textarea and combobox
	 *
	 * @param boxLabel  - name of table row where select box is located
	 * @param itemNames - visible names of chosen items
	 */
	removeItemsFromSelectBox = async ( boxLabel, itemNames ) => {
		itemNames = ! Array.isArray( itemNames ) ? [ itemNames ] : itemNames;

		for ( const name of itemNames ) {
			const removeButton = this.selectBoxItemRemoveButton(
				boxLabel,
				name
			);
			if ( await removeButton.isVisible() ) {
				await removeButton.click();
			}
		}
		// Make sure select box dropdown is closed before clicking elsewhere
		await this.closeSelectBoxDropdown( boxLabel );
	};

	/**
	 * Adds (unchosen) items to select box
	 * Select box - input field like 'Pay Later Button Locations' - a mix ot textarea and combobox
	 *
	 * @param boxLabel  - name of table row where select box is located
	 * @param itemNames - visible names of items from dropdown list
	 */
	addItemsToSelectBox = async ( boxLabel, itemNames ) => {
		itemNames = ! Array.isArray( itemNames ) ? [ itemNames ] : itemNames;
		for ( const name of itemNames ) {
			// Make sure select box dropdown is opened
			await this.openSelectBoxDropdown( boxLabel );

			if (
				! ( await this.selectBoxChosenItem(
					boxLabel,
					name
				).isVisible() )
			) {
				await this.selectBoxDropdownItem( name ).click();
			}
		}
		// Make sure select box dropdown is closed before clicking elsewhere
		await this.closeSelectBoxDropdown( boxLabel );
	};
}
