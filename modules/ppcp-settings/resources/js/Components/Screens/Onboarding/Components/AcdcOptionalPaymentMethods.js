import { __, sprintf } from '@wordpress/i18n';

import BadgeBox from '../../../ReusableComponents/BadgeBox';
import { Separator } from '../../../ReusableComponents/Elements';
import PricingTitleBadge from '../../../ReusableComponents/PricingTitleBadge';

const AcdcOptionalPaymentMethods = ( {
	isFastlane,
	isPayLater,
	storeCountry,
} ) => {
	if ( isFastlane && isPayLater && storeCountry === 'US' ) {
		return (
			<div className="ppcp-r-optional-payment-methods__wrapper">
				<BadgeBox
					title={ __(
						'Custom Card Fields',
						'woocommerce-paypal-payments'
					) }
					imageBadge={ [
						'icon-button-visa.svg',
						'icon-button-mastercard.svg',
						'icon-button-amex.svg',
						'icon-button-discover.svg',
					] }
					textBadge={ <PricingTitleBadge item="ccf" /> }
					description={ sprintf(
						// translators: %s: Link to PayPal business fees guide
						__(
							'Style the credit card fields to match your own style. Includes advanced processing with risk management, 3D Secure, fraud protection options, and chargeback protection. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://www.paypal.com/us/business/paypal-business-fees'
					) }
				/>
				<Separator className="ppcp-r-optional-payment-methods__separator" />
				<BadgeBox
					title={ __(
						'Digital Wallets',
						'woocommerce-paypal-payments'
					) }
					imageBadge={ [
						'icon-button-apple-pay.svg',
						'icon-button-google-pay.svg',
					] }
					textBadge={ <PricingTitleBadge item="dw" /> }
					description={ sprintf(
						// translators: %s: Link to PayPal business fees guide
						__(
							'Accept Apple Pay on eligible devices and Google Pay through mobile and web. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://www.paypal.com/us/business/paypal-business-fees'
					) }
				/>
				<Separator className="ppcp-r-optional-payment-methods__separator" />
				<BadgeBox
					title={ __(
						'Alternative Payment Methods',
						'woocommerce-paypal-payments'
					) }
					imageBadge={ [
						'icon-button-ideal.svg',
						'icon-button-blik.svg',
						'icon-button-bancontact.svg',
					] }
					textBadge={ <PricingTitleBadge item="apm" /> }
					description={ sprintf(
						// translators: %s: Link to PayPal business fees guide
						__(
							'Seamless payments for customers across the globe using their preferred payment methods. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://www.paypal.com/us/business/paypal-business-fees'
					) }
				/>
				<Separator className="ppcp-r-optional-payment-methods__separator" />
				<BadgeBox
					title={ __( '', 'woocommerce-paypal-payments' ) }
					imageBadge={ [ 'icon-payment-method-fastlane-small.svg' ] }
					textBadge={
						<PricingTitleBadge item="fast country currency=storeCurrency=storeCountrylane" />
					}
					description={ sprintf(
						// translators: %s: Link to PayPal business fees guide
						__(
							'Speed up guest checkout with Fatslane. Link a customer\'s email address to their payment details. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://www.paypal.com/us/business/paypal-business-fees'
					) }
				/>
			</div>
		);
	}

	if ( isPayLater && storeCountry === 'uk' ) {
		return (
			<div className="ppcp-r-optional-payment-methods__wrapper">
				<BadgeBox
					title={ __(
						'Custom Card Fields',
						'woocommerce-paypal-payments'
					) }
					imageBadge={ [
						'icon-button-visa.svg',
						'icon-button-mastercard.svg',
						'icon-button-amex.svg',
						'icon-button-discover.svg',
					] }
					textBadge={ <PricingTitleBadge item="ccf" /> }
					description={ sprintf(
						// translators: %s: Link to PayPal business fees guide
						__(
							'Style the credit card fields to match your own style. Includes advanced processing with risk management, 3D Secure, fraud protection options, and chargeback protection. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://www.paypal.com/us/business/paypal-business-fees'
					) }
				/>
				<Separator className="ppcp-r-optional-payment-methods__separator" />
				<BadgeBox
					title={ __(
						'Digital Wallets',
						'woocommerce-paypal-payments'
					) }
					imageBadge={ [
						'icon-button-apple-pay.svg',
						'icon-button-google-pay.svg',
					] }
					textBadge={ <PricingTitleBadge item="dw" /> }
					description={ sprintf(
						// translators: %s: Link to PayPal business fees guide
						__(
							'Accept Apple Pay on eligible devices and Google Pay through mobile and web. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://www.paypal.com/us/business/paypal-business-fees'
					) }
				/>
				<Separator className="ppcp-r-optional-payment-methods__separator" />
				<BadgeBox
					title={ __(
						'Alternative Payment Methods',
						'woocommerce-paypal-payments'
					) }
					imageBadge={ [
						// 'icon-button-sepa.svg',
						'icon-button-ideal.svg',
						'icon-button-blik.svg',
						'icon-button-bancontact.svg',
					] }
					textBadge={ <PricingTitleBadge item="apm" /> }
					description={ sprintf(
						// translators: %s: Link to PayPal business fees guide
						__(
							'Seamless payments for customers across the globe using their preferred payment methods. <a target="_blank" href="%s">Learn more</a>',
							'woocommerce-paypal-payments'
						),
						'https://www.paypal.com/us/business/paypal-business-fees'
					) }
				/>
			</div>
		);
	}

	return (
		<div className="ppcp-r-optional-payment-methods__wrapper">
			<BadgeBox
				title={ __(
					'Custom Card Fields',
					'woocommerce-paypal-payments'
				) }
				imageBadge={ [
					'icon-button-visa.svg',
					'icon-button-mastercard.svg',
					'icon-button-amex.svg',
					'icon-button-discover.svg',
				] }
				textBadge={ <PricingTitleBadge item="ccf" /> }
				description={ sprintf(
					// translators: %s: Link to PayPal business fees guide
					__(
						'Style the credit card fields to match your own style. Includes advanced processing with risk management, 3D Secure, fraud protection options, and chargeback protection. <a target="_blank" href="%s">Learn more</a>',
						'woocommerce-paypal-payments'
					),
					'https://www.paypal.com/us/business/paypal-business-fees'
				) }
			/>
			<Separator className="ppcp-r-optional-payment-methods__separator" />
			<BadgeBox
				title={ __( 'Digital Wallets', 'woocommerce-paypal-payments' ) }
				imageBadge={ [
					'icon-button-apple-pay.svg',
					'icon-button-google-pay.svg',
				] }
				textBadge={ <PricingTitleBadge item="dw" /> }
				description={ sprintf(
					// translators: %s: Link to PayPal business fees guide
					__(
						'Accept Apple Pay on eligible devices and Google Pay through mobile and web. <a target="_blank" href="%s">Learn more</a>',
						'woocommerce-paypal-payments'
					),
					'https://www.paypal.com/us/business/paypal-business-fees'
				) }
			/>
			<Separator className="ppcp-r-optional-payment-methods__separator" />
			<BadgeBox
				title={ __(
					'Alternative Payment Methods',
					'woocommerce-paypal-payments'
				) }
				imageBadge={ [
					// 'icon-button-sepa.svg',
					'icon-button-ideal.svg',
					'icon-button-blik.svg',
					'icon-button-bancontact.svg',
				] }
				textBadge={ <PricingTitleBadge item="apm" /> }
				description={ sprintf(
					// translators: %s: Link to PayPal business fees guide
					__(
						'Seamless payments for customers across the globe using their preferred payment methods. <a target="_blank" href="%s">Learn more</a>',
						'woocommerce-paypal-payments'
					),
					'https://www.paypal.com/us/business/paypal-business-fees'
				) }
			/>
		</div>
	);
};

export default AcdcOptionalPaymentMethods;
