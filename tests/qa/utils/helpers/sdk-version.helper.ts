/**
 * The PayPal JS SDK version under test.
 *
 * Since 4.2.0 v6 is PCP's default and needs no configuration. Setting
 * PCP_JS_SDK_VERSION=v5 makes env-setup force v5 via the
 * `pcp-sdk-version-flag` plugin and switches the page objects to v5
 * locators, so the WP-side flag and the locators can't drift apart.
 */
export type SdkVersion = 'v5' | 'v6';

export const sdkVersion = (): SdkVersion =>
	process.env.PCP_JS_SDK_VERSION === 'v5' ? 'v5' : 'v6';
