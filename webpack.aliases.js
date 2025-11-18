/**
 * Shared webpack aliases for all modules.
 *
 * This allows modules to use clean import paths like:
 * - import { registerSetting } from '@settings/extensions';
 * - import { SettingsBlock } from '@settings/components';
 */

const path = require( 'path' );

module.exports = {
	'@settings': path.resolve(
		__dirname,
		'modules/ppcp-settings/resources/js'
	),
};
