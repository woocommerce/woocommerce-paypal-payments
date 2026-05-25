/**
 * External dependencies
 */
import crypto from 'crypto';
import fs from 'fs';
import path from 'path';
import { expect, BrowserContext, Cookie, Page } from '@playwright/test';

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

	/**
	 * Path to the cached Google session cookies file
	 */
	private static readonly SESSION_PATH = path.join(
		__dirname,
		'../../.google-session.json'
	);

	/**
	 * Loads previously saved Google cookies into the browser context
	 * @param context
	 */
	private static loadGoogleSession = async (
		context: BrowserContext
	): Promise< void > => {
		try {
			if ( ! fs.existsSync( GooglePayPopup.SESSION_PATH ) ) {
				return;
			}
			const { cookies } = JSON.parse(
				fs.readFileSync( GooglePayPopup.SESSION_PATH, 'utf-8' )
			) as { cookies: Cookie[] };
			if ( Array.isArray( cookies ) && cookies.length > 0 ) {
				await context.addCookies( cookies );
			}
		} catch {}
	};

	/**
	 * Saves this popup's google.com cookies to disk so they can be reused later.
	 */
	private saveGoogleSession = async (): Promise< void > => {
		try {
			const all = await this.page.context().cookies();
			const googleCookies = all.filter(
				( c ) =>
					c.domain === 'google.com' ||
					c.domain.endsWith( '.google.com' )
			);
			fs.writeFileSync(
				GooglePayPopup.SESSION_PATH,
				JSON.stringify( { cookies: googleCookies }, null, 2 )
			);
		} catch {}
	};

	// -------------------------------------------------------------------------
	// Browser-level patches — call once on the context before the test runs
	// -------------------------------------------------------------------------

	/**
	 * Registers init scripts on the Playwright browser context that prevent
	 * Google from detecting the automated browser and blocking sign-in.
	 * Call this in beforeEach, before any page navigation.
	 * @param context
	 */
	static applyBrowserPatches = async ( context: BrowserContext ) => {
		// Restore a cached Google session from disk
		await GooglePayPopup.loadGoogleSession( context );

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

	// -------------------------------------------------------------------------
	// Locators — Google Sign-in (accounts.google.com)
	// -------------------------------------------------------------------------

	emailInput = () => this.page.locator( 'input[type="email"]' );

	passwordInput = () =>
		this.page
			.locator(
				'input[type="password"]:not([aria-hidden="true"]):not([tabindex="-1"])'
			)
			.or( this.page.locator( 'input[type="password"][name="Passwd"]' ) )
			.first();

	/** "Next" button shared by the email step and the password step. */
	nextButton = () =>
		this.page
			.getByRole( 'button', { name: 'Next' } )
			.or(
				this.page
					.locator( '[jsname="LgbsSe"]' )
					.filter( { hasText: /Next/i } )
			)
			.first();

	/** TOTP / authenticator-app code input shown during 2-step verification. */
	private totpInput = () =>
		this.page
			.locator( 'input[name="totpPin"]' )
			.or( this.page.locator( 'input[id="totpPin"]' ) )
			.or( this.page.locator( 'input[autocomplete="one-time-code"]' ) )
			.or(
				this.page.locator( 'input[type="tel"]' ).filter( {
					has: this.page.locator( ':scope' ).and(
						this.page.locator( '[aria-label*="code" i], [aria-label*="verification" i]' )
					),
				} )
			)
			.first();

	/** "Try another way" link shown on 2FA challenge pages to switch auth method. */
	private tryAnotherWayLink = () =>
		this.page
			.getByRole( 'link', { name: /try another way/i } )
			.or( this.page.getByRole( 'button', { name: /try another way/i } ) )
			.first();

	/** Authenticator-app option shown after clicking "Try another way". */
	private authenticatorAppOption = () =>
		this.page
			.getByRole( 'link', { name: /authenticator app/i } )
			.or( this.page.getByRole( 'button', { name: /authenticator app/i } ) )
			.or( this.page.locator( '[data-authtype="33"]' ) )
			.first();

	/**
	 * Generates a TOTP code from a base32 secret (RFC 6238, SHA-1, 30 s step, 6 digits).
	 */
	private static generateTOTP( secret: string ): string {
		const base32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
		const clean = secret.toUpperCase().replace( /[^A-Z2-7]/g, '' );

		// Base32-decode the secret into a byte array.
		let bits = 0;
		let acc = 0;
		const keyBytes: number[] = [];
		for ( const ch of clean ) {
			const idx = base32.indexOf( ch );
			if ( idx < 0 ) continue;
			acc = ( acc << 5 ) | idx;
			bits += 5;
			if ( bits >= 8 ) {
				keyBytes.push( ( acc >> ( bits - 8 ) ) & 0xff );
				bits -= 8;
			}
		}

		// Current 30-second time step as an 8-byte big-endian counter.
		const step = Math.floor( Date.now() / 1000 / 30 );
		const counter = Buffer.alloc( 8 );
		counter.writeUInt32BE( Math.floor( step / 0x100000000 ), 0 );
		counter.writeUInt32BE( step >>> 0, 4 );

		// HMAC-SHA1 → dynamic truncation → 6-digit code.
		const hmac = crypto
			.createHmac( 'sha1', Buffer.from( keyBytes ) )
			.update( counter )
			.digest();
		const offset = hmac[ hmac.length - 1 ] & 0x0f;
		const code =
			( ( ( hmac[ offset ] & 0x7f ) << 24 ) |
				( ( hmac[ offset + 1 ] & 0xff ) << 16 ) |
				( ( hmac[ offset + 2 ] & 0xff ) << 8 ) |
				( hmac[ offset + 3 ] & 0xff ) ) %
			1_000_000;

		return code.toString().padStart( 6, '0' );
	}

	private postLoginButton = () =>
		this.page
			.getByRole( 'button', {
				name: /^(Continue|I agree|Confirm|Not now|Skip|Yes|Got it)$/i,
			} )
			.first();

	// -------------------------------------------------------------------------
	// Locators — Google Pay payment sheet (pay.google.com)
	// -------------------------------------------------------------------------

	/** The confirm button lives inside the cross-origin buyflow2 iframe. */
	private buyflowFrame = () =>
		this.page.frameLocator( 'iframe[src*="buyflow2"]' );

	confirmButton = () =>
		this.buyflowFrame()
			.getByRole( 'button', {
				name: /^(Continue|Pay now|Pay|Confirm)$/i,
			} )
			.or(
				this.buyflowFrame().locator( 'button[jsname="LgbsSe"]' ).last()
			);

	// -------------------------------------------------------------------------
	// Actions
	// -------------------------------------------------------------------------

	private waitForContent = async () => {
		// The popup can sit at about:blank briefly while the SDK prepares the redirect.
		await this.page
			.waitForURL( ( url ) => url.href !== 'about:blank', {
				timeout: 15_000,
			} )
			.catch( () => {} );

		// Reload only when still on about:blank; reloading during Google's redirect can break sign-in.
		if ( this.page.url() === 'about:blank' || this.page.url() === '' ) {
			await this.page
				.reload( { waitUntil: 'domcontentloaded' } )
				.catch( () => {} );
		}

		// Wait until the pay.google loading step finishes so we land on sign-in or the pay screen before other checks run.
		await this.page
			.waitForURL(
				( url ) =>
					url.hostname.includes( 'accounts.google.com' ) ||
					( url.hostname.includes( 'pay.google.com' ) &&
						! url.pathname.startsWith( '/gp/p/loading' ) ),
				{ timeout: 20_000 }
			)
			.catch( () => {} );

		await this.page
			.waitForLoadState( 'domcontentloaded' )
			.catch( () => {} );
	};

	private signInToGoogle = async () => {
		const email = process.env.GOOGLE_PAY_EMAIL;
		const password = process.env.GOOGLE_PAY_PASSWORD;
		const totpSecret = process.env.GOOGLE_PAY_TOTP_SECRET;

		if ( ! email || ! password ) {
			throw new Error(
				'GOOGLE_PAY_EMAIL and GOOGLE_PAY_PASSWORD must be set to run Google Pay tests.'
			);
		}

		await expect(
			this.emailInput(),
			'Google email input is visible'
		).toBeVisible( { timeout: 15_000 } );
		await this.emailInput().fill( email );
		await this.nextButton().click();

		await expect(
			this.passwordInput(),
			'Google password input is visible'
		).toBeVisible( { timeout: 15_000 } );
		await this.passwordInput().fill( password );
		await this.nextButton().click();

		// Handle 2-step verification (TOTP challenge) if Google presents it.
		await this.handleTwoFactorIfPresent( totpSecret );

		// Wait until we've left all accounts.google.* domains (handles regional
		// variants like accounts.google.de that appear after 2FA redirects).
		await this.page.waitForURL(
			( url ) => ! url.hostname.includes( 'accounts.google' ),
			{ timeout: 30_000 }
		);
		await this.page.waitForLoadState();

		// Save login cookies so the next test run can skip Google sign-in.
		await this.saveGoogleSession();
	};

	/**
	 * If Google presents a TOTP / authenticator-app challenge after the password
	 * step, generates the current code from GOOGLE_PAY_TOTP_SECRET and submits it.
	 *
	 * Google may also complete 2FA via a device-approval push notification (no input
	 * required). In that case the challenge page navigates away on its own — we wait
	 * for that and skip the TOTP entry so the overall sign-in flow is not broken.
	 */
	private handleTwoFactorIfPresent = async (
		totpSecret: string | undefined
	): Promise< void > => {
		// Give Google a moment to decide whether to show a 2FA challenge.
		await this.page.waitForLoadState( 'domcontentloaded' ).catch( () => {} );

		const isOnAccountsGoogle = ( url: string ) =>
			url.includes( 'accounts.google' );

		const isChallengeUrl = ( url: string ) =>
			url.includes( 'accounts.google' ) &&
			( url.includes( '/challenge' ) || url.includes( 'totp' ) );

		if ( ! isChallengeUrl( this.page.url() ) ) {
			// Not on a challenge page yet — wait a beat in case of redirect.
			await this.page
				.waitForURL( ( u ) => isChallengeUrl( u.href ), {
					timeout: 5_000,
				} )
				.catch( () => {} );
		}

		if ( ! isChallengeUrl( this.page.url() ) ) {
			return; // No 2FA challenge — nothing to do.
		}

		// Soft-check: TOTP input may not appear if Google uses a different 2FA
		// method (device tap, push notification). Use a short timeout here rather
		// than a hard assertion so we don't fail when Google handles 2FA itself.
		const hasTotpInput = await this.totpInput()
			.waitFor( { state: 'visible', timeout: 8_000 } )
			.then( () => true )
			.catch( () => false );

		if ( ! hasTotpInput ) {
			// Google may be defaulting to device-approval or another 2FA method.
			// If we have a TOTP secret, try switching to the authenticator app via
			// "Try another way" before falling back to waiting for device approval.
			if ( totpSecret ) {
				const canTryAnother = await this.tryAnotherWayLink()
					.waitFor( { state: 'visible', timeout: 5_000 } )
					.then( () => true )
					.catch( () => false );

				if ( canTryAnother ) {
					await this.tryAnotherWayLink().click();
					await this.page
						.waitForLoadState( 'domcontentloaded' )
						.catch( () => {} );

					const hasAuthenticator = await this.authenticatorAppOption()
						.waitFor( { state: 'visible', timeout: 5_000 } )
						.then( () => true )
						.catch( () => false );

					if ( hasAuthenticator ) {
						await this.authenticatorAppOption().click();
						await this.page
							.waitForLoadState( 'domcontentloaded' )
							.catch( () => {} );

						const hasTotpNow = await this.totpInput()
							.waitFor( { state: 'visible', timeout: 8_000 } )
							.then( () => true )
							.catch( () => false );

						if ( hasTotpNow ) {
							const code = GooglePayPopup.generateTOTP( totpSecret );
							await this.totpInput().fill( code );
							await this.nextButton().click();
							return;
						}
					}
				}
			}

			// No TOTP path available — wait for device approval or other auto-navigation.
			await this.page
				.waitForURL( ( u ) => ! isOnAccountsGoogle( u.href ), {
					timeout: 30_000,
				} )
				.catch( () => {} );
			return;
		}

		if ( ! totpSecret ) {
			throw new Error(
				'Google presented a TOTP challenge but GOOGLE_PAY_TOTP_SECRET is not set.'
			);
		}

		const code = GooglePayPopup.generateTOTP( totpSecret );
		await this.totpInput().fill( code );
		await this.nextButton().click();
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

		await expect(
			this.confirmButton(),
			'Google Pay confirm button is visible'
		).toBeVisible( { timeout: 30_000 } );

		await this.saveGoogleSession();

		await Promise.all( [
			this.page.waitForEvent( 'close' ),
			this.confirmButton().click(),
		] );
	};
}
