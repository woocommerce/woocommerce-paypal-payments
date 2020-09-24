function onboardingCallback(authCode, sharedId) {
	const sandboxSwitchElement = document.querySelector('#ppcp-sandbox_on')
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
					env: sandboxSwitchElement && sandboxSwitchElement.checked ? 'sandbox' : 'production'
				}
			)
		}
	)
		.then( response => response.json() )
		.then(
			(data) => {
				if (data.success) {
					return;
				}
				alert( PayPalCommerceGatewayOnboarding.error )
			}
		);
}

/**
 * Since the PayPal modal will redirect the user a dirty form
 * provokes an alert if the user wants to leave the page. Since the user
 * needs to toggle the sandbox switch, we disable this dirty state with the
 * following workaround for checkboxes.
 *
 * @param event
 */
const checkBoxOnClick = (event) => {
	const value = event.target.checked;
	if (event.target.getAttribute('id') === 'ppcp-sandbox_on') {
		toggleConnectButtons(! value);
	}
	event.preventDefault();
	event.stopPropagation();
	setTimeout( () => {
		event.target.checked = value;
		},1
	);
}

const toggleConnectButtons = (showProduction) => {
	if (showProduction) {
		document.querySelector('#connect-to-production').style.display = '';
		document.querySelector('#connect-to-sandbox').style.display = 'none';
		return;
	}
	document.querySelector('#connect-to-production').style.display = 'none';
	document.querySelector('#connect-to-sandbox').style.display = '';
}

(() => {
	const sandboxSwitchElement = document.querySelector('#ppcp-sandbox_on');
	if (sandboxSwitchElement) {
		toggleConnectButtons(! sandboxSwitchElement.checked);
	}

	document.querySelectorAll('#mainform input[type="checkbox"]').forEach(
		(checkbox) => {
			checkbox.addEventListener('click', checkBoxOnClick);
		}
	);

})();