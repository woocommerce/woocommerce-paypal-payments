/**
 * External dependencies
 */
import { expect, BrowserContext, Locator, Page } from '@playwright/test';
import { generate } from 'otplib';

/**
 * Handles the Google Pay TEST-environment popup.
 *
 * Flow:
 *  1. Clicking the Google Pay button opens a popup at pay.google.com/gp/p/loading.
 *  2. Google redirects to accounts.google.com for sign-in (fresh contexts have no session).
 *  3. Google may challenge with 2-Step Verification (TOTP code from an authenticator app).
 *  4. After sign-in, intermediate consent / recovery pages may appear.
 *  5. The payment sheet at pay.google.com/gp/p/ui/pay shows a confirm button.
 *
 * Credentials are read from GOOGLE_PAY_EMAIL / GOOGLE_PAY_PASSWORD env vars.
 * The test account must have 2-Step Verification set up via an authenticator app
 * (not SMS) — its secret is read from GOOGLE_PAY_TOTP_SECRET and used to compute
 * the current code locally, instead of waiting on an unautomatable phone prompt.
 */
export class GooglePayPopup {
	page: Page;

	constructor( page: Page ) {
		this.page = page;
	}

	// -------------------------------------------------------------------------
	// Locators — Google Sign-in (accounts.google.com)
	// -------------------------------------------------------------------------

	emailInput = () =>
		this.page
			.locator( 'input[type="email"]' )
			.or( this.page.getByRole( 'textbox', { name: 'Email or phone' } ) );

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

	private totpCodeInput = () =>
		this.page
			.locator( 'input#totpPin' )
			.or( this.page.locator( 'input[name="totpPin"]' ) )
			.or( this.page.getByRole( 'textbox', { name: /enter code/i } ) )
			.first();

	private totpVerifyButton = () =>
		this.page.getByRole( 'button', { name: /^(Next|Verify)$/i } ).first();

	// Google may default to an SMS challenge even when an authenticator app is
	// set up as a secondary method; this switches to the TOTP code prompt.
	private tryAnotherWayButton = () =>
		this.page.getByRole( 'button', { name: 'Try another way' } );

	private authenticatorAppOption = () =>
		this.page
			.getByRole( 'link', { name: /Google Authenticator/i } )
			.or(
				this.page.getByRole( 'button', {
					name: /Google Authenticator/i,
				} )
			)
			.first();

	// Recovery-options prompt (gds.google.com) — Cancel follows the `continue`
	// param back to the payment flow.
	private recoveryCancelButton = () =>
		this.page.getByRole( 'button', { name: 'Cancel' } );

	/**
	 * Every post-login screen a plain click gets past.
	 *
	 * Which one is showing doesn't matter — they all need the same action — so
	 * they're matched as a union rather than probed one at a time. "Cancel" is
	 * only offered on the recovery prompt, since clicking a generic "Cancel" on
	 * an arbitrary Google page is far worse than one wasted pass.
	 */
	private clickablePrompt = () => {
		const base = this.authenticatorAppOption()
			.or( this.tryAnotherWayButton() )
			.or( this.postLoginButton() );

		// .first() because waitFor is strict: a page showing two of these would
		// otherwise throw instead of being advanced.
		return (
			this.page.url().includes( 'gds.google.com' )
				? base.or( this.recoveryCancelButton() )
				: base
		).first();
	};

	private buyflowFrame = () =>
		this.page.frameLocator( 'iframe[src*="buyflow2"]' );

	confirmButton = () =>
		this.buyflowFrame().getByRole( 'button', {
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
	 * Whether `locator` shows up within `timeout`.
	 *
	 * Uses `waitFor` rather than `isVisible`: `isVisible` resolves the selector
	 * and checks the DOM exactly once, so it reports "not there" for anything
	 * that simply hasn't rendered yet — its `timeout` bounds the round-trip,
	 * not how long it waits.
	 *
	 * @param locator
	 * @param timeout
	 */
	private appears = ( locator: Locator, timeout = 4_000 ) =>
		locator
			.waitFor( { state: 'visible', timeout } )
			.then( () => true )
			.catch( () => false );

	/**
	 * Computes the current TOTP code from GOOGLE_PAY_TOTP_SECRET and submits it.
	 */
	private submitTotpCode = async () => {
		const secret = process.env.GOOGLE_PAY_TOTP_SECRET;
		if ( ! secret ) {
			throw new Error(
				'GOOGLE_PAY_TOTP_SECRET must be set to get past Google 2-Step Verification.'
			);
		}

		const codeInput = this.totpCodeInput();
		await codeInput.fill( await generate( { secret } ) ).catch( () => {} );
		await this.totpVerifyButton()
			.click()
			.catch( () => {} );

		// A code is single-use and only valid for its 30-second window, and
		// Google leaves the field on screen while it verifies. Without waiting
		// for it to go away, the next pass re-detects the same field and submits
		// a second code that Google has already consumed — which it rejects,
		// costing the whole challenge. Hence a longer wait than the generic one:
		// this is the screen where guessing wrong is unrecoverable, not just slow.
		await codeInput
			.waitFor( { state: 'hidden', timeout: 20_000 } )
			.catch( () => {} );
	};

	/**
	 * Advances the popup past whichever post-login screen is showing, at most one
	 * screen per call. Returns true once the popup has reached the payment sheet.
	 *
	 * Only one distinction matters here: the 2FA field needs filling, everything
	 * else just needs clicking — so the rest are matched as a union instead of
	 * being probed individually.
	 *
	 * Action errors are swallowed on purpose. These screens are transient, and a
	 * click that loses a race with a navigation is a retry, not a failure. It
	 * also has to hold for `expect.poll`, which runs its generator outside its
	 * own try/catch: a rejection here would abort the poll instead of retrying.
	 */
	private advancePastPrompt = async (): Promise< boolean > => {
		if ( this.page.url().includes( 'pay.google.com' ) ) {
			return true;
		}

		if ( await this.appears( this.totpCodeInput() ) ) {
			await this.submitTotpCode();
			return false;
		}

		const clickable = this.clickablePrompt();
		if ( await this.appears( clickable ) ) {
			await clickable.click().catch( () => {} );
			// Wait for it to go away, so the next pass doesn't re-detect the
			// screen it just dismissed and act on it twice.
			await clickable
				.waitFor( { state: 'hidden', timeout: 4_000 } )
				.catch( () => {} );
		}

		return false;
	};

	/**
	 * Describes whatever screen the popup is stuck on, for the failure message.
	 *
	 * A datacenter IP (which is what CI runs from) draws challenges that no
	 * amount of retrying solves — recovery-email confirmation, device approval,
	 * "this browser or app may not be secure". Those have to be identifiable
	 * from a CI log alone, since they can't be reproduced interactively.
	 */
	private describeCurrentScreen = async () => {
		const heading = await this.page
			.getByRole( 'heading' )
			.first()
			.textContent( { timeout: 2_000 } )
			.catch( () => null );

		return `${ this.page.url() }${
			heading ? ` — "${ heading.trim() }"` : ''
		}`;
	};

	/**
	 * Dismisses whatever Google shows between sign-in and the payment sheet:
	 * 2-Step Verification, recovery-options (gds.google.com), consent dialogs,
	 * "Continue" / "Not now".
	 *
	 * Bounded by wall-clock time rather than a number of attempts: the same ten
	 * passes meant seconds on a settled page and minutes mid-navigation. The old
	 * loop also ran out silently, so a popup that never reached the payment sheet
	 * surfaced later as a confusing "confirm button is not visible" failure.
	 */
	private skipPostLoginPrompts = async () => {
		await expect
			.poll( () => this.advancePastPrompt(), {
				message:
					'Google Pay popup never reached the payment sheet — stuck on an unhandled screen',
				timeout: 30_000,
				intervals: [ 0 ],
			} )
			.toBe( true )
			.catch( async ( error ) => {
				// Rethrow with the screen appended rather than replacing the
				// message: a missing GOOGLE_PAY_TOTP_SECRET surfaces here too,
				// and that error names the actual fix.
				throw new Error(
					`${
						error.message
					}\nStuck on: ${ await this.describeCurrentScreen() }`
				);
			} );
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
					await this.confirmButton()
						.click()
						.catch( () => {} );
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
