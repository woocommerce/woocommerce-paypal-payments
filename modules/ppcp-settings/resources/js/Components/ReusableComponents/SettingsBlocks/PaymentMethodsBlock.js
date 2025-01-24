import SettingsBlock from '../SettingsBlock';
import PaymentMethodItemBlock from './PaymentMethodItemBlock';
import { usePaymentMethods } from '../../../data/payment/hooks';

const PaymentMethodsBlock = ( {
	paymentMethods,
	className = '',
	onTriggerModal,
} ) => {
	const { setPersistent } = usePaymentMethods();

	if ( ! paymentMethods?.length ) {
		return null;
	}

	const handleSelect = ( paymentMethod, isSelected ) => {
		setPersistent( paymentMethod.id, {
			...paymentMethod,
			enabled: isSelected,
		} );
	};

	return (
		<SettingsBlock
			className={ `ppcp-r-settings-block__payment-methods ${ className }` }
		>
			{ paymentMethods.map( ( paymentMethod ) => (
				<PaymentMethodItemBlock
					key={ paymentMethod.id }
					paymentMethod={ paymentMethod }
					isSelected={ paymentMethod.enabled }
					onSelect={ ( checked ) =>
						handleSelect( paymentMethod, checked )
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
