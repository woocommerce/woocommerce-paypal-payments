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
	// Browser-level patches — call once on the context before the test runs
	// -------------------------------------------------------------------------

	/**
	 * Registers init scripts on the Playwright browser context that prevent
	 * Google from detecting the automated browser and blocking sign-in.
	 * Call this in beforeEach, before any page navigation.
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
								onMessage: { addListener: () => {}, removeListener: () => {} },
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

	// -------------------------------------------------------------------------
	// Locators — Google Sign-in (accounts.google.com)
	// -------------------------------------------------------------------------

	emailInput = () => this.page.locator( 'input[type="email"]' );

	passwordInput = () =>
		this.page
			.locator( 'input[type="password"]:not([aria-hidden="true"]):not([tabindex="-1"])' )
			.or( this.page.locator( 'input[type="password"][name="Passwd"]' ) )
			.first();

	/** "Next" button shared by the email step and the password step. */
	nextButton = () =>
		this.page
			.getByRole( 'button', { name: 'Next' } )
			.or( this.page.locator( '[jsname="LgbsSe"]' ).filter( { hasText: /Next/i } ) )
			.first();

	private postLoginButton = () =>
		this.page
			.getByRole( 'button', { name: /^(Continue|I agree|Confirm|Not now|Skip|Yes|Got it)$/i } )
			.first();

	// -------------------------------------------------------------------------
	// Locators — Google Pay payment sheet (pay.google.com)
	// -------------------------------------------------------------------------

	/** The confirm button lives inside the cross-origin buyflow2 iframe. */
	private buyflowFrame = () => this.page.frameLocator( 'iframe[src*="buyflow2"]' );

	confirmButton = () =>
		this.buyflowFrame()
			.getByRole( 'button', { name: /^(Continue|Pay now|Pay|Confirm)$/i } )
			.or( this.buyflowFrame().locator( 'button[jsname="LgbsSe"]' ).last() );

	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

	private waitForContent = async () => {
		// The popup can sit at about:blank briefly while the SDK prepares the redirect.
		await this.page
			.waitForURL( ( url ) => url.href !== 'about:blank', { timeout: 15_000 } )
			.catch( () => {} );
		await this.page.waitForLoadState( 'domcontentloaded' ).catch( () => {} );

		// In headed mode the popup occasionally loads blank on the first attempt —
		// one reload is usually enough to get the real content.
		for ( let attempt = 0; attempt < 2; attempt++ ) {
			const hasContent = await this.page
				.locator( 'input, button, iframe' )
				.first()
				.isVisible()
				.catch( () => false );
			if ( hasContent ) return;
			await this.page.reload( { waitUntil: 'domcontentloaded' } );
			await this.page.waitForTimeout( 1_000 );
		}

		await expect(
			this.page.locator( 'body' ),
			'Google Pay popup stayed blank after retries'
		).not.toHaveText( /^\s*$/, { timeout: 10_000 } );
	};

	private signInToGoogle = async () => {
		const email = process.env.GOOGLE_PAY_EMAIL;
		const password = process.env.GOOGLE_PAY_PASSWORD;

		if ( ! email || ! password ) {
			throw new Error(
				'GOOGLE_PAY_EMAIL and GOOGLE_PAY_PASSWORD must be set to run Google Pay tests.'
			);
		}

		await expect( this.emailInput(), 'Google email input is visible' )
			.toBeVisible( { timeout: 15_000 } );
		await this.emailInput().fill( email );
		await this.nextButton().click();

		await expect( this.passwordInput(), 'Google password input is visible' )
			.toBeVisible( { timeout: 15_000 } );
		await this.passwordInput().fill( password );
		await this.nextButton().click();

		// Wait until we've left the sign-in domain entirely.
		await this.page.waitForURL(
			( url ) => ! url.hostname.includes( 'accounts.google.com' ),
			{ timeout: 30_000 }
		);
		await this.page.waitForLoadState();
	};

	/**
	 * Dismisses any pages Google shows between sign-in and the payment sheet:
	 * recovery-options (gds.google.com), consent dialogs, "Continue" / "Not now".
	 */
	private skipPostLoginPrompts = async () => {
		for ( let attempt = 0; attempt < 10; attempt++ ) {
			await this.page.waitForLoadState( 'domcontentloaded' );

			if ( this.page.url().includes( 'pay.google.com' ) ) return;

			// Recovery-options prompt — Cancel follows the `continue` param back.
			if ( this.page.url().includes( 'gds.google.com' ) ) {
				const cancel = this.page.getByRole( 'button', { name: 'Cancel' } );
				if ( await cancel.isVisible( { timeout: 4_000 } ).catch( () => false ) ) {
					await cancel.click();
					continue;
				}
			}

			const btn = this.postLoginButton();
			if ( await btn.isVisible( { timeout: 3_000 } ).catch( () => false ) ) {
				await btn.click();
				continue;
			}

			await this.page.waitForTimeout( 1_500 );
		}
	};

	/** Signs in if needed, then confirms payment on the Google Pay sheet. */
	completePayment = async () => {
		await this.waitForContent();

		if ( this.page.url().includes( 'accounts.google.com' ) ) {
			await this.signInToGoogle();
			await this.skipPostLoginPrompts();
		}

		await this.page.waitForURL(
			( url ) => url.hostname.includes( 'pay.google.com' ),
			{ timeout: 60_000 }
		);
		await this.page.waitForLoadState();

		await expect( this.confirmButton(), 'Google Pay confirm button is visible' )
			.toBeVisible( { timeout: 30_000 } );

		await Promise.all( [
			this.page.waitForEvent( 'close' ),
			this.confirmButton().click(),
		] );
	};
}
