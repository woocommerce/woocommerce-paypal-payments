/**
 * Internal dependencies
 */
import { expect, test } from '../../utils';
import { merchants, storeConfigDefault } from '../../resources';

test.beforeAll( async ( { utils, pcpApi } ) => {
	await utils.configureStore( storeConfigDefault );
	await utils.installAndActivatePcp();
	await pcpApi.resetDb();
	await pcpApi.connectMerchant(
		merchants.usa.client_id,
		merchants.usa.client_secret
	);
} );

test( 'PCP-6234 | Settings - Overview - Features card shows items with Active badges @Critical', async ( {
	pcpOverview,
} ) => {
	await pcpOverview.visit();
	await pcpOverview.waitForOverview();

	await test.step( 'Assert Features card title and description', async () => {
		await expect(
			pcpOverview.featuresCard(),
			'Assert Features card is visible'
		).toBeVisible();

		await expect(
			pcpOverview.featuresCardTitle(),
			'Assert Features card title reads "Features"'
		).toContainText( 'Features' );

		await expect(
			pcpOverview.featuresCardDescription(),
			'Assert Features description mentions enabling features'
		).toContainText( 'Enable additional features' );

		await expect(
			pcpOverview.featuresCardDescription(),
			'Assert Features description mentions Refresh action'
		).toContainText( 'Refresh' );
	} );

	await test.step( 'Assert feature items are rendered with Active badges for enabled features', async () => {
		const featureCount = await pcpOverview.featureItems().count();
		expect(
			featureCount,
			'Assert at least one feature item is visible'
		).toBeGreaterThan( 0 );

		const activeCount = await pcpOverview.activeFeatureBadges().count();
		expect
			.soft(
				activeCount,
				'Assert at least one feature shows an Active badge'
			)
			.toBeGreaterThan( 0 );
	} );

	await test.step( 'Assert Refresh button is visible in Features card', async () => {
		await expect(
			pcpOverview.refreshButton(),
			'Assert Refresh button is visible'
		).toBeVisible();
	} );
} );

test( 'PCP-6233 | Settings - Overview - Features Refresh button triggers success notice @Critical', async ( {
	pcpOverview,
} ) => {
	await pcpOverview.visit();
	await pcpOverview.waitForOverview();

	await expect(
		pcpOverview.refreshButton(),
		'Assert Refresh button is visible before click'
	).toBeVisible();

	await pcpOverview.refreshButton().click();

	await expect(
		pcpOverview.successNotice( 'Features refreshed successfully.' ),
		'Assert success notice "Features refreshed successfully." appears'
	).toBeAttached( { timeout: 15_000 } );
} );

test( 'PCP-6232 | Settings - Overview - Feature Configure buttons navigate to correct tabs @Critical', async ( {
	pcpOverview,
} ) => {
	await pcpOverview.visit();
	await pcpOverview.waitForOverview();

	await test.step( 'ACDC Configure navigates to Payment Methods tab', async () => {
		// "Configure" buttons render as <button>
		const acdcConfigure = pcpOverview.configureButtonFor(
			'Advanced Credit and Debit Cards'
		);

		await expect(
			acdcConfigure,
			'Assert ACDC Configure button is visible'
		).toBeVisible();

		await acdcConfigure.click();

		await expect(
			pcpOverview.paymentMethodsTab(),
			'Assert Payment Methods tab is selected after clicking ACDC Configure'
		).toHaveAttribute( 'aria-selected', 'true', {
			timeout: 5_000,
		} );
	} );

	await test.step( 'Save PayPal and Venmo Configure navigates to Settings tab', async () => {
		await pcpOverview.overviewTab().click();
		await pcpOverview
			.featuresCard()
			.waitFor( { state: 'visible', timeout: 10_000 } );

		const spvConfigure = pcpOverview.configureButtonFor(
			'Save PayPal and Venmo'
		);

		await expect(
			spvConfigure,
			'Assert Save PayPal and Venmo Configure button is visible'
		).toBeVisible();

		await spvConfigure.click();

		await expect(
			pcpOverview.settingsTab(),
			'Assert Settings tab is selected after clicking Save PayPal and Venmo Configure'
		).toHaveAttribute( 'aria-selected', 'true', {
			timeout: 5_000,
		} );
	} );
} );
