import { __, sprintf } from '@wordpress/i18n';

import BadgeBox, {
	BADGE_BOX_TITLE_BIG,
} from '../../../ReusableComponents/BadgeBox';
import { Separator } from '../../../ReusableComponents/Elements';
import PricingTitleBadge from '../../../ReusableComponents/PricingTitleBadge';
import OptionalPaymentMethods from './OptionalPaymentMethods';

const AcdcFlow = ( { isFastlane, isPayLater, storeCountry } ) => {
	if ( isFastlane && isPayLater && storeCountry === 'US' ) {
		return (
			<div className="ppcp-r-welcome-docs__wrapper">
				<div className="ppcp-r-welcome-docs__col">
					<BadgeBox
						title={ __(
							'PayPal Checkout',
							'woocommerce-paypal-payments'
						) }
						titleType={ BADGE_BOX_TITLE_BIG }
						textBadge={ <PricingTitleBadge item="checkout" /> }
						description={ __(
							'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximise conversion',
							'woocommerce-paypal-payments'
						) }
					/>
					<BadgeBox
						title={ __(
							'Included in PayPal Checkout',
							'woocommerce-paypal-payments'
						) }
						titleType={ BADGE_BOX_TITLE_BIG }
					/>
					<BadgeBox
						title={ __(
							'Pay with PayPal',
							'woocommerce-paypal-payments'
						) }
						imageBadge={ [ 'icon-button-paypal.svg' ] }
						description={ sprintf(
							// translators: %s: Link to PayPal business fees guide
							__(
								'Our brand recognition helps give customers the confidence to buy. <a target="_blank" href="%s">Learn more</a>',
								'woocommerce-paypal-payments'
							),
							'https://www.paypal.com/us/business/paypal-business-fees'
						) }
					/>
					<Separator className="ppcp-r-page-welcome-mode-separator" />
					<BadgeBox
						title={ __(
							'Pay Later',
							'woocommerce-paypal-payments'
						) }
						imageBadge={ [
							'icon-payment-method-paypal-small.svg',
						] }
						textBadge={ <PricingTitleBadge item="plater" /> }
						description={ sprintf(
							// translators: %s: Link to PayPal business fees guide
							__(
								'Offer installment payment options and get paid upfront. <a target="_blank" href="%s">Learn more</a>',
								'woocommerce-paypal-payments'
							),
							'https://www.paypal.com/us/business/paypal-business-fees'
						) }
					/>
					<Separator className="ppcp-r-page-welcome-mode-separator" />
					<BadgeBox
						title={ __( 'Venmo', 'woocommerce-paypal-payments' ) }
						imageBadge={ [ 'icon-button-venmo.svg' ] }
						description={ sprintf(
							// translators: %s: Link to PayPal business fees guide
							__(
								'Automatically offer Venmo checkout to millions of active users. <a target="_blank" href="%s">Learn more</a>',
								'woocommerce-paypal-payments'
							),
							'https://www.paypal.com/us/business/paypal-business-fees'
						) }
					/>
					<Separator className="ppcp-r-page-welcome-mode-separator" />
					<BadgeBox
						title={ __( 'Crypto', 'woocommerce-paypal-payments' ) }
						imageBadge={ [ 'icon-payment-method-crypto.svg' ] }
						description={ sprintf(
							// translators: %s: Link to PayPal business fees guide
							__(
								'Let customers checkout with Crypto while you get paid in cash. <a target="_blank" href="%s">Learn more</a>',
								'woocommerce-paypal-payments'
							),
							'https://www.paypal.com/us/business/paypal-business-fees'
						) }
					/>
				</div>
				<div className="ppcp-r-welcome-docs__col">
					<BadgeBox
						title={ __(
							'Expanded Checkout',
							'woocommerce-paypal-payments'
						) }
						titleType={ BADGE_BOX_TITLE_BIG }
						description={ __(
							'Accept debit/credit cards, PayPal, Apple Pay, Google Pay, and more. Note: Additional application required for more methods',
							'woocommerce-paypal-payments'
						) }
					/>
					<OptionalPaymentMethods
						useAcdc={ true }
						isFastlane={ isFastlane }
						isPayLater={ isPayLater }
						storeCountry={ storeCountry }
					/>
				</div>
			</div>
		);
	}

	if ( isPayLater && storeCountry === 'UK' ) {
		return (
			<div className="ppcp-r-welcome-docs__wrapper">
				<div className="ppcp-r-welcome-docs__col">
					<BadgeBox
						title={ __(
							'PayPal Checkout',
							'woocommerce-paypal-payments'
						) }
						titleType={ BADGE_BOX_TITLE_BIG }
						textBadge={ <PricingTitleBadge item="checkout" /> }
						description={ __(
							'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximise conversion',
							'woocommerce-paypal-payments'
						) }
					/>
					<BadgeBox
						title={ __(
							'Included in PayPal Checkout',
							'woocommerce-paypal-payments'
						) }
						titleType={ BADGE_BOX_TITLE_BIG }
					/>
					<BadgeBox
						title={ __(
							'Pay with PayPal',
							'woocommerce-paypal-payments'
						) }
						imageBadge={ [ 'icon-button-paypal.svg' ] }
						description={ sprintf(
							// translators: %s: Link to PayPal REST application guide
							__(
								'Our brand recognition helps give customers the confidence to buy. <a target="_blank" href="%s">Learn more</a>',
								'woocommerce-paypal-payments'
							),
							'https://woocommerce.com/document/woocommerce-paypal-payments/#manual-credential-input '
						) }
					/>
					<Separator className="ppcp-r-page-welcome-mode-separator" />
					<BadgeBox
						title={ __(
							'Pay in 3',
							'woocommerce-paypal-payments'
						) }
						imageBadge={ [
							'icon-payment-method-paypal-small.svg',
						] }
						description={ sprintf(
							// translators: %s: Link to PayPal REST application guide
							__(
								'Offer installment payment options and get paid upfront - at no extra cost to you. <a target="_blank" href="%s">Learn more</a>',
								'woocommerce-paypal-payments'
							),
							'https://woocommerce.com/document/woocommerce-paypal-payments/#manual-credential-input '
						) }
					/>
				</div>
				<div className="ppcp-r-welcome-docs__col">
					<BadgeBox
						title={ __(
							'Optional payment methods',
							'woocommerce-paypal-payments'
						) }
						titleType={ BADGE_BOX_TITLE_BIG }
						description={ __(
							'with additional application',
							'woocommerce-paypal-payments'
						) }
					/>
					<OptionalPaymentMethods
						useAcdc={ true }
						isFastlane={ isFastlane }
						isPayLater={ isPayLater }
						storeCountry={ storeCountry }
					/>
				</div>
			</div>
		);
	}

	return (
		<div className="ppcp-r-welcome-docs__wrapper">
			<div className="ppcp-r-welcome-docs__col">
				<BadgeBox
					title={ __(
						'PayPal Checkout',
						'woocommerce-paypal-payments'
					) }
					titleType={ BADGE_BOX_TITLE_BIG }
					textBadge={ <PricingTitleBadge item="checkout" /> }
					description={ __(
						'Our all-in-one checkout solution lets you offer PayPal, Venmo, Pay Later options, and more to help maximise conversion',
						'woocommerce-paypal-payments'
					) }
				/>
				<BadgeBox
					title={ __(
						'Included in PayPal Checkout',
						'woocommerce-paypal-payments'
					) }
					titleType={ BADGE_BOX_TITLE_BIG }
				/>
				<BadgeBox
					title={ __(
						'Pay with PayPal',
						'woocommerce-paypal-payments'
					) }
					imageBadge={ [ 'icon-button-paypal.svg' ] }
					description={ sprintf(
						// translators: %s: Link to PayPal REST application guide
						__(
							'Our brand recognition helps give customers the confidence to buy. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://woocommerce.com/document/woocommerce-paypal-payments/#manual-credential-input '
					) }
				/>
				<Separator className="ppcp-r-page-welcome-mode-separator" />
				<BadgeBox
					title={ __( 'Pay Later', 'woocommerce-paypal-payments' ) }
					imageBadge={ [ 'icon-payment-method-paypal-small.svg' ] }
					description={ sprintf(
						// translators: %s: Link to PayPal REST application guide
						__(
							'Offer installment payment options and get paid upfront. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://woocommerce.com/document/woocommerce-paypal-payments/#manual-credential-input '
					) }
				/>
			</div>
			<div className="ppcp-r-welcome-docs__col">
				<BadgeBox
					title={ __(
						'Optional payment methods',
						'woocommerce-paypal-payments'
					) }
					titleType={ BADGE_BOX_TITLE_BIG }
					description={ __(
						'with additional application',
						'woocommerce-paypal-payments'
					) }
				/>
				<OptionalPaymentMethods
					useAcdc={ true }
					isFastlane={ isFastlane }
					isPayLater={ isPayLater }
					storeCountry={ storeCountry }
				/>
			</div>
		</div>
	);
};

export default AcdcFlow;
