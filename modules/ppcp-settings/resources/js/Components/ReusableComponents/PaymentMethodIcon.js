import { Icon } from '@wordpress/components';

const imageUrl = ( name ) => {
	const filename = `icon-button-${ name }.svg`;
	const pathToImages = global.ppcpSettings.assets.imagesUrl;
	const className = '';

	return (
		<img className={ className } alt="" src={ pathToImages + filename } />
	);
};

const PaymentMethodIcon = ( { type } ) => (
	<Icon icon={ imageUrl( type ) } className="ppcp--method-icon" />
);

export default PaymentMethodIcon;
