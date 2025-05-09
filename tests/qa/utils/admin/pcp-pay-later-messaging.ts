/**
 * Internal dependencies
 */
import { expect } from '../test';
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';
import { Pcp } from '../../resources';

export class PcpPayLaterMessaging extends PcpAdminPage {
	url = urls.admin.pcp.payLaterMessaging;

	// Locators
	configContainer = () => this.page.locator( '#messaging-configurator' );
	accordionButton = ( location: Pcp.Admin.Plm.Location ) =>
		this.page.getByRole( 'button', { name: location } );
	accordionButtonSvgPath = ( location: Pcp.Admin.Plm.Location ) =>
		this.accordionButton( location ).locator( 'svg path' );
	accordionContainer = ( location: Pcp.Admin.Plm.Location ) =>
		this.page
			.locator( 'div[accordionname="message-configs"]' )
			.filter( { has: this.accordionButton( location ) } );
	accordionCheckbox = ( location: Pcp.Admin.Plm.Location ) =>
		this.accordionContainer( location ).locator(
			'div[data-id="checkbox"] label[for^="Checkbox"]'
		);
	accordionCheckboxSvg = ( location: Pcp.Admin.Plm.Location ) =>
		this.accordionCheckbox( location ).locator( 'svg' );

	logoTypeCombobox = () =>
		this.page.locator(
			'button[aria-labelledby^="dropdownMenuButton_Logotype"]'
		);
	textColorCombobox = () =>
		this.page.locator(
			'button[aria-labelledby^="dropdownMenuButton_Textcolor"]'
		);
	logoPositionCombobox = () =>
		this.page.locator(
			'button[aria-labelledby^="dropdownMenuButton_Logoposition"]'
		);
	textSizeCombobox = () =>
		this.page.locator(
			'button[aria-labelledby^="dropdownMenuButton_Textsize"]'
		);
	bannerColorCombobox = () =>
		this.page.locator(
			'button[aria-labelledby^="dropdownMenuButton_Bannercolor"]'
		);
	bannerSizeCombobox = () =>
		this.page.locator(
			'button[aria-labelledby^="dropdownMenuButton_Bannersize"]'
		);
	comboboxOption = ( optionName: string ) =>
		this.page
			.locator( 'li[id^="smenu_item_"]' )
			.filter( { hasText: optionName } );
	previewContainer = () =>
		this.page.locator( '#configurator-previewSectionContainer' );
	previewIframe = () =>
		this.previewContainer().locator( 'iframe' ).first().contentFrame();
	previewIframeSpan = () =>
		this.previewIframe().locator( '[id^="zoid-paypal-message-uid"]' );
	previewMessageIframe = () =>
		this.previewIframeSpan().frameLocator(
			'iframe[name^="__zoid__paypal_message__"]'
		);
	previewMessageTextPart = () =>
		this.previewMessageIframe().getByText(
			'Pay in 4 interest-free payments'
		);
	previewTextButton = () =>
		this.page.locator(
			'svg path[d="M5 5a1 1 0 0 0 0 2h14a1 1 0 1 0 0-2H5zm-1 7a1 1 0 0 1 1-1h14a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1zm0 6a1 1 0 0 1 1-1h14a1 1 0 1 1 0 2H5a1 1 0 0 1-1-1z"]'
		);
	previewDesktopButton = () =>
		this.page.locator(
			'svg path[d="M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-6v2h2.25a.75.75 0 0 1 0 1.5h-6.5a.75.75 0 0 1 0-1.5H11v-2H5a1 1 0 0 1-1-1V5zm2 9.25a.75.75 0 0 1 .75-.75h10.5a.75.75 0 0 1 0 1.5H6.75a.75.75 0 0 1-.75-.75z"]'
		);
	previewMobileButton = () =>
		this.page.locator(
			'svg path[d="M6 2a1 1 0 0 0-1 1v18a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H6zm6 18a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"]'
		);
	darkModeToggle = () =>
		this.page.locator(
			'label[for="configurator-previewSectionDarkModeToggle"]'
		);

	// Actions

	/**
	 * Expands section of the accordion by provided location.
	 * Simply clicking on a button doesn't work because there's no way to tell if section is expanded.
	 * Unfortunately there's no other way to differentiate between collapsed and expanded section except svg path value.
	 *
	 * @param {Pcp.Admin.Plm.Location} location
	 */
	expandAccordionSection = async ( location: Pcp.Admin.Plm.Location ) => {
		// Get the current path data
		const pathData = await this.accordionButtonSvgPath(
			location
		).getAttribute( 'd' );

		// Expected values (replace with correct ones if needed)
		const collapsedPath =
			'M4.292 8.293a1 1 0 0 1 1.414 0L12 14.586l6.293-6.293a1 1 0 0 1 1.414 1.414L12.713 16.7a1.01 1.01 0 0 1-1.428 0L4.292 9.707a1 1 0 0 1 0-1.414z';
		const expandedPath =
			'M4.293 15.703a1 1 0 0 0 1.414 0L12 9.41l6.293 6.293a1 1 0 0 0 1.414-1.414l-6.993-6.993a1.01 1.01 0 0 0-1.428 0l-6.993 6.993a1 1 0 0 0 0 1.414z';

		if ( pathData === collapsedPath ) {
			await this.accordionButton( location ).click();
			await expect(
				this.accordionButtonSvgPath( location )
			).toHaveAttribute( 'd', expandedPath );
		}
	};

	/**
	 * Checks if messaging for location is enabled.
	 *
	 * @param location
	 */
	isMessagingForLocationEnabled = async (
		location: Pcp.Admin.Plm.Location
	) => {
		// Get the current 'color' property of SVG
		const color = await this.accordionCheckboxSvg( location ).evaluate(
			( el ) => getComputedStyle( el ).color
		);
		// Expected colors
		const checkedColor = 'rgb(255, 255, 255)'; // White (checked)
		const isEnabled = color === checkedColor;
		return isEnabled;
	};

	/**
	 * Enables/disabled messaging for location.
	 *
	 * @param location
	 * @param isChecked
	 */
	setMessagingCheckboxStateForLocation = async (
		location: Pcp.Admin.Plm.Location,
		isChecked: boolean
	) => {
		const isEnabled = await this.isMessagingForLocationEnabled( location );
		if ( isEnabled !== isChecked ) {
			await this.accordionCheckbox( location ).click();
		}
	};

	/**
	 * Enables messaging for location.
	 *
	 * @param location
	 */
	enableMessagingForLocation = async ( location: Pcp.Admin.Plm.Location ) =>
		this.setMessagingCheckboxStateForLocation( location, true );

	/**
	 * Disables messaging for location.
	 *
	 * @param location
	 */
	disableMessagingForLocation = async ( location: Pcp.Admin.Plm.Location ) =>
		this.setMessagingCheckboxStateForLocation( location, false );

	/**
	 * Toggles dark mode on/off.
	 *
	 * @param isEnabled
	 */
	setDarkModeToggleState = async ( isEnabled: boolean ) => {
		const toggle = this.darkModeToggle();
		// Get the current 'color' property
		const color = await toggle.evaluate(
			( el ) => getComputedStyle( el ).borderBottomColor
		);
		// Expected colors
		const uncheckedColor = 'rgb(146, 148, 150)'; // White (unchecked)
		const isChecked = color !== uncheckedColor;
		if ( isChecked !== isEnabled ) {
			await toggle.click();
		}
	};

	/**
	 * Toggles dark mode on.
	 */
	enableDarkModeToggle = async () => this.setDarkModeToggleState( true );

	/**
	 * Toggles dark mode off.
	 */
	disableDarkModeToggle = async () => this.setDarkModeToggleState( false );

	/**
	 * Sets combobox settings for location
	 *
	 * @param location
	 * @param settings
	 * @param settings.logoType
	 * @param settings.textColor
	 * @param settings.logoPosition
	 * @param settings.textSize
	 * @param settings.bannerColor
	 * @param settings.bannerSize
	 */
	updateLocationSettings = async (
		location: Pcp.Admin.Plm.Location,
		settings: {
			logoType?: Pcp.Admin.Plm.LogoType;
			textColor?: Pcp.Admin.Plm.TextColor;
			logoPosition?: Pcp.Admin.Plm.LogoPosition;
			textSize?: Pcp.Admin.Plm.TextSize;
			bannerColor?: Pcp.Admin.Plm.BannerColor;
			bannerSize?: Pcp.Admin.Plm.BannerSize;
		}
	) => {
		const {
			logoType,
			textColor,
			logoPosition,
			textSize,
			bannerColor,
			bannerSize,
		} = settings;

		await this.expandAccordionSection( location );

		if ( logoType ) {
			await this.logoTypeCombobox().click();
			await this.comboboxOption( logoType ).click();
		}

		if ( textColor ) {
			await this.textColorCombobox().click();
			await this.comboboxOption( textColor ).click();
		}

		if ( logoPosition && logoType === 'Full Logo' ) {
			await this.logoPositionCombobox().click();
			await this.comboboxOption( logoPosition ).click();
		}

		if ( textSize ) {
			await this.textSizeCombobox().click();
			await this.comboboxOption( textSize ).click();
		}

		if ( bannerColor ) {
			await this.bannerColorCombobox().click();
			await this.comboboxOption( bannerColor ).click();
		}

		if ( bannerSize ) {
			await this.bannerSizeCombobox().click();
			await this.comboboxOption( bannerSize ).click();
		}
	};

	// Assertions

	/**
	 * Softly asserts combobox settings for location
	 *
	 * @param settings
	 * @param settings.logoType
	 * @param settings.textColor
	 * @param settings.logoPosition
	 * @param settings.textSize
	 * @param settings.bannerColor
	 * @param settings.bannerSize
	 */
	assertLocationSettings = async ( settings: {
		logoType?: Pcp.Admin.Plm.LogoType;
		textColor?: Pcp.Admin.Plm.TextColor;
		logoPosition?: Pcp.Admin.Plm.LogoPosition;
		textSize?: Pcp.Admin.Plm.TextSize;
		bannerColor?: Pcp.Admin.Plm.BannerColor;
		bannerSize?: Pcp.Admin.Plm.BannerSize;
	} ) => {
		const {
			logoType,
			textColor,
			logoPosition,
			textSize,
			bannerColor,
			bannerSize,
		} = settings;

		if ( logoType ) {
			await expect( this.logoTypeCombobox() ).toHaveText( logoType );
		}

		if ( textColor ) {
			await expect( this.textColorCombobox() ).toHaveText( textColor );
		}

		if ( logoPosition && logoType === 'Full Logo' ) {
			await expect( this.logoPositionCombobox() ).toHaveText(
				logoPosition
			);
		}

		if ( textSize ) {
			await expect( this.textSizeCombobox() ).toHaveText( textSize );
		}

		if ( bannerColor ) {
			await expect( this.bannerColorCombobox() ).toHaveText(
				bannerColor
			);
		}

		if ( bannerSize ) {
			await expect( this.bannerSizeCombobox() ).toHaveText( bannerSize );
		}
	};

	/**
	 * Compares actual configurator screenshot to expected.
	 *
	 * @param snapshotName
	 */
	snapshotPlmConfigurator = async ( snapshotName: string ) => {
		// Assert message is displayed
		await expect( this.previewMessageTextPart() ).toBeVisible();
		// Screenshot configurator
		this.snapshotLocator( this.configContainer(), snapshotName );
	};
}
