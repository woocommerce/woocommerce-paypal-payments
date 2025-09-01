import PaymentMethodIcon from './PaymentMethodIcon';

const PaymentMethodIcons = ( { icons = [] } ) => (
	<div className="ppcp-r-payment-method-icons">
		{ icons.map( ( type ) => (
			<PaymentMethodIcon key={ type } type={ type } />
		) ) }
	</div>
);

export default PaymentMethodIcons;
