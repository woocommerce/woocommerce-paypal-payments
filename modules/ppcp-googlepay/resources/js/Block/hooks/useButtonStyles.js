import { useMemo } from '@wordpress/element';
import { combineStyles } from '../../../../../ppcp-button/resources/js/modules/Helper/PaymentButtonHelpers';

const useButtonStyles = ( buttonConfig, ppcpConfig ) => {
	return useMemo( () => {
		const styles = combineStyles(
			ppcpConfig?.button || {},
			buttonConfig?.button || {}
		);

		if ( styles.MiniCart && styles.MiniCart.type === 'buy' ) {
			styles.MiniCart.type = 'pay';
		}

		return styles;
	}, [ buttonConfig, ppcpConfig ] );
};

export default useButtonStyles;
