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
