import { ToggleControl } from '@wordpress/components';

import SettingsBlock from '../SettingsBlock';
import PaymentMethodIcon from '../PaymentMethodIcon';
import data from '../../../utils/data';

const PaymentMethodItemBlock = ( {
	paymentMethod,
	onTriggerModal,
	onSelect,
	isSelected,
} ) => {
	return (
		<SettingsBlock className="ppcp-r-settings-block__payment-methods__item">
			<div className="ppcp-r-settings-block__payment-methods__item__inner">
				<div className="ppcp-r-settings-block__payment-methods__item__title-wrapper">
					<PaymentMethodIcon
						icons={ [ paymentMethod.icon ] }
						type={ paymentMethod.icon }
					/>
					<span className="ppcp-r-settings-block__payment-methods__item__title">
						{ paymentMethod.itemTitle }
					</span>
				</div>
				<p className="ppcp-r-settings-block__payment-methods__item__description">
					{ paymentMethod.itemDescription }
				</p>
				<div className="ppcp-r-settings-block__payment-methods__item__footer">
					<ToggleControl
						__nextHasNoMarginBottom={ true }
						checked={ isSelected }
						onChange={ onSelect }
					/>
					{ paymentMethod?.fields && onTriggerModal && (
						<div
							className="ppcp-r-settings-block__payment-methods__item__settings"
							onClick={ onTriggerModal }
						>
							{ data().getImage( 'icon-settings.svg' ) }
						</div>
					) }
				</div>
			</div>
		</SettingsBlock>
	);
};

export default PaymentMethodItemBlock;
