/**
 * Internal dependencies
 */
import { test, expect } from '../../utils';
import { pcpConfigDefault, storeConfigDefault } from '../../resources';

test.describe( 'Connection', () => {
	test.beforeAll( async ( { utils } ) => {
		await utils.configureStore( storeConfigDefault );
		await utils.configurePcp( pcpConfigDefault );
	} );

	test( 'PCP-1018 | Connection - Disconnect Account button @Critical', async ( {
		connection,
	} ) => {
		await connection.visit();
		const disconnectButton = connection.disconnectAccountButton();
		await expect( disconnectButton ).toBeVisible();
		await disconnectButton.click();
		await expect( connection.page ).toHaveURL( connection.url );
		await expect(
			connection.toggleToManualCredentialInputButton()
		).toBeVisible();
	} );

	test.describe( 'Connected merchant', () => {
		test.beforeAll( async ( { pcpApi } ) => {
			await pcpApi.connectMerchant( pcpConfigDefault.merchant );
		} );

		test( 'PCP-1019 | Advanced Credit and Debit Card Payments status is enabled button Settings is also enabled @Critical', async ( {
			connection,
			advancedCardProcessing,
		} ) => {
			await connection.visit();
			await expect( connection.advancedCardPaymentStatus() ).toHaveText(
				'Status: Available'
			);
			await expect(
				connection.advancedCardPaymentSettingsButton()
			).toHaveText( 'Settings' );

			await connection.advancedCardPaymentSettingsButton().click();
			await advancedCardProcessing.assertUrl();
		} );

		test( 'PCP-1029 | Connection - Resubscribe @Critical', async ( {
			connection,
		} ) => {
			await connection.visit();

			const textDataInitial = await connection
				.webhooksList()
				.last()
				.allInnerTexts();
			const resubscribeButton = connection.resubscribeButton();
			const simulateButton = connection.simulateButton();

			await resubscribeButton.click();
			await expect( resubscribeButton ).toBeEnabled();

			await expect( simulateButton ).toBeVisible();
			await expect( simulateButton ).toBeEnabled();

			const textDataResulted = await connection
				.webhooksList()
				.last()
				.allInnerTexts();

			await expect( textDataInitial ).toEqual(
				expect.arrayContaining( textDataResulted )
			);
			await expect(
				connection.page.getByText( 'Failed to load webhooks.' )
			).not.toBeVisible();
		} );

		// Feature removed in v3.3.1:
		// test( 'PCP-1030 | Connection - Simulate button @Critical', async ( {
		// 	connection,
		// } ) => {
		// 	await connection.visit();
		// 	await connection.resubscribeButton().click();
		// 	await connection.page.waitForLoadState( 'networkidle' );

		// 	await expect( connection.simulateButton() ).toBeEnabled();
		// 	await connection.simulateButton().click();
		// 	await expect(
		// 		connection.page.getByText(
		// 			'Waiting for the webhook to arrive...'
		// 		)
		// 	).toBeVisible( { timeout: 1 * 60 * 1000 } );
		// 	await expect(
		// 		connection.page.getByText(
		// 			'The webhook was received successfully.'
		// 		)
		// 	).toBeVisible( { timeout: 1 * 60 * 1000 } );
		// } );

		// //can't restore Advanced Credit and Debit Card Payments status to initial state('Status: Not yet enabled')
		// test.fixme('PCP-1021 | Advanced Credit and Debit Card Payments status is not yet enabled button Settings is also not yet enabled', async ({ utils, connection, advancedCardProcessing, context, page }) => {
		//
		//   await connection.visit();
		//   await expect(connection.advancedCardPaymentStatus()).toHaveText('Status: Not yet enabled');
		//   await expect(connection.advancedCardPaymentSettingsButton()).toHaveText('Enable Advanced Card Payments');
		//   await expect(advancedCardProcessing.tabName()).not.toBeVisible();

		//   const pagePromise = context.waitForEvent('page');
		//   await connection.advancedCardPaymentSettingsButton().click();
		//   const newPage = await pagePromise;
		//   await newPage.waitForLoadState();
		//   await expect(page).toHaveURL(urls.pcp.advancedCardProcessing);
		// });

		// test.fixme('PCP-1468 | Remove PayPal Payments data from Database on uninstall', async ({ utils, connection, requestUtils, plugins }) => {
		//
		//   await connection.visit();
		//   await connection.removeDataOnUninstallCheckbox().check();
		//   await connection.saveChangesButton().click();
		//   await expect(connection.savedChangesMessage()).toBeVisible();
		//   await expect(connection.removeDataOnUninstallCheckbox()).toBeChecked();

		//   await requestUtils.deletePlugin(pcp.slug, true);
		//   // TODO: assert "woocommerce-ppcp-version, woocommerce-ppcp-settings,ppcp-webhook should not be dispalyed"

		//   await plugins.installPluginFromFile(pcp.zipFilePath);
		//   await connection.visit();
		//   // TODO: which default settings to assert?
		// });

		// test.fixme('PCP-1469 | Remove PayPal Payments data from Database Clear Now button', async ({ utils, connection, page }) => {
		//
		//   await connection.clearDB();
		//   await expect(page.getByText('PayPal Payments is almost ready. To get started, connect your account.')).toBeVisible();
		//   // TODO: assert "woocommerce-ppcp-version, woocommerce-ppcp-settings,ppcp-webhook should not be dispalyed"
		// });
	} );
} );
