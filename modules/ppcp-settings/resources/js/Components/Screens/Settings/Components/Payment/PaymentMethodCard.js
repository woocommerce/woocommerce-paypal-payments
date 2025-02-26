import SettingsCard from '../../../../ReusableComponents/SettingsCard';
import { PaymentMethodsBlock } from '../../../../ReusableComponents/SettingsBlocks';
import usePaymentDependencyState from '../../../../../hooks/usePaymentDependencyState';
import DependencyMessage from './DependencyMessage';

/**
 * Renders a payment method card with dependency handling
 *
 * @param {Object}               props                 - Component props
 * @param {string}               props.id              - Unique identifier for the card
 * @param {string}               props.title           - Title of the payment method card
 * @param {string}               props.description     - Description of the payment method
 * @param {string}               props.icon            - Icon path for the payment method
 * @param {Array}                props.methods         - List of payment methods to display
 * @param {Object}               props.methodsMap      - Map of all payment methods by ID
 * @param {Function}             props.onTriggerModal  - Callback when a method is clicked
 * @param {boolean}              props.isDisabled      - Whether the entire card is disabled
 * @param {(string|JSX.Element)} props.disabledMessage - Message to show when disabled
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
	disabledMessage,
} ) => {
	const dependencyState = usePaymentDependencyState( methods, methodsMap );

	return (
		<SettingsCard
			id={ id }
			title={ title }
			description={ description }
			icon={ icon }
			contentContainer={ false }
		>
			<PaymentMethodsBlock
				paymentMethods={ methods.map( ( method ) => {
					const dependency = dependencyState[ method.id ];

					const dependencyMessage = dependency ? (
						<DependencyMessage
							parentId={ dependency.parentId }
							parentName={ dependency.parentName }
						/>
					) : null;

					return {
						...method,
						isDisabled:
							method.isDisabled ||
							isDisabled ||
							Boolean( dependency?.isDisabled ),
						disabledMessage:
							method.disabledMessage ||
							dependencyMessage ||
							disabledMessage,
					};
				} ) }
				onTriggerModal={ onTriggerModal }
			/>
		</SettingsCard>
	);
};

export default PaymentMethodCard;
