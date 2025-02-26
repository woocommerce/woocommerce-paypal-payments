import SettingsBlock from '../SettingsBlock';
import PaymentMethodItemBlock from './PaymentMethodItemBlock';
import { PaymentHooks } from '../../../data';

// TODO: This is not a reusable component, as it's connected to the Redux store.
const PaymentMethodsBlock = ( { paymentMethods = [], onTriggerModal } ) => {
	const { changePaymentSettings } = PaymentHooks.useStore();

	const handleSelect = ( methodId, isSelected ) =>
		changePaymentSettings( methodId, {
			enabled: isSelected,
		} );

	if ( ! paymentMethods.length ) {
		return null;
	}

	return (
		<SettingsBlock className="ppcp--grid ppcp-r-settings-block__payment-methods">
			{ paymentMethods
				// Remove empty/invalid payment method entries.
				.filter( ( m ) => m && m.id )
				.map( ( paymentMethod ) => (
					<PaymentMethodItemBlock
						key={ paymentMethod.id }
						paymentMethod={ paymentMethod }
						isSelected={ paymentMethod.enabled }
						isDisabled={ paymentMethod.isDisabled }
						disabledMessage={ paymentMethod.disabledMessage }
						onSelect={ ( checked ) =>
							handleSelect( paymentMethod.id, checked )
						}
						onTriggerModal={ () =>
							onTriggerModal?.( paymentMethod.id )
						}
					/>
				) ) }
		</SettingsBlock>
	);
};

export default PaymentMethodsBlock;
