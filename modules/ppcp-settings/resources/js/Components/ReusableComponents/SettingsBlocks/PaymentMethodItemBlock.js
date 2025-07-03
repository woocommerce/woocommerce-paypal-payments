import { ToggleControl, Icon, Button } from '@wordpress/components';
import { cog } from '@wordpress/icons';

import SettingsBlock from '../SettingsBlock';
import PaymentMethodIcon from '../PaymentMethodIcon';
import WarningMessages from '../../../Components/Screens/Settings/Components/Payment/WarningMessages';

const PaymentMethodItemBlock = ( {
	paymentMethod,
	onTriggerModal,
	onSelect,
	isSelected,
	isDisabled,
	disabledMessage,
	warningMessages,
} ) => {
	const hasWarning =
		warningMessages && Object.keys( warningMessages ).length > 0;

	// Determine class names based on states
	const methodItemClasses = [
		'ppcp--method-item',
		isDisabled ? 'ppcp--method-item--disabled' : '',
		hasWarning && ! isDisabled ? 'ppcp--method-item--warning' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	return (
		<SettingsBlock
			id={ paymentMethod.id }
			className={ methodItemClasses }
			separatorAndGap={ false }
			aria-disabled={ isDisabled ? 'true' : 'false' }
		>
			{ isDisabled && (
				<div
					className="ppcp--method-disabled-overlay"
					role="alert"
					aria-live="polite"
				>
					<p className="ppcp--method-disabled-message" tabIndex="0">
						{ disabledMessage }
					</p>
				</div>
			) }
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
					<div className="ppcp--method-toggle-wrapper">
						<ToggleControl
							__nextHasNoMarginBottom
							checked={ isSelected }
							onChange={ onSelect }
							disabled={ isDisabled }
							aria-label={ `Enable ${ paymentMethod.itemTitle }` }
						/>
						{ hasWarning && ! isDisabled && isSelected && (
							<WarningMessages
								warningMessages={ warningMessages }
							/>
						) }
					</div>
					{ paymentMethod?.fields && onTriggerModal && (
						<Button
							className="ppcp--method-settings"
							disabled={ isDisabled }
							onClick={ onTriggerModal }
							aria-label={ `Configure ${ paymentMethod.itemTitle } settings` }
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
