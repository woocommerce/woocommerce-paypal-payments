/**
 * External dependencies
 */
import { expect, BrowserContext, Locator, Page } from '@playwright/test';
import { generate } from 'otplib';
import fs from 'fs';
import path from 'path';

/**
 * Handles the Google Pay TEST-environment popup.
 *
 * Flow: button click opens a popup -> Google sign-in (accounts.google.com) ->
 * optional 2FA (TOTP, or a "Verify it's you" step-up prompt) -> optional
 * consent/recovery screens -> payment sheet with a confirm button.
 *
 * Credentials: GOOGLE_PAY_EMAIL / GOOGLE_PAY_PASSWORD / GOOGLE_PAY_TOTP_SECRET
 * (authenticator app, not SMS). Sign-in cookies are cached across runs (see
 * loadPersistedSession/persistSession) so Google recognizes the device and
 * offers the step-up challenge less often.
 */
export class GooglePayPopup {
	page: Page;

	constructor( page: Page ) {
		this.page = page;
	}

	/** Where Google's sign-in cookies are cached across runs (google.com only, separate from the WP storage states in STORAGE_STATE_PATH). */
	private static sessionStoragePath = (): string | undefined =>
		process.env.STORAGE_STATE_PATH
			? path.join(
					process.env.STORAGE_STATE_PATH,
					'google-pay-session.json'
			  )
			: undefined;

	/**
	 * Restores cached Google sign-in cookies so the device is recognized.
	 * Call in beforeEach, alongside applyBrowserPatches(), before navigation.
	 *
	 * @param context
	 */
	static loadPersistedSession = async (
		context: BrowserContext
	): Promise< void > => {
		const storagePath = GooglePayPopup.sessionStoragePath();
		if ( ! storagePath || ! fs.existsSync( storagePath ) ) {
			return;
		}

		try {
			const { cookies } = JSON.parse(
				fs.readFileSync( storagePath, 'utf-8' )
			);
			if ( cookies?.length ) {
				await context.addCookies( cookies );
			}
		} catch {
			// A corrupt or unreadable cache isn't fatal — the test just signs in fresh.
		}
	};

	/** Persists the context's google.com cookies for a later run to reuse, excluding the merchant site's own cookies in the same context. */
	private persistSession = async (): Promise< void > => {
		const storagePath = GooglePayPopup.sessionStoragePath();
		if ( ! storagePath ) {
			return;
		}

		try {
			const { cookies } = await this.page.context().storageState();
			const googleCookies = cookies.filter( ( cookie ) =>
				/(^|\.)google\.com$/.test( cookie.domain )
			);

			fs.mkdirSync( path.dirname( storagePath ), { recursive: true } );
			fs.writeFileSync(
				storagePath,
				JSON.stringify( { cookies: googleCookies }, null, 2 )
			);
		} catch {
			// Best-effort caching; a write failure shouldn't fail the test.
		}
	};

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

	// Switches an SMS-first challenge to the TOTP prompt.
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

	// Recovery-options prompt (gds.google.com); Cancel returns to the payment flow.
	private recoveryCancelButton = () =>
		this.page.getByRole( 'button', { name: 'Cancel' } );

	/**
	 * Every post-login screen a plain click gets past, matched as a union
	 * since they all need the same action. "Cancel" only applies on the
	 * recovery prompt — too risky to offer generically.
	 */
	private clickablePrompt = () => {
		const base = this.authenticatorAppOption()
			.or( this.tryAnotherWayButton() )
			.or( this.postLoginButton() );

		// .first(): waitFor is strict and would throw if two matched at once.
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
	 * Patches the context so Google doesn't detect automation and block
	 * sign-in. Call in beforeEach, before navigation.
	 *
	 * @param context
	 */
	static applyBrowserPatches = async ( context: BrowserContext ) => {
		await context.addInitScript( () => {
			// Needed for a secure context; without it, http:// throws DEVELOPER_ERROR.
			try {
				Object.defineProperty( window, 'isSecureContext', {
					get: () => true,
					configurable: true,
				} );
			} catch {}

			// Forces window.open() mode instead of the native Payment Handler
			// sheet, which Playwright can't capture as a popup event.
			try {
				// @ts-ignore — intentional: force Google Pay into popup mode
				delete window.PaymentRequest;
			} catch {
				try {
					// @ts-ignore
					window.PaymentRequest = undefined;
				} catch {}
			}

			// Headless Chrome lacks window.chrome, which trips Google's "may not be secure" check.
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

			// A non-empty plugins list looks more like a real browser to Google's risk scoring.
			try {
				Object.defineProperty( navigator, 'plugins', {
					get: () => [ 1, 2, 3, 4, 5 ],
					configurable: true,
				} );
			} catch {}
		} );
	};

	/**
	 * Fills email/password only if Google actually asks for them — a
	 * restored session can skip the form entirely. Env vars are still
	 * required as a fallback for a stale/expired session.
	 */
	private signInToGoogle = async () => {
		const email = process.env.GOOGLE_PAY_EMAIL;
		const password = process.env.GOOGLE_PAY_PASSWORD;

		if ( ! email || ! password ) {
			throw new Error(
				'GOOGLE_PAY_EMAIL and GOOGLE_PAY_PASSWORD must be set to run Google Pay tests.'
			);
		}

		if ( ! ( await this.appears( this.emailInput() ) ) ) {
			return;
		}
		await this.emailInput().fill( email );

		await expect(
			this.nextButton(),
			'Assert next button (email) is visible'
		).toBeVisible();
		await this.nextButton().click();

		if ( ! ( await this.appears( this.passwordInput() ) ) ) {
			return;
		}
		await this.passwordInput().fill( password );

		await expect(
			this.nextButton(),
			'Assert next button (password) is visible'
		).toBeVisible();
		await this.nextButton().click();

		await this.page.waitForLoadState();
	};

	/**
	 * Whether `locator` shows up within `timeout`. Uses `waitFor` rather than
	 * `isVisible`, which checks the DOM only once and misses anything that
	 * simply hasn't rendered yet.
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

		// Wait for the field to clear before the next pass, or it resubmits
		// the same single-use code and Google rejects it, costing the challenge.
		await codeInput
			.waitFor( { state: 'hidden', timeout: 20_000 } )
			.catch( () => {} );
	};

	/**
	 * Advances the popup past whichever post-login screen is showing, at most
	 * one per call. Returns true once the confirm button is visible.
	 *
	 * "Arrived" used to be inferred from the URL reaching pay.google.com, but
	 * Google can show a step-up "Verify it's you" prompt on that same domain —
	 * the URL check would then report done while the prompt sits untouched.
	 * Checking the confirm button directly avoids that false positive.
	 *
	 * Action errors are swallowed on purpose: these screens are transient, and
	 * `expect.poll` needs the generator to keep retrying rather than throw.
	 */
	private advancePastPrompt = async (): Promise< boolean > => {
		if ( await this.appears( this.confirmButton(), 1_000 ) ) {
			return true;
		}

		if ( await this.appears( this.totpCodeInput() ) ) {
			await this.submitTotpCode();
			return false;
		}

		const clickable = this.clickablePrompt();
		if ( await this.appears( clickable ) ) {
			await clickable.click().catch( () => {} );
			// So the next pass doesn't re-detect and re-click the same screen.
			await clickable
				.waitFor( { state: 'hidden', timeout: 4_000 } )
				.catch( () => {} );
		}

		return false;
	};

	/**
	 * Describes whatever screen the popup is stuck on, for the failure
	 * message — some CI-only challenges (device approval, recovery email)
	 * can't be reproduced interactively, so this has to be identifiable from
	 * the log alone.
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
	 * Dismisses whatever Google shows between sign-in and the payment sheet.
	 * Bounded by wall-clock time, not attempt count, so a popup that never
	 * arrives fails here with a clear message instead of a confusing timeout
	 * later on the confirm button.
	 */
	private skipPostLoginPrompts = async () => {
		await expect
			.poll( () => this.advancePastPrompt(), {
				message:
					'Google Pay popup never reached the payment sheet — stuck on an unhandled screen',
				// Bumped from 30s: each pass now also peeks for the confirm
				// button, so a multi-step challenge needs more passes to resolve.
				timeout: 45_000,
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
		// Cache the session now that we've reliably signed in, before the
		// popup closes.
		await this.persistSession();
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
					// Swallow click errors from the popup tearing down mid-click;
					// isClosed() on the next poll is the real success signal.
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
