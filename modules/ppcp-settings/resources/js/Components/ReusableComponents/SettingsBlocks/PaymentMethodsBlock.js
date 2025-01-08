import SettingsBlock from './SettingsBlock';
import PaymentMethodItemBlock from './PaymentMethodItemBlock';

const PaymentMethodsBlock = ( {
	paymentMethods,
	className = '',
	onTriggerModal,
} ) => {
	if ( paymentMethods?.length === 0 ) {
		return null;
	}

	return (
		<SettingsBlock
			className={ `ppcp-r-settings-block__payment-methods ${ className }` }
		>
			{ paymentMethods?.map( ( paymentMethod ) => (
				<PaymentMethodItemBlock
					key={ paymentMethod.id }
					{ ...paymentMethod }
					onTriggerModal={ () =>
						onTriggerModal?.( paymentMethod.id )
					}
				/>
			) ) }
		</SettingsBlock>
	);
};

export default PaymentMethodsBlock;
