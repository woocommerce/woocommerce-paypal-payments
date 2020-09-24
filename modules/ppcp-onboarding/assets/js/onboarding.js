function onboardingCallback(authCode, sharedId) {
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
					nonce: PayPalCommerceGatewayOnboarding.nonce
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

const sandboxSwitch = (element) => {

	const toggleConnectButtons = (showProduction) => {
		if (showProduction) {
			document.querySelector('#connect-to-production').style.display = '';
			document.querySelector('#connect-to-sandbox').style.display = 'none';
			return;
		}
		document.querySelector('#connect-to-production').style.display = 'none';
		document.querySelector('#connect-to-sandbox').style.display = '';
	}
	toggleConnectButtons(! element.checked);

	element.addEventListener(
		'change',
		(event) => {
			toggleConnectButtons(! element.checked);
		}
	);
};

(() => {
	const sandboxSwitchElement = document.querySelector('#ppcp-sandbox_on');
	if (sandboxSwitchElement) {
		sandboxSwitch(sandboxSwitchElement);
	}
})();