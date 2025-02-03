import classNames from 'classnames';
import { PayPalCheckbox, PayPalRdb } from './index';

const OptionSelector = ( {
	multiSelect = false,
	options,
	value,
	onChange,
} ) => (
	<div className="ppcp-r-select-box-wrapper">
		{ options.map(
			( { value: itemValue, title, description, contents } ) => {
				let isSelected;

				if ( Array.isArray( value ) ) {
					isSelected = value.includes( itemValue );
				} else {
					isSelected = value === itemValue;
				}

				return (
					<OptionItem
						key={ itemValue }
						itemTitle={ title }
						itemDescription={ description }
						itemValue={ itemValue }
						onChange={ onChange }
						isMulti={ multiSelect }
						isSelected={ isSelected }
					>
						{ contents }
					</OptionItem>
				);
			}
		) }
	</div>
);

export default OptionSelector;

const OptionItem = ( {
	itemTitle,
	itemDescription,
	itemValue,
	onChange,
	isMulti,
	isSelected,
	children,
} ) => {
	const boxClassName = classNames( 'ppcp-r-select-box', {
		'ppcp--selected': isSelected,
		'ppcp--multiselect': isMulti,
	} );

	return (
		// eslint-disable-next-line jsx-a11y/label-has-associated-control -- label has a nested input control.
		<label className={ boxClassName }>
			<InputField
				value={ itemValue }
				isRadio={ ! isMulti }
				onChange={ onChange }
				isSelected={ isSelected }
			/>

			<div className="ppcp--box-content">
				<div className="ppcp--box-content-inner">
					<span className="ppcp--box-title">{ itemTitle }</span>
					<div className="ppcp--box-description">
						{ itemDescription }
					</div>
					{ children && (
						<div className="ppcp--box-details">{ children }</div>
					) }
				</div>
			</div>
		</label>
	);
};

const InputField = ( { value, onChange, isRadio, isSelected } ) => {
	if ( isRadio ) {
		return (
			<PayPalRdb
				value={ value }
				onChange={ onChange }
				checked={ isSelected }
			/>
		);
	}

	return (
		<PayPalCheckbox
			value={ value }
			onChange={ onChange }
			checked={ isSelected }
		/>
	);
};
