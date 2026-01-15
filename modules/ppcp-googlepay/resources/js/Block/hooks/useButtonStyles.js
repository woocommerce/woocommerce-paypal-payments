import { useMemo } from '@wordpress/element';
import { combineStyles } from '@ppcp-button/Helper/PaymentButtonHelpers';

const useButtonStyles = ( buttonConfig, ppcpConfig, buttonAttributes ) => {
	return useMemo( () => {
		const styles = combineStyles(
			ppcpConfig?.button || {},
			buttonConfig?.button || {}
		);

		if ( buttonAttributes && styles.Default ) {
			styles.Default.height =
				buttonAttributes.height || styles.Default.height;
			styles.Default.borderRadius =
				buttonAttributes.borderRadius || styles.Default.borderRadius;
		}

		if ( styles.MiniCart?.type === 'buy' ) {
			styles.MiniCart.type = 'pay';
		}

		return styles;
	}, [ buttonConfig, ppcpConfig, buttonAttributes ] );
};

export default useButtonStyles;
