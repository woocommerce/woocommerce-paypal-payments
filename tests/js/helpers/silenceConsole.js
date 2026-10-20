/**
 * Stubs the console for a test file that deliberately drives a failure path.
 *
 * The WordPress Jest preset fails any test that writes to the console without
 * asserting on it. Asserting would pin log wording nothing depends on, so a
 * file that expects console output imports this instead: the output goes
 * nowhere, and nothing is expected of it.
 *
 * Usage, from any module's test file:
 *
 *     import '@ppcp-test/helpers/silenceConsole';
 *
 * @package
 */

const SILENCED = [ 'error', 'warn', 'log', 'info' ];

const original = {};

/*
 * A plain function, not jest.spyOn(). The preset has already replaced these
 * methods with its own spies, and spying on a spy hands back that same mock, so
 * the call would still be recorded and still fail the test. Replacing the method
 * outright is what keeps the preset's spy from ever seeing it.
 */
beforeEach( () => {
	SILENCED.forEach( ( level ) => {
		original[ level ] = console[ level ];
		console[ level ] = () => {};
	} );
} );

afterEach( () => {
	SILENCED.forEach( ( level ) => {
		console[ level ] = original[ level ];
	} );
} );
