import { __ } from '@wordpress/i18n';

import { ControlTextInput } from '../../../../../ReusableComponents/Controls';
import { SettingsHooks } from '../../../../../../data';
import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';

const InvoicePrefix = () => {
	const { invoicePrefix, setInvoicePrefix } = SettingsHooks.useSettings();

	return (
		<SettingsBlock
			title="Invoice Prefix"
			titleSuffix={ __( '(Recommended)', 'woocommerce-paypal-payments' ) }
			className="ppcp--invoice-prefix"
		>
			<ControlTextInput
				placeholder={ __(
					'Input prefix',
					'woocommerce-paypal-payments'
				) }
				onChange={ setInvoicePrefix }
				value={ invoicePrefix }
				description="Add a unique prefix to invoice numbers for site-specific tracking (recommended)."
			/>
		</SettingsBlock>
	);
};

export default InvoicePrefix;
