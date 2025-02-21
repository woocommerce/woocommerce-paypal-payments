import { ToggleControl, Icon, Button } from '@wordpress/components';
import { cog } from '@wordpress/icons';
import { useEffect } from '@wordpress/element';
import { useActiveHighlight } from '../../../data/common/hooks';

import SettingsBlock from '../SettingsBlock';
import PaymentMethodIcon from '../PaymentMethodIcon';

const PaymentMethodItemBlock = ( {
	paymentMethod,
	onTriggerModal,
	onSelect,
	isSelected,
} ) => {
	const { activeHighlight, setActiveHighlight } = useActiveHighlight();
	const isHighlighted = activeHighlight === paymentMethod.id;

	// Reset the active highlight after 2 seconds
	useEffect( () => {
		if ( isHighlighted ) {
			const timer = setTimeout( () => {
				setActiveHighlight( null );
			}, 2000 );

			return () => clearTimeout( timer );
		}
	}, [ isHighlighted, setActiveHighlight ] );

	return (
		<SettingsBlock
			id={ paymentMethod.id }
			className={ `ppcp--method-item ${
				isHighlighted ? 'ppcp-highlight' : ''
			}` }
			separatorAndGap={ false }
		>
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
