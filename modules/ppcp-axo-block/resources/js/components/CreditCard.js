import { useMemo } from '@wordpress/element';
import { FastlaneWatermark } from './FastlaneWatermark';

const cardIcons = {
	VISA: 'visa-light.svg',
	MASTER_CARD: 'mastercard-light.svg',
	AMEX: 'amex-light.svg',
	DISCOVER: 'discover-light.svg',
	DINERS: 'dinersclub-light.svg',
	JCB: 'jcb-light.svg',
	UNIONPAY: 'unionpay-light.svg',
};

export const CreditCard = ( { card, shippingAddress, fastlaneSdk } ) => {
	const { brand, lastDigits, expiry } = card?.paymentSource?.card ?? {};
	const { fullName } = shippingAddress?.name ?? {};

	const cardLogo = useMemo( () => {
		return cardIcons[ brand ] ? (
			<img
				className="wc-block-axo-block-card__meta-icon"
				title={ brand }
				src={ `${ window.wc_ppcp_axo.icons_directory }${ cardIcons[ brand ] }` }
				alt={ brand }
			/>
		) : (
			<span>{ brand }</span>
		);
	}, [ brand ] );

	const formattedExpiry = expiry
		? `${ expiry.split( '-' )[ 1 ] }/${ expiry.split( '-' )[ 0 ] }`
		: '';

	return (
		<div className="wc-block-checkout-axo-block-card">
			<div className="wc-block-checkout-axo-block-card__inner">
				<div className="wc-block-checkout-axo-block-card__content">
					<div className="wc-block-checkout-axo-block-card__meta">
						<span className="wc-block-checkout-axo-block-card__meta-digits">
							{ `**** **** **** ${ lastDigits }` }
						</span>
						{ cardLogo }
					</div>
					<div className="wc-block-checkout-axo-block-card__meta">
						<span>{ fullName }</span>
						<span>{ formattedExpiry }</span>{ ' ' }
					</div>
				</div>
				<div className="wc-block-checkout-axo-block-card__watermark">
					<FastlaneWatermark
						fastlaneSdk={ fastlaneSdk }
						name="wc-block-checkout-axo-card-watermark"
						includeAdditionalInfo={ false }
					/>
				</div>
			</div>
		</div>
	);
};
