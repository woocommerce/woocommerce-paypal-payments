import { ToggleControl, Icon, Button } from '@wordpress/components';
import { cog } from '@wordpress/icons';

import SettingsBlock from '../SettingsBlock';
import PaymentMethodIcon from '../PaymentMethodIcon';

const PaymentMethodItemBlock = ( {
	paymentMethod,
	onTriggerModal,
	onSelect,
	isSelected,
} ) => {
	return (
		<SettingsBlock className="ppcp--method-item" separatorAndGap={ false }>
			<div className="ppcp--method-inner">
				<div className="ppcp--method-title-wrapper">
					{ paymentMethod?.icon && (
						<PaymentMethodIcon
							icons={ [ paymentMethod.icon ] }
							type={ paymentMethod.icon }
						/>
					) }
					<span className="ppcp--method-title">
						{ paymentMethod.itemTitle }
					</span>
				</div>
				<p className="ppcp--method-description">
					{ paymentMethod.itemDescription }
				</p>
				<div className="ppcp--method-footer">
					<ToggleControl
						__nextHasNoMarginBottom
						checked={ isSelected }
						onChange={ onSelect }
					/>
					{ paymentMethod?.fields && onTriggerModal && (
						<Button
							className="ppcp--method-settings"
							onClick={ onTriggerModal }
						>
							<Icon icon={ cog } />
						</Button>
					) }
				</div>
			</div>
		</SettingsBlock>
	);
};

export default PaymentMethodItemBlock;
