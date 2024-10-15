export function APM( { config, components } ) {
	const { PaymentMethodIcons } = components;

    return (
        <div>
            <div
                className="wc-block-components-payment-method-icons wc-block-components-payment-method-icons--align-right">
                <img
                    className={`wc-block-components-payment-method-icon wc-block-components-payment-method-icon--${config.id}`}
                    src={config.icon}
                    alt={config.title}
                />
            </div>
        </div>
    );
}
