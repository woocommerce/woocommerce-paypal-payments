import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { Watermark } from '../Watermark';
import { STORE_NAME } from '../../stores/axoStore';

const cardIcons = {
	VISA: 'visa-light.svg',
	MASTERCARD: 'mastercard-light.svg',
	AMEX: 'amex-light.svg',
	DISCOVER: 'discover-light.svg',
	DINERS: 'dinersclub-light.svg',
	JCB: 'jcb-light.svg',
	UNIONPAY: 'unionpay-light.svg',
};

const Card = ( { fastlaneSdk, showWatermark = true } ) => {
	const { card } = useSelect(
		( select ) => ( {
			card: select( STORE_NAME ).getCardDetails(),
		} ),
		[]
	);

	const { brand, lastDigits, expiry, name } = card?.paymentSource?.card ?? {};

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
						<span>{ name }</span>
						<span>{ formattedExpiry }</span>{ ' ' }
					</div>
				</div>
				<div className="wc-block-checkout-axo-block-card__watermark">
					{ showWatermark && (
						<Watermark
							fastlaneSdk={ fastlaneSdk }
							name="wc-block-checkout-axo-card-watermark"
							includeAdditionalInfo={ false }
						/>
					) }
				</div>
			</div>
		</div>
	);
};

export default Card;
