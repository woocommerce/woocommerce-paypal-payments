import PaymentMethodIcon from './PaymentMethodIcon';

const PaymentMethodIcons = ( props ) => {
	return (
		<div className="ppcp-r-payment-method-icons">
			<PaymentMethodIcon type="paypal" icons={ props.icons } />
			<PaymentMethodIcon type="venmo" icons={ props.icons } />
			<PaymentMethodIcon type="visa" icons={ props.icons } />
			<PaymentMethodIcon type="mastercard" icons={ props.icons } />
			<PaymentMethodIcon type="amex" icons={ props.icons } />
			<PaymentMethodIcon type="discover" icons={ props.icons } />
			<PaymentMethodIcon type="apple-pay" icons={ props.icons } />
			<PaymentMethodIcon type="google-pay" icons={ props.icons } />
			<PaymentMethodIcon type="sepa" icons={ props.icons } />
			<PaymentMethodIcon type="ideal" icons={ props.icons } />
			<PaymentMethodIcon type="bancontact" icons={ props.icons } />
		</div>
	);
};

export default PaymentMethodIcons;
