import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { ControlTextInput } from '@ppcp-settings/Components/ReusableComponents/Controls';
import { SettingsHooks } from '@ppcp-settings/data';
import SettingsBlock from '@ppcp-settings/Components/ReusableComponents/SettingsBlock';

// The prefix is an identity component of the IDs sent to PayPal, so keep it to
// characters that are safe in every consumer.
const ALLOWED_PREFIX = /^[A-Za-z0-9_-]*$/;

const InvoicePrefix = () => {
	const { invoicePrefix, setInvoicePrefix } = SettingsHooks.useSettings();
	const [ error, setError ] = useState( '' );

	const handleChange = ( value ) => {
		// Refuse the input rather than silently dropping the offending
		// characters, so the merchant is never left with a value they did not
		// type.
		if ( ! ALLOWED_PREFIX.test( value ) ) {
			setError(
				__(
					'Only letters, numbers, hyphens and underscores are allowed.',
					'woocommerce-paypal-payments'
				)
			);
			return;
		}

		setInvoicePrefix( value );
	};

	// Keep the message up while the merchant is still typing. Clearing it on the
	// next accepted character would hide the fact that something was refused,
	// leaving them with a value they did not type and no explanation.
	const handleBlur = () => setError( '' );

	return (
		<SettingsBlock
			title={ __( 'Invoice Prefix', 'woocommerce-paypal-payments' ) }
			titleSuffix={ __( '(Recommended)', 'woocommerce-paypal-payments' ) }
			className="ppcp--invoice-prefix"
		>
			<ControlTextInput
				placeholder={ __(
					'Input prefix',
					'woocommerce-paypal-payments'
				) }
				onChange={ handleChange }
				onBlur={ handleBlur }
				value={ invoicePrefix }
				error={ error }
				description={ __(
					'Add a unique prefix to invoice numbers for site-specific tracking (recommended).',
					'woocommerce-paypal-payments'
				) }
			/>
		</SettingsBlock>
	);
};

export default InvoicePrefix;
