import OnboardingHeader from '../../ReusableComponents/OnboardingHeader.js';
import { __, sprintf } from '@wordpress/i18n';
import { Button, TextControl } from '@wordpress/components';
import PaymentMethodIcons from '../../ReusableComponents/PaymentMethodIcons';
import SettingsToggleBlock from '../../ReusableComponents/SettingsToggleBlock';
import Separator from '../../ReusableComponents/Separator';
import { useOnboardingDetails } from '../../../data';
import DataStoreControl from '../../ReusableComponents/DataStoreControl';

const StepWelcome = () => {
	return (
		<div className="ppcp-r-page-welcome">
			<OnboardingHeader
				title={ __(
					'Welcome to PayPal Payments',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Your all-in-one checkout solution with PayPal, Venmo, Pay Later, all major credit/debit cards, Apple Pay, Google Pay, and more.',
					'woocommerce-paypal-payments'
				) }
			/>
			<div className="ppcp-r-inner-container">
				<PaymentMethodIcons icons="all" />
				<WelcomeFeatures />
				<Button
					className="ppcp-r-button-activate-paypal"
					variant="primary"
				>
					{ __(
						'Activate PayPal Payments',
						'woocommerce-paypal-payments'
					) }
				</Button>
				<Separator
					className="ppcp-r-page-welcome-or-separator"
					text={ __( 'or', 'woocommerce-paypal-payments' ) }
				/>
				<WelcomeForm />
			</div>
		</div>
	);
};

const WelcomeFeatures = () => {
	return (
		<div className="ppcp-r-welcome-features">
			<div className="ppcp-r-welcome-features__col">
				<span>{ __( 'Deposits', 'woocommerce-paypal-payments' ) }</span>
				<p>{ __( 'Instant', 'woocommerce-paypal-payments' ) }</p>
			</div>
			<div className="ppcp-r-welcome-features__col">
				<span>
					{ __( 'Payment Capture', 'woocommerce-paypal-payments' ) }
				</span>
				<p>
					{ __(
						'Authorize only or Capture',
						'woocommerce-paypal-payments'
					) }
				</p>
			</div>
			<div className="ppcp-r-welcome-features__col">
				<span>
					{ __(
						'Recurring payments',
						'woocommerce-paypal-payments'
					) }
				</span>
				<p>{ __( 'Supported', 'woocommerce-paypal-payments' ) }</p>
			</div>
		</div>
	);
};

const WelcomeForm = () => {
	const {
		isSandboxMode,
		setSandboxMode,
		isManualConnectionMode,
		setManualConnectionMode,
		clientId,
		setClientId,
		clientSecret,
		setClientSecret,
	} = useOnboardingDetails();

	const advancedUsersDescription = sprintf(
		// translators: %s: Link to PayPal REST application guide
		__(
			'For advanced users: Connect a custom PayPal REST app for full control over your integration. For more information on creating a PayPal REST application, <a href="%s">click here</a>.',
			'woocommerce-paypal-payments'
		),
		'#'
	);

	return (
		<>
			<SettingsToggleBlock
				label={ __(
					'Enable Sandbox Mode',
					'woocommerce-paypal-payments'
				) }
				description={ __(
					'Activate Sandbox mode to safely test PayPal with sample data. Once your store is ready to go live, you can easily switch to your production account.',
					'woocommerce-paypal-payments'
				) }
				isToggled={ !! isSandboxMode }
				setToggled={ setSandboxMode }
			>
				<Button variant="secondary">
					{ __( 'Connect Account', 'woocommerce-paypal-payments' ) }
				</Button>
			</SettingsToggleBlock>
			<Separator className="ppcp-r-page-welcome-mode-separator" />
			<SettingsToggleBlock
				label={ __(
					'Manually Connect - TODO missing link',
					'woocommerce-paypal-payments'
				) }
				description={ advancedUsersDescription }
				isToggled={ !! isManualConnectionMode }
				setToggled={ setManualConnectionMode }
			>
				<DataStoreControl
					control={ TextControl }
					label={ __(
						'Sandbox Client ID',
						'woocommerce-paypal-payments'
					) }
					value={ clientId }
					onChange={ setClientId }
				/>
				<DataStoreControl
					control={ TextControl }
					label={ __(
						'Sandbox Secret Key',
						'woocommerce-paypal-payments'
					) }
					value={ clientSecret }
					onChange={ setClientSecret }
					type="password"
				/>
				<Button variant="secondary">
					{ __( 'Connect Account', 'woocommerce-paypal-payments' ) }
				</Button>
			</SettingsToggleBlock>
		</>
	);
};

export default StepWelcome;
