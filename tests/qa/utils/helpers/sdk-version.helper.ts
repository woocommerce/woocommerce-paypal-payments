/**
 * The PayPal JS SDK version under test.
 *
 * Single source of truth read from PCP_JS_SDK_VERSION: env-setup (which
 * flips the `pcp-sdk-version-flag` plugin) and the page objects (which branch
 * locators) both read this, so the WP-side flag and the Playwright-side
 * locators can never drift out of sync.
 */
export type SdkVersion = 'v5' | 'v6';

export const sdkVersion = (): SdkVersion =>
	process.env.PCP_JS_SDK_VERSION === 'v5' ? 'v5' : 'v6';
