( function ( $ ) {
	'use strict';

	const config = window.ppcpRecaptchaSettings || {};
	let v3Token = null;
	let v2Token = null;
	let v2WidgetId = null;
	let showingV2 = false;

	function waitForRecaptchaV3() {
		return new Promise( function ( resolve ) {
			if ( typeof grecaptcha !== 'undefined' && grecaptcha.execute ) {
				console.log( 'PPCP reCAPTCHA: reCAPTCHA v3 loaded and ready' );
				resolve();
			} else {
				console.log(
					'PPCP reCAPTCHA: Waiting for reCAPTCHA v3 to load...'
				);
				setTimeout( function () {
					waitForRecaptchaV3().then( resolve );
				}, 100 );
			}
		} );
	}

	function updateClassicCheckoutToken( token, version ) {
		if ( config.isBlocks ) {
			return;
		}

		const selector = config.isSingleProduct
			? 'form.cart'
			: config.isCheckout
			? 'form.checkout'
			: null;
		if ( ! selector ) {
			return;
		}

		const form = $( selector );
		if ( form.length === 0 ) {
			console.error( 'PPCP reCAPTCHA: Form not found for token update' );
			return;
		}

		form.find( 'input[name="ppcp_recaptcha_token"]' ).remove();
		form.find( 'input[name="ppcp_recaptcha_version"]' ).remove();
		form.append(
			'<input type="hidden" name="ppcp_recaptcha_token" value="' +
				token +
				'">'
		);
		form.append(
			'<input type="hidden" name="ppcp_recaptcha_version" value="' +
				version +
				'">'
		);
	}

	function handleCheckoutErrors( errorMessages ) {
		console.log( 'PPCP reCAPTCHA: Checkout error detected' );

		const hasCaptchaError =
			errorMessages &&
			( typeof errorMessages === 'string'
				? errorMessages.toLowerCase().includes( 'captcha' )
				: errorMessages.some(
						( error ) =>
							error.content &&
							error.content.toLowerCase().includes( 'captcha' )
				  ) );

		if ( showingV2 ) {
			if (
				v2WidgetId !== null &&
				typeof grecaptcha !== 'undefined' &&
				grecaptcha.reset
			) {
				console.log( 'PPCP reCAPTCHA: Resetting v2 widget' );
				grecaptcha.reset( v2WidgetId );
				v2Token = null;
			}
		} else if ( hasCaptchaError ) {
			console.log(
				'PPCP reCAPTCHA: v3 failed on checkout, rendering v2'
			);
			renderV2();
		} else if ( config.siteKeyV3 ) {
			console.log( 'PPCP reCAPTCHA: Regenerating v3 token' );
			generateV3Token();
		}
	}

	async function generateV3Token() {
		if ( typeof grecaptcha === 'undefined' || ! grecaptcha.execute ) {
			console.error( 'PPCP reCAPTCHA: grecaptcha v3 not loaded' );
			return;
		}

		try {
			v3Token = await grecaptcha.execute( config.siteKeyV3, {
				action: 'ppcp',
			} );
			console.log( 'PPCP reCAPTCHA: New v3 token generated' );
			updateClassicCheckoutToken( v3Token, 'v3' );

			if ( config.isBlocks && window.wp && window.wp.data ) {
				window.wp.data
					.dispatch( 'wc/store/checkout' )
					.__internalSetExtensionData( 'ppcp_recaptcha', {
						token: v3Token,
						version: 'v3',
					} );
			}
		} catch ( error ) {
			console.error(
				'PPCP reCAPTCHA: Failed to generate v3 token',
				error
			);
			v3Token = null;
		}
	}

	function renderV2() {
		if ( showingV2 || ! config.siteKeyV2 ) {
			return;
		}

		const container = document.getElementById( config.v2ContainerId );
		if ( ! container ) {
			console.error( 'PPCP reCAPTCHA: v2 container not found' );
			return;
		}

		if ( typeof grecaptcha === 'undefined' || ! grecaptcha.render ) {
			console.error( 'PPCP reCAPTCHA: grecaptcha v2 not loaded' );
			return;
		}

		container.innerHTML = '';

		const recaptchaDiv = document.createElement( 'div' );
		recaptchaDiv.className = 'g-recaptcha';
		recaptchaDiv.setAttribute( 'data-sitekey', config.siteKeyV2 );
		recaptchaDiv.setAttribute( 'data-theme', config.theme );

		container.appendChild( recaptchaDiv );

		v2WidgetId = grecaptcha.render( recaptchaDiv, {
			sitekey: config.siteKeyV2,
			theme: config.theme,
			callback( token ) {
				v2Token = token;
				console.log( 'PPCP reCAPTCHA: v2 verified' );
				updateClassicCheckoutToken( token, 'v2' );

				if ( config.isBlocks && window.wp && window.wp.data ) {
					window.wp.data
						.dispatch( 'wc/store/checkout' )
						.__internalSetExtensionData( 'ppcp_recaptcha', {
							token,
							version: 'v2',
						} );
				}
			},
			'expired-callback'() {
				v2Token = null;
				updateClassicCheckoutToken( '', 'v2' );

				if ( config.isBlocks && window.wp && window.wp.data ) {
					window.wp.data
						.dispatch( 'wc/store/checkout' )
						.__internalSetExtensionData( 'ppcp_recaptcha', {
							token: '',
							version: 'v2',
						} );
				}
			},
		} );

		showingV2 = true;
	}

	const originalFetch = window.fetch;
	window.fetch = async function ( resource, init = {} ) {
		const url = typeof resource === 'string' ? resource : resource.url;
		const isPayPalAjax = url && url.includes( 'ppc-create-order' );

		if ( ! isPayPalAjax ) {
			return originalFetch.call( this, resource, init );
		}

		console.log( 'PPCP reCAPTCHA: Intercepting AJAX', url );

		let token, version;
		if ( showingV2 && v2Token ) {
			token = v2Token;
			version = 'v2';
		} else {
			token = v3Token;
			version = 'v3';
		}

		if ( ! token ) {
			console.error( 'PPCP reCAPTCHA: No token available' );
			return Promise.reject( new Error( 'Missing reCAPTCHA token' ) );
		}

		try {
			const body = JSON.parse( init.body );
			body.ppcp_recaptcha_token = token;
			body.ppcp_recaptcha_version = version;
			init.body = JSON.stringify( body );
			console.log( 'PPCP reCAPTCHA: Token injected', version );
		} catch ( e ) {
			console.error( 'PPCP reCAPTCHA: Failed to inject token', e );
		}

		return originalFetch
			.call( this, resource, init )
			.then( function ( response ) {
				if ( response.status === 403 || response.status === 400 ) {
					response
						.clone()
						.json()
						.then( function ( response ) {
							if (
								response.data.code ===
								config.errorCodeVerificationFailed
							) {
								if ( ! showingV2 ) {
									console.log(
										'PPCP reCAPTCHA: v3 failed, rendering v2'
									);
									renderV2();
								} else {
									console.log(
										'PPCP reCAPTCHA: v2 verification failed, resetting v2 widget'
									);
									grecaptcha.reset( v2WidgetId );
									v2Token = null;
								}
							}
						} );
				}
				return response;
			} );
	};

	$( document ).ready( function () {
		if ( config.siteKeyV3 ) {
			waitForRecaptchaV3().then( function () {
				console.log( 'PPCP reCAPTCHA: Pre-generating v3 token' );
				generateV3Token();
				setInterval( generateV3Token, 90000 );
			} );
		}
	} );

	if ( ! config.isBlocks && config.isCheckout ) {
		$( document.body ).on( 'checkout_error', function () {
			const errorMessages = $(
				'.woocommerce-error, .woocommerce-NoticeGroup-checkout'
			).text();
			handleCheckoutErrors( errorMessages );
		} );
	}

	if ( config.isBlocks && window.wp && window.wp.data ) {
		const { subscribe, select } = window.wp.data;
		let previousHasError = false;

		subscribe( () => {
			const checkoutStore = select( 'wc/store/checkout' );
			if ( ! checkoutStore ) {
				return;
			}

			// Using hasError() + DOM read as fallback, validationStore.getValidationErrors() not yet working
			const hasError = checkoutStore.hasError();

			if ( hasError && ! previousHasError ) {
				console.log( 'PPCP reCAPTCHA: Block checkout error detected' );
				setTimeout( function () {
					const errorBanner = $(
						'.wc-block-components-notice-banner__content'
					);
					if ( errorBanner.length > 0 ) {
						const errorMessages = errorBanner.text();
						console.log(
							'PPCP reCAPTCHA: Error message extracted:',
							errorMessages
						);
						handleCheckoutErrors( errorMessages );
					} else {
						console.error(
							'PPCP reCAPTCHA: Error banner not found in DOM'
						);
					}
				}, 100 );
			}

			previousHasError = hasError;
		} );
	}
} )( jQuery );
