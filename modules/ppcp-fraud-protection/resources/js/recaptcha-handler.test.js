import { waitFor } from '@testing-library/react';
import '@ppcp-test/helpers/silenceConsole';

const HANDLER_PATH ='./recaptcha-handler';
const TOKEN = 'token-123';

const createWpData = ( { storeExists = true } = {} ) => {
	const listeners = [];
	const state = { storeExists };
	const setExtensionData = jest.fn();
	const store = { __internalSetExtensionData: setExtensionData };

	return {
		setExtensionData,
		registerStore() {
			state.storeExists = true;
			listeners.forEach( ( listener ) => listener() );
		},
		wpData: {
			dispatch: () => ( state.storeExists ? store : undefined ),
			select: () =>
				state.storeExists ? { hasError: () => false } : undefined,
			subscribe: ( listener ) => listeners.push( listener ),
		},
	};
};

const loadHandler = () => {
	jest.isolateModules( () => {
		require( HANDLER_PATH );
	} );
};

const waitForTokenGenerated = async () => {
	await waitFor( () =>
		expect( window.grecaptcha.execute ).toHaveBeenCalled()
	);
	await window.grecaptcha.execute.mock.results[ 0 ].value;
	await Promise.resolve();
};

describe( 'recaptcha-handler', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		document.body.innerHTML = '';
		window.jQuery = require( 'jquery' );
		window.ppcpRecaptchaSettings = { siteKeyV3: 'site-key', isCheckout: true };
		window.grecaptcha = {
			execute: jest.fn().mockResolvedValue( TOKEN ),
		};
		delete window.wp;
	} );

	afterEach( () => {
		delete window.wp;
		delete window.grecaptcha;
		delete window.ppcpRecaptchaSettings;
	} );

	test( 'passes the token to the blocks checkout store when only the store exists', async () => {
		const { wpData, setExtensionData } = createWpData();
		window.wp = { data: wpData };

		loadHandler();

		await waitFor( () =>
			expect( setExtensionData ).toHaveBeenCalledWith( 'ppcp_recaptcha', {
				token: TOKEN,
				version: 'v3',
			} )
		);
	} );

	test( 'writes the token to hidden inputs of the classic form when only the form exists', async () => {
		document.body.innerHTML = '<form class="checkout"></form>';

		loadHandler();

		const form = document.querySelector( 'form.checkout' );
		await waitFor( () =>
			expect(
				form.querySelector( 'input[name="ppcp_recaptcha_token"]' )
			).not.toBeNull()
		);
		expect(
			form.querySelector( 'input[name="ppcp_recaptcha_token"]' ).value
		).toBe( TOKEN );
		expect(
			form.querySelector( 'input[name="ppcp_recaptcha_version"]' ).value
		).toBe( 'v3' );
	} );

	test( 'does not throw when neither the store nor the form exists', async () => {
		loadHandler();
		await waitForTokenGenerated();

		expect(
			document.querySelector( 'input[name="ppcp_recaptcha_token"]' )
		).toBeNull();
	} );

	test( 'passes the token to a store that registers after the token was generated', async () => {
		const { wpData, setExtensionData, registerStore } = createWpData( {
			storeExists: false,
		} );
		window.wp = { data: wpData };

		loadHandler();
		await waitForTokenGenerated();
		expect( setExtensionData ).not.toHaveBeenCalled();

		registerStore();

		expect( setExtensionData ).toHaveBeenCalledWith( 'ppcp_recaptcha', {
			token: TOKEN,
			version: 'v3',
		} );
	} );
} );
