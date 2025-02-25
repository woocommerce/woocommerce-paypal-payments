import { PercyConfig } from '@inpsyde/playwright-utils/build/@types/visual/percy';

export const percyPcpSettingsConfig: PercyConfig = {
	scope: '#ppcp-settings-container',
	httpCredentials: {
		username: process.env.WP_BASIC_AUTH_USER,
		password: process.env.WP_BASIC_AUTH_PASS,
	},
};