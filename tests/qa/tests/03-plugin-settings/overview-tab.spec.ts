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

test(
	'PCP-6237 | Settings - Overview - Tab loads and all navigation tabs are visible @Critical',
	async ( { pcpOverview } ) => {
		await pcpOverview.visit();
		await pcpOverview.waitForOverview();

		await expect(
			pcpOverview.overviewTab(),
			'Assert Overview tab is visible in navigation'
		).toBeVisible();

		await expect(
			pcpOverview.overviewTab(),
			'Assert Overview tab is the active tab on load'
		).toHaveAttribute( 'aria-selected', 'true' );

		await expect(
			pcpOverview.overviewContainer(),
			'Assert overview container is rendered'
		).toBeVisible();

		// All settings tabs are reachable from the Overview tab
		await expect(
			pcpOverview.paymentMethodsTab(),
			'Assert Payment Methods tab is present'
		).toBeVisible();
		await expect(
			pcpOverview.settingsTab(),
			'Assert Settings tab is present'
		).toBeVisible();
		await expect(
			pcpOverview.stylingTab(),
			'Assert Styling tab is present'
		).toBeVisible();
		await expect(
			pcpOverview.payLaterMessagingTab(),
			'Assert Pay Later Messaging tab is present'
		).toBeVisible();
	}
);

test(
	'PCP-6236 | Settings - Overview - Todos card structure, dismiss, and restore @Critical',
	async ( { pcpOverview } ) => {
		await pcpOverview.visit();
		await pcpOverview.waitForOverview();

		await pcpOverview.todosCard().waitFor( { state: 'visible' } );

		let initialCount = 0;

		await test.step( 'Assert Todos card title, description, and restore button', async () => {
			await expect(
				pcpOverview.todosCardTitle(),
				'Assert Todos card title reads "Things to do next"'
			).toContainText( 'Things to do next' );

			await expect(
				pcpOverview.todosCardDescription(),
				'Assert Todos card description mentions completing tasks'
			).toContainText( 'Complete these tasks' );

			await expect(
				pcpOverview.restoreButton(),
				'Assert Restore button is present in Todos card'
			).toBeVisible();
		} );

		await test.step( 'Assert item count, titles, and dismiss buttons', async () => {
			initialCount = await pcpOverview.todoItems().count();
			expect(
				initialCount,
				'Assert todo items are rendered at the cap of 5'
			).toBe( 5 );

			const firstTitle = await pcpOverview
				.todoItemTitle()
				.first()
				.textContent();
			expect(
				firstTitle?.trim(),
				'Assert first todo item has a non-empty title'
			).toBeTruthy();

			const dismissCount = await pcpOverview
				.todoDismissButtons()
				.count();
			expect(
				dismissCount,
				'Assert every visible todo item has a dismiss button'
			).toBe( initialCount );
		} );

		await test.step(
			'Dismiss first todo item and assert it is removed from the list',
			async () => {
				// Capture the title of the first item so we can assert it is gone
				const firstTitle = (
					await pcpOverview.todoItemTitle().first().textContent()
				)?.trim() ?? '';

				await pcpOverview.todoDismissButtons().first().click();

				await expect(
					pcpOverview
						.todoItems()
						.filter( { hasText: firstTitle } )
						.first(),
					'Assert the dismissed todo item is removed from the list'
				).not.toBeAttached( { timeout: 5_000 } );
			}
		);

		await test.step(
			'Restore dismissed items and assert success notice',
			async () => {
				await pcpOverview.restoreButton().click();
				await expect(
					pcpOverview.successNotice(
						'Dismissed items restored successfully.'
					),
					'Assert success notice "Dismissed items restored successfully." appears'
				).toBeAttached( { timeout: 15_000 } );
			}
		);
	}
);

test(
	'PCP-6235 | Settings - Overview - Clicking a todo item navigates to its associated tab @Critical',
	async ( { pcpOverview } ) => {
		await pcpOverview.visit();
		await pcpOverview.waitForOverview();

		await pcpOverview.todosCard().waitFor( { state: 'visible' } );

		// Target the "Enable Fastlane" todo
		const fastlaneTodo = pcpOverview
			.todoItems()
			.filter( { hasText: 'Enable Fastlane' } )
			.first();

		await expect(
			fastlaneTodo,
			'Assert "Enable Fastlane" todo is present'
		).toBeVisible();

		// Click the title area of the todo item (avoids the dismiss button)
		await fastlaneTodo
			.locator( '.ppcp-r-todo-item__description' )
			.click();

		await expect(
			pcpOverview.paymentMethodsTab(),
			'Assert Payment Methods tab is selected after clicking the Enable Fastlane todo'
		).toHaveAttribute( 'aria-selected', 'true', { timeout: 5_000 } );
	}
);
