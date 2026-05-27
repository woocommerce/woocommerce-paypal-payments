import { useEffect } from '@wordpress/element';
import SettingsCard from '@ppcp-settings/Components/ReusableComponents/SettingsCard';
import { PaymentMethodsBlock } from '@ppcp-settings/Components/ReusableComponents/SettingsBlocks';
import usePaymentDependencyState from '@ppcp-settings/hooks/usePaymentDependencyState';
import useSettingDependencyState from '@ppcp-settings/hooks/useSettingDependencyState';
import usePaymentMethodsToggle from '@ppcp-settings/hooks/usePaymentMethodsToggle';
import useDependencyMessages from '@ppcp-settings/hooks/useDependencyMessages';
import useMethodWarnings from '@ppcp-settings/hooks/useMethodWarnings';
import BulkPaymentToggle from './BulkPaymentToggle';
import SpinnerOverlay from '@ppcp-settings/Components/ReusableComponents/SpinnerOverlay';
import {
	PaymentHooks,
	SettingsHooks,
	OnboardingHooks,
} from '@ppcp-settings/data';
import { useNavigation } from '@ppcp-settings/hooks/useNavigation';
import usePaymentGatewayRefresh from '@ppcp-settings/hooks/usePaymentGatewayRefresh';

/**
 * Renders a payment method card with dependency handling
 *
 * @param {Object}   props                - Component props
 * @param {string}   props.id             - Unique identifier for the card
 * @param {string}   props.title          - Title of the payment method card
 * @param {string}   props.description    - Description of the payment method
 * @param {string}   props.icon           - Icon path for the payment method
 * @param {Array}    props.methods        - List of payment methods to display
 * @param {Object}   props.methodsMap     - Map of all payment methods by ID
 * @param {Function} props.onTriggerModal - Callback when a method is clicked
 * @param {boolean}  props.isDisabled     - Whether the entire card is disabled
 * @param {boolean}  props.showBulkToggle - Whether to show the bulk toggle option
 * @param {string}   props.groupName      - Name of the payment method group for the toggle label
 * @return {JSX.Element} The rendered component
 */
const PaymentMethodCard = ( {
	id,
	title,
	description,
	icon,
	methods,
	methodsMap = {},
	onTriggerModal,
	isDisabled = false,
	showBulkToggle = false,
	groupName = '',
} ) => {
	const { isReady: isPaymentStoreReady, changePaymentSettings } =
		PaymentHooks.useStore();
	const { isReady: isSettingsStoreReady } = SettingsHooks.useStore();
	const { handleHighlightFromUrl } = useNavigation();
	const { gatewaysRefreshed } = OnboardingHooks.useGatewayRefresh();

	// Re-fetch payment gateway data to hide methods based on exclusion conditions.
	usePaymentGatewayRefresh();

	const paymentDependencies = usePaymentDependencyState(
		methods,
		methodsMap
	);

	const settingDependencies = useSettingDependencyState( methods );

	const dependencyMessagesMap = useDependencyMessages(
		methods,
		paymentDependencies,
		settingDependencies,
		isDisabled
	);

	// Initialize the bulk toggle functionality.
	const { allEnabled, toggleAllMethods, methodCount } =
		usePaymentMethodsToggle( {
			methods,
			methodsMap,
			changePaymentSettings,
			paymentDependencies,
			settingDependencies,
			additionalDeps: [ isDisabled, gatewaysRefreshed ],
			groupName,
		} );

	// Evaluate reactive warning visibility conditions against store data.
	const methodsWithWarnings = useMethodWarnings( methods );

	useEffect( () => {
		if ( isPaymentStoreReady && isSettingsStoreReady ) {
			handleHighlightFromUrl();
		}
	}, [ handleHighlightFromUrl, isPaymentStoreReady, isSettingsStoreReady ] );

	if (
		! isPaymentStoreReady ||
		! isSettingsStoreReady ||
		! gatewaysRefreshed
	) {
		return <SpinnerOverlay asModal={ true } />;
	}

	// Process methods with dependencies from the pre-computed map.
	const processedMethods = methodsWithWarnings.map( ( method ) => {
		const dependencyInfo = dependencyMessagesMap[ method.id ] || {};

		return {
			...method,
			isDisabled:
				dependencyInfo.isMethodDisabled ||
				method.isDisabled ||
				isDisabled,
			disabledMessage: dependencyInfo.dependencyMessage,
		};
	} );

	const descriptionWithToggle = showBulkToggle ? (
		<div>
			<p>{ description }</p>
			<BulkPaymentToggle
				isEnabled={ allEnabled }
				onToggle={ toggleAllMethods }
				isDisabled={ isDisabled || methodCount === 0 }
				groupName={ groupName }
				methodCount={ methodCount }
			/>
		</div>
	) : (
		description
	);

	return (
		<SettingsCard
			id={ id }
			title={ title }
			description={ descriptionWithToggle }
			icon={ icon }
			contentContainer={ false }
		>
			<PaymentMethodsBlock
				paymentMethods={ processedMethods }
				onTriggerModal={ onTriggerModal }
			/>
		</SettingsCard>
	);
};

export default PaymentMethodCard;
