/**
 * Internal dependencies
 */
import { PcpAdminPage } from './pcp-admin-page';
import urls from '../urls';

export class PcpOverview extends PcpAdminPage {
	url = urls.admin.pcp.overview;

	// Locators — container
	overviewContainer = () => this.page.locator( '.ppcp-r-tab-overview' );

	// Locators — Todos section
	todosCard = () => this.page.locator( '.ppcp-r-tab-overview-todo' );
	todosCardTitle = () =>
		this.todosCard().locator( '.ppcp-r-settings-card__title' );
	todosCardDescription = () =>
		this.todosCard().locator( '.ppcp-r-settings-card__description' );
	todoItems = () => this.todosCard().locator( '.ppcp-r-todo-item' );
	todoItemTitle = () =>
		this.todosCard().locator( '.ppcp-r-todo-item__description' );
	todoDismissButtons = () =>
		this.todosCard().locator( 'button.ppcp-r-todo-item__dismiss' );
	restoreButton = () =>
		this.todosCard().getByRole( 'button', {
			name: 'Restore dismissed Things To Do',
		} );

	// Locators — Features section
	featuresCard = () => this.page.locator( '.ppcp-r-tab-overview-features' );
	featuresCardTitle = () =>
		this.featuresCard().locator( '.ppcp-r-settings-card__title' );
	featuresCardDescription = () =>
		this.featuresCard().locator( '.ppcp-r-settings-card__description' );
	featureItems = () =>
		this.featuresCard().locator( '.ppcp-r-settings-block__feature' );
	activeFeatureBadges = () =>
		this.featuresCard().locator( '.ppcp-r-title-badge--positive' );
	refreshButton = () =>
		this.featuresCard().getByRole( 'button', { name: /Refresh/ } );
	configureButtonFor = ( featureName: string ) =>
		this.featureItems()
			.filter( { hasText: featureName } )
			.getByRole( 'button', { name: 'Configure' } );

	// Locators — Notices (WordPress snackbar rendered by WooCommerce admin layout)
	successNotice = ( text: string ) =>
		this.page.locator( '.components-snackbar' ).filter( { hasText: text } );

	// Actions

	/**
	 * Waits for the overview container to be visible after load.
	 * Reloads once if the container does not appear within 3s
	 */
	waitForOverview = async () => {
		await this.waitForLoadingMaskRemoved();
		const isContainerVisible = await this.overviewContainer()
			.waitFor( { state: 'visible', timeout: 3_000 } )
			.then( () => true )
			.catch( () => false );
		if ( ! isContainerVisible ) {
			await this.page.reload();
			await this.waitForLoadingMaskRemoved();
		}
		await this.overviewContainer().waitFor( {
			state: 'visible',
			timeout: 20_000,
		} );
	};
}
