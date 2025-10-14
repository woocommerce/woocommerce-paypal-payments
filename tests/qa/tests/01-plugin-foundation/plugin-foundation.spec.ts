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

testPluginInstallationFromFile( 'PCP-1000', pcpPlugin, '@Critical' );

testPluginReinstallationFromFile( 'PCP-1007', pcpPlugin, '@Critical' );

testPluginActivation( 'PCP-2003', pcpPlugin, '@Critical' );

testPluginDeactivation( 'PCP-1006', pcpPlugin, '@Critical' );

testPluginRemoval( 'PCP-1005', pcpPlugin, '@Critical' );
