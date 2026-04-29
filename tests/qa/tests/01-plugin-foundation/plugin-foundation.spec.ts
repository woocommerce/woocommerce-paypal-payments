/**
 * External dependencies
 */
import {
	testPluginInstallationFromFile,
	testPluginReinstallationFromFile,
	testPluginActivation,
	testPluginDeactivation,
	testPluginRemoval,
} from '@inpsyde/playwright-utils/build';
/**
 * Internal dependencies
 */
import { pcpPlugin } from '../../resources';

testPluginInstallationFromFile( 'PCP-1000', pcpPlugin, '@Critical @Smoke' );

testPluginReinstallationFromFile( 'PCP-1007', pcpPlugin, '@Critical @Smoke' );

testPluginActivation( 'PCP-2003', pcpPlugin, '@Critical @Smoke' );

testPluginDeactivation( 'PCP-1006', pcpPlugin, '@Critical @Smoke' );

testPluginRemoval( 'PCP-1005', pcpPlugin, '@Critical @Smoke' );
