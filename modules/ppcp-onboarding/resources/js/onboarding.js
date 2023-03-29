const ppcp_onboarding = {
	BUTTON_SELECTOR: '[data-paypal-onboard-button]',
	PAYPAL_JS_ID: 'ppcp-onboarding-paypal-js',
	_timeout: false,

    STATE_START: 'start',
    STATE_ONBOARDED: 'onboarded',

	init: function() {
		document.addEventListener('DOMContentLoaded', this.reload);
	},

	reload: function() {
		const buttons = document.querySelectorAll(ppcp_onboarding.BUTTON_SELECTOR);

		if (0 === buttons.length) {
			return;
		}

		// Add event listeners to buttons preventing link clicking if PayPal init failed.
		buttons.forEach(
			(element) => {
				if (element.hasAttribute('data-ppcp-button-initialized')) {
					return;
				}

				element.addEventListener(
					'click',
					(e) => {
						if (!element.hasAttribute('data-ppcp-button-initialized') || 'undefined' === typeof window.PAYPAL) {
							e.preventDefault();
						}
					}
				);
			}
		);

		// Clear any previous PayPal scripts.
		[ppcp_onboarding.PAYPAL_JS_ID, 'signup-js', 'biz-js'].forEach(
			(scriptID) => {
				const scriptTag = document.getElementById(scriptID);

				if (scriptTag) {
					scriptTag.parentNode.removeChild(scriptTag);
				}

				if ('undefined' !== typeof window.PAYPAL) {
					delete window.PAYPAL;
				}
			}
		);

		// Load PayPal scripts.
		const paypalScriptTag = document.createElement('script');
		paypalScriptTag.id = ppcp_onboarding.PAYPAL_JS_ID;
		paypalScriptTag.src = PayPalCommerceGatewayOnboarding.paypal_js_url;
		document.body.appendChild(paypalScriptTag);

		if (ppcp_onboarding._timeout) {
			clearTimeout(ppcp_onboarding._timeout);
		}

		ppcp_onboarding._timeout = setTimeout(
			() => {
				buttons.forEach((element) => { element.setAttribute('data-ppcp-button-initialized', 'true'); });

				if ('undefined' !== window.PAYPAL.apps.Signup) {
					window.PAYPAL.apps.Signup.render();
				}
			},
			1000
		);

		const onboard_pui = document.querySelector('#ppcp-onboarding-pui');
		const spinner = '<span class="spinner is-active" style="float: none;"></span>';
		onboard_pui?.addEventListener('click', (event) => {
            event.preventDefault();
            onboard_pui.setAttribute('disabled', 'disabled');
            buttons.forEach((element) => {
                element.removeAttribute('href');
                element.setAttribute('disabled', 'disabled');
                jQuery(spinner).insertAfter(element);
            });

            fetch(PayPalCommerceGatewayOnboarding.pui_endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    nonce: PayPalCommerceGatewayOnboarding.pui_nonce,
                    checked: onboard_pui.checked
                })
            }).then((res)=>{
                return res.json();
            }).then((data)=>{
                if (!data.success) {
                    alert('Could not update signup buttons: ' + JSON.stringify(data));
                    return;
                }

                buttons.forEach((element) => {
                    for (let [key, value] of Object.entries(data.data.signup_links)) {
                        key = 'connect-to' + key.replace(/-/g, '');
                        if(key === element.id) {
                            element.setAttribute('href', value);
                            element.removeAttribute('disabled')
                            document.querySelector('.spinner').remove()
                        }
                    }
                });
                onboard_pui.removeAttribute('disabled');
            });
        })
	},

	loginSeller: function(env, authCode, sharedId) {
		fetch(
			PayPalCommerceGatewayOnboarding.endpoint,
			{
				method: 'POST',
				headers: {
					'content-type': 'application/json'
				},
				body: JSON.stringify(
					{
						authCode: authCode,
						sharedId: sharedId,
						nonce: PayPalCommerceGatewayOnboarding.nonce,
						env: env,
                        acceptCards: document.querySelector('#ppcp-onboarding-accept-cards').checked,
					}
				)
			}
		);
	},
};


window.ppcp_onboarding_sandboxCallback = function(...args) {
    return ppcp_onboarding.loginSeller('sandbox', ...args);
};

window.ppcp_onboarding_productionCallback = function(...args) {
    return ppcp_onboarding.loginSeller('production', ...args);
};

(() => {
    const productionCredentialElementsSelectors = [
        '#field-merchant_email_production',
        '#field-merchant_id_production',
        '#field-client_id_production',
        '#field-client_secret_production',
    ];
    const sandboxCredentialElementsSelectors = [
        '#field-merchant_email_sandbox',
        '#field-merchant_id_sandbox',
        '#field-client_id_sandbox',
        '#field-client_secret_sandbox',
    ];

    const updateOptionsState = () => {
        const cardsChk = document.querySelector('#ppcp-onboarding-accept-cards');
        if (!cardsChk) {
            return;
        }

        document.querySelectorAll('#ppcp-onboarding-dcc-options input').forEach(input => {
            input.disabled = !cardsChk.checked;
        });

        document.querySelector('.ppcp-onboarding-cards-options').style.display = !cardsChk.checked ? 'none' : '';

        const basicRb = document.querySelector('#ppcp-onboarding-dcc-basic');

        const isExpress = !cardsChk.checked || basicRb.checked;

        const expressButtonSelectors = [
            '#field-ppcp_onboarding_production_express',
            '#field-ppcp_onboarding_sandbox_express',
        ];
        const ppcpButtonSelectors = [
            '#field-ppcp_onboarding_production_ppcp',
            '#field-ppcp_onboarding_sandbox_ppcp',
        ];

        document.querySelectorAll(expressButtonSelectors.join()).forEach(
            element => element.style.display = isExpress ? '' : 'none'
        );
        document.querySelectorAll(ppcpButtonSelectors.join()).forEach(
            element => element.style.display = !isExpress ? '' : 'none'
        );

        const screemImg = document.querySelector('#ppcp-onboarding-cards-screen-img');
        if (screemImg) {
            const currentRb = Array.from(document.querySelectorAll('#ppcp-onboarding-dcc-options input[type="radio"]'))
                .filter(rb => rb.checked)[0] ?? null;

            const imgUrl = currentRb.getAttribute('data-screen-url');
            screemImg.src = imgUrl;
        }
    };

    const updateManualInputControls = (shown, isSandbox, isAnyEnvOnboarded) => {
        const productionElementsSelectors = productionCredentialElementsSelectors;
        const sandboxElementsSelectors = sandboxCredentialElementsSelectors;
        const otherElementsSelectors = [
            '.woocommerce-save-button',
        ];
        if (!isAnyEnvOnboarded) {
            otherElementsSelectors.push('#field-sandbox_on');
        }

        document.querySelectorAll(productionElementsSelectors.join()).forEach(
            element => {
                element.classList.remove('hide', 'show');
                element.classList.add((shown && !isSandbox) ? 'show' : 'hide');
            }
        );
        document.querySelectorAll(sandboxElementsSelectors.join()).forEach(
            element => {
                element.classList.remove('hide', 'show');
                element.classList.add((shown && isSandbox) ? 'show' : 'hide');
            }
        );
        document.querySelectorAll(otherElementsSelectors.join()).forEach(
            element => element.style.display = shown ? '' : 'none'
        );
    };

    const updateEnvironmentControls = (isSandbox) => {
        const productionElementsSelectors = [
            '#field-ppcp_disconnect_production',
            '#field-credentials_production_heading',
        ];
        const sandboxElementsSelectors = [
            '#field-ppcp_disconnect_sandbox',
            '#field-credentials_sandbox_heading',
        ];

        document.querySelectorAll(productionElementsSelectors.join()).forEach(
            element => element.style.display = !isSandbox ? '' : 'none'
        );
        document.querySelectorAll(sandboxElementsSelectors.join()).forEach(
            element => element.style.display = isSandbox ? '' : 'none'
        );
    };

    let isDisconnecting = false;

    const disconnect = (event) => {
        event.preventDefault();
        const fields = event.target.classList.contains('production') ? productionCredentialElementsSelectors : sandboxCredentialElementsSelectors;

        document.querySelectorAll(fields.map(f => f + ' input').join()).forEach(
            (element) => {
                element.value = '';
            }
        );

        sandboxSwitchElement.checked = ! sandboxSwitchElement.checked;

        isDisconnecting = true;

        document.querySelector('.woocommerce-save-button').click();
    };

    // Prevent the message about unsaved checkbox/radiobutton when reloading the page.
    // (WC listens for changes on all inputs and sets dirty flag until form submission)
    const preventDirtyCheckboxPropagation = event => {
        event.preventDefault();
        event.stopPropagation();

        const value = event.target.checked;
        setTimeout( () => {
                event.target.checked = value;
            }, 1
        );
    };

    const sandboxSwitchElement = document.querySelector('#ppcp-sandbox_on');

    const validate = () => {
        const selectors = sandboxSwitchElement.checked ? sandboxCredentialElementsSelectors : productionCredentialElementsSelectors;
        const values = selectors.map(s => document.querySelector(s + ' input')).map(el => el.value);

        const errors = [];
        if (values.some(v => !v)) {
            errors.push(PayPalCommerceGatewayOnboarding.error_messages.no_credentials);
        }

        return errors;
    };

    const isAnyEnvOnboarded = PayPalCommerceGatewayOnboarding.sandbox_state === ppcp_onboarding.STATE_ONBOARDED ||
        PayPalCommerceGatewayOnboarding.production_state === ppcp_onboarding.STATE_ONBOARDED;

	document.querySelectorAll('.ppcp-disconnect').forEach(
		(button) => {
			button.addEventListener(
				'click',
				disconnect
			);
		}
	);

    document.querySelectorAll('.ppcp-onboarding-options input').forEach(
        (element) => {
            element.addEventListener('click', event => {
                updateOptionsState();

                preventDirtyCheckboxPropagation(event);
            });
        }
    );

    const isSandboxInBackend = PayPalCommerceGatewayOnboarding.current_env === 'sandbox';
    if (sandboxSwitchElement.checked !== isSandboxInBackend) {
        sandboxSwitchElement.checked = isSandboxInBackend;
    }

    updateOptionsState();

    const settingsContainer = document.querySelector('#mainform .form-table');

    const markCurrentOnboardingState = (isOnboarded) => {
        settingsContainer.classList.remove('ppcp-onboarded', 'ppcp-onboarding');
        settingsContainer.classList.add(isOnboarded ? 'ppcp-onboarded' : 'ppcp-onboarding');
    }

    markCurrentOnboardingState(PayPalCommerceGatewayOnboarding.current_state === ppcp_onboarding.STATE_ONBOARDED);

    const manualInputToggleButton = document.querySelector('#field-toggle_manual_input button');
    let isManualInputShown = PayPalCommerceGatewayOnboarding.current_state === ppcp_onboarding.STATE_ONBOARDED;

    manualInputToggleButton.addEventListener(
            'click',
            (event) => {
                event.preventDefault();

                isManualInputShown = !isManualInputShown;

                updateManualInputControls(isManualInputShown, sandboxSwitchElement.checked, isAnyEnvOnboarded);
            }
        );

    sandboxSwitchElement.addEventListener(
        'click',
        (event) => {
            const isSandbox = sandboxSwitchElement.checked;

            if (isAnyEnvOnboarded) {
                const onboardingState = isSandbox ? PayPalCommerceGatewayOnboarding.sandbox_state : PayPalCommerceGatewayOnboarding.production_state;
                const isOnboarded = onboardingState === ppcp_onboarding.STATE_ONBOARDED;

                markCurrentOnboardingState(isOnboarded);
                isManualInputShown = isOnboarded;
            }

            updateManualInputControls(isManualInputShown, isSandbox, isAnyEnvOnboarded);

            updateEnvironmentControls(isSandbox);

            preventDirtyCheckboxPropagation(event);
        }
    );

    updateManualInputControls(isManualInputShown, sandboxSwitchElement.checked, isAnyEnvOnboarded);

    updateEnvironmentControls(sandboxSwitchElement.checked);

    document.querySelector('#mainform').addEventListener('submit', e => {
        if (isDisconnecting) {
            return;
        }

        const errors = validate();
        if (errors.length) {
            e.preventDefault();

            const errorLabel = document.querySelector('#ppcp-form-errors-label');
            errorLabel.parentElement.parentElement.classList.remove('hide');

            errorLabel.innerHTML = errors.join('<br/>');

            errorLabel.scrollIntoView();
            window.scrollBy(0, -120); // WP + WC floating header
        }
    });

	// Onboarding buttons.
	ppcp_onboarding.init();
})();
