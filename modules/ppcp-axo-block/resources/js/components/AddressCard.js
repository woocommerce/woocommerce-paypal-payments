import { __ } from '@wordpress/i18n';

const AddressCard = ( { address, onEdit, isExpanded } ) => {
	const formatAddress = ( addressData ) => {
		const {
			first_name,
			last_name,
			company,
			address_1,
			address_2,
			city,
			state,
			postcode,
			country,
		} = addressData;
		const formattedAddress = [
			`${ first_name } ${ last_name }`,
			company,
			address_1,
			address_2,
			city,
			state,
			postcode,
			country,
		].filter( Boolean );

		return formattedAddress;
	};

	const formattedAddress = formatAddress( address );

	return (
		<div className="wc-block-components-axo-address-card">
			<address>
				{ formattedAddress.map( ( line, index ) => (
					<span
						key={ index }
						className="wc-block-components-axo-address-card__address-section"
					>
						{ line }
					</span>
				) ) }
				{ address.phone && (
					<div className="wc-block-components-axo-address-card__address-section">
						{ address.phone }
					</div>
				) }
			</address>
			{ onEdit && (
				<button
					className="wc-block-components-axo-address-card__edit"
					aria-controls="shipping"
					aria-expanded={ isExpanded }
					aria-label={ __( 'Edit shipping address', 'woocommerce' ) }
					onClick={ ( e ) => {
						e.preventDefault();
						onEdit();
					} }
					type="button"
				>
					{ __( 'Edit', 'woocommerce' ) }
				</button>
			) }
		</div>
	);
};

export default AddressCard;
