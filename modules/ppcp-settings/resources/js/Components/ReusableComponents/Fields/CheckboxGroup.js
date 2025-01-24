import { PayPalCheckbox } from './index';

const CheckboxGroup = ( { options, value, onChange } ) => {
	const handleChange = ( key, checked ) => {
		const getNewValue = () => {
			if ( checked ) {
				return [ ...value, key ];
			}
			return value.filter( ( val ) => val !== key );
		};

		onChange( getNewValue() );
	};

	return (
		<>
			{ options.map(
				( {
					value: itemValue,
					label,
					checked,
					disabled,
					description,
					tooltip,
				} ) => (
					<PayPalCheckbox
						key={ itemValue }
						value={ itemValue }
						label={ label }
						checked={ checked }
						disabled={ disabled }
						description={ description }
						tooltip={ tooltip }
						changeCallback={ handleChange }
					/>
				)
			) }
		</>
	);
};

export default CheckboxGroup;
