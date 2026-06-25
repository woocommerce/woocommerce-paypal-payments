/**
 * External dependencies
 */
import { expect, BrowserContext, Page } from '@playwright/test';

/**
 * Handles the Google Pay TEST-environment popup.
 *
 * Flow:
 *  1. Clicking the Google Pay button opens a popup at pay.google.com/gp/p/loading.
 *  2. Google redirects to accounts.google.com for sign-in (fresh contexts have no session).
 *  3. After sign-in, intermediate consent / recovery pages may appear.
 *  4. The payment sheet at pay.google.com/gp/p/ui/pay shows a confirm button.
 *
 * Credentials are read from GOOGLE_PAY_EMAIL / GOOGLE_PAY_PASSWORD env vars.
 */
export class GooglePayPopup {
	page: Page;

	constructor( page: Page ) {
		this.page = page;
	}

	// -------------------------------------------------------------------------
	// Locators — Google Sign-in (accounts.google.com)
	// -------------------------------------------------------------------------

	emailInput = () => this.page
		.locator( 'input[type="email"]' )
		.or(
			this.page.getByRole( 'textbox', { name: 'Email or phone' } )
		);

	passwordInput = () =>
		this.page
			.locator(
				'input[type="password"]:not([aria-hidden="true"]):not([tabindex="-1"])'
			)
			.or( this.page.locator( 'input[type="password"][name="Passwd"]' ) )
			.first();

	nextButton = () =>
		this.page
			.getByRole( 'button', { name: 'Next' } )
			.or(
				this.page
					.locator( '[jsname="LgbsSe"]' )
					.filter( { hasText: /Next/i } )
			)
			.first();

	private postLoginButton = () =>
		this.page
			.getByRole( 'button', {
				name: /^(Continue|I agree|Confirm|Not now|Skip|Yes|Got it)$/i,
			} )
			.first();
	private buyflowFrame = () =>
		this.page.frameLocator( 'iframe[src*="buyflow2"]' );

	confirmButton = () =>
		this.buyflowFrame()
			.getByRole( 'button', {
				name: /^(Continue|Pay now|Pay|Confirm)$/i,
			} );
			
	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

	/**
	 * Registers init scripts on the Playwright browser context that prevent
	 * Google from detecting the automated browser and blocking sign-in.
	 * Call this in beforeEach, before any page navigation.
	 * @param context
	 */
	static applyBrowserPatches = async ( context: BrowserContext ) => {
		await context.addInitScript( () => {
			// Google Pay requires a secure context. On a local http:// dev site it
			// throws DEVELOPER_ERROR without this patch.
			try {
				Object.defineProperty( window, 'isSecureContext', {
					get: () => true,
					configurable: true,
				} );
			} catch {}

			// Chrome's Payment Handler API intercepts loadPaymentData() and opens a
			// native payment sheet Playwright cannot capture as a popup event.
			// Removing PaymentRequest forces the Google Pay SDK into window.open() mode.
			try {
				// @ts-ignore — intentional: force Google Pay into popup mode
				delete window.PaymentRequest;
			} catch {
				try {
					// @ts-ignore
					window.PaymentRequest = undefined;
				} catch {}
			}

			// Headless Chrome lacks window.chrome. Google sign-in detects this and
			// shows "This browser or app may not be secure." A minimal stub fixes it.
			try {
				if ( ! ( window as any ).chrome ) {
					Object.defineProperty( window, 'chrome', {
						value: {
							runtime: {
								onMessage: {
									addListener: () => {},
									removeListener: () => {},
								},
								connect: () => {},
								sendMessage: () => {},
							},
							loadTimes: () => {},
							csi: () => {},
							app: {},
						},
						configurable: true,
						writable: true,
					} );
				}
			} catch {}

			// Headless Chrome reports 0 plugins. A non-empty list looks more like a
			// real browser to Google's risk scoring.
			try {
				Object.defineProperty( navigator, 'plugins', {
					get: () => [ 1, 2, 3, 4, 5 ],
					configurable: true,
				} );
			} catch {}
		} );
	};

	private signInToGoogle = async () => {
		const email = process.env.GOOGLE_PAY_EMAIL;
		const password = process.env.GOOGLE_PAY_PASSWORD;

		if ( ! email || ! password ) {
			throw new Error(
				'GOOGLE_PAY_EMAIL and GOOGLE_PAY_PASSWORD must be set to run Google Pay tests.'
			);
		}

		await expect(
			this.emailInput(),
			'Assert Google email input is visible'
		).toBeVisible();
		await this.emailInput().fill( email );
		
		await expect(
			this.nextButton(),
			'Assert next button (email) is visible'
		).toBeVisible();
		await this.nextButton().click();

		await expect(
			this.passwordInput(),
			'Assert Google password input is visible'
		).toBeVisible();
		await this.passwordInput().fill( password );
		
		await expect(
			this.nextButton(),
			'Assert next button (password) is visible'
		).toBeVisible();
		await this.nextButton().click();

		await this.page.waitForLoadState();
	};

	/**
	 * Dismisses any pages Google shows between sign-in and the payment sheet:
	 * recovery-options (gds.google.com), consent dialogs, "Continue" / "Not now".
	 */
	private skipPostLoginPrompts = async () => {
		for ( let attempt = 0; attempt < 10; attempt++ ) {
			await this.page.waitForLoadState( 'domcontentloaded' );

			if ( this.page.url().includes( 'pay.google.com' ) ) {
				return;
			}

			// Recovery-options prompt — Cancel follows the `continue` param back.
			if ( this.page.url().includes( 'gds.google.com' ) ) {
				const cancel = this.page.getByRole( 'button', {
					name: 'Cancel',
				} );
				if (
					await cancel
						.isVisible( { timeout: 4_000 } )
						.catch( () => false )
				) {
					await cancel.click();
					continue;
				}
			}

			const btn = this.postLoginButton();
			if (
				await btn.isVisible( { timeout: 3_000 } ).catch( () => false )
			) {
				await btn.click();
				continue;
			}

			await this.page.waitForTimeout( 1_500 );
		}
	};

	/**
	 * Signs in if needed, then confirms payment on the Google Pay sheet.
	 */
	completePayment = async () => {
		await this.signInToGoogle();
		await this.skipPostLoginPrompts();
		await this.page.waitForLoadState();
		await this.tryClickConfirmButton();
	};

	tryClickConfirmButton = async () => {
		await expect(
			this.confirmButton(),
			'Assert Google Pay confirm button is visible'
		).toBeVisible();

		await expect
			.poll(
				async () => {
					if ( this.page.isClosed() ) {
						return true;
					}
					// Swallow click errors caused by the popup tearing down
					// mid-click; the popup closing is the success signal and is
					// detected on the next poll via isClosed(). A genuinely broken
					// selector surfaces as a poll timeout, since the page never closes.
					await this.confirmButton().click().catch( () => {} );
					return this.page.isClosed();
				},
				{
					message:
						'Payment was not confirmed: the Google Pay popup did not close within the timeout',
					timeout: 30_000,
					intervals: [ 3000 ],
				}
			)
			.toBe( true );
	};
}
