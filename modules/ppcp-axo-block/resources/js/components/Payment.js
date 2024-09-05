import { useEffect } from '@wordpress/element';

const cardIcons = {
	VISA: 'visa-light.svg',
	MASTER_CARD: 'mastercard-light.svg',
	AMEX: 'amex-light.svg',
	DISCOVER: 'discover-light.svg',
	DINERS: 'dinersclub-light.svg',
	JCB: 'jcb-light.svg',
	UNIONPAY: 'unionpay-light.svg',
};

export const Payment = ( {
	fastlaneSdk,
	card,
	shippingAddress,
	isGuestFlow,
	onPaymentLoad,
} ) => {
	const { brand, lastDigits, expiry } = card?.paymentSource?.card ?? {};

	const { fullName } = shippingAddress?.name ?? {};

	useEffect( () => {
		if ( isGuestFlow ) {
			( async () => {
				const paymentComponent =
					await fastlaneSdk.FastlaneCardComponent( {} );
				paymentComponent.render( `#fastlane-card` );
				onPaymentLoad( paymentComponent );
			} )();
		}
	}, [ fastlaneSdk, onPaymentLoad, isGuestFlow ] );

	const cardLogo = cardIcons[ brand ] ? (
		<img
			className="wc-block-axo-block-card__meta-icon"
			title={ brand }
			src={ `${ window.wc_ppcp_axo.icons_directory }${ cardIcons[ brand ] }` }
			alt={ brand }
		/>
	) : (
		<span>{ brand }</span>
	);

	const formattedExpiry = expiry
		? `${ expiry.split( '-' )[ 1 ] }/${ expiry.split( '-' )[ 0 ] }`
		: '';

	return isGuestFlow ? (
		<div id="fastlane-card" key="fastlane-card" />
	) : (
		<div key="custom-card" className="wc-block-checkout-axo-block-card">
			<div className="wc-block-checkout-axo-block-card__meta-container">
				<div className="wc-block-axo-block-card__meta">
					<span className="wc-block-axo-block-card__meta__digits">{ `**** **** **** ${ lastDigits }` }</span>
					{ cardLogo }
				</div>
				<div className="wc-block-axo-block-card__meta">
					<span>{ fullName }</span>
					<span>{ formattedExpiry }</span>{ ' ' }
				</div>
			</div>
		</div>
	);
};
