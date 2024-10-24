import data from '../../utils/data';

const SelectBox = ( props ) => {
	const handleCheckboxState = ( checked ) => {
		let newValue = null;
		if ( checked ) {
			newValue = [ ...props.currentValue, props.value ];
			props.changeCallback( newValue );
		} else {
			newValue = props.currentValue.filter(
				( value ) => value !== props.value
			);
		}
		props.changeCallback( newValue );
	};

	let boxClassName = 'ppcp-r-select-box';

	if (
		props.value === props.currentValue ||
		( Array.isArray( props.currentValue ) &&
			props.currentValue.includes( props.value ) )
	) {
		boxClassName += ' selected';
	}

	return (
		<div className={ boxClassName }>
			{ props.type === 'radio' && (
				<div className="ppcp-r-select-box__radio">
					<input
						className="ppcp-r-select-box__radio-value"
						type="radio"
						checked={ props.value === props.currentValue }
						name={ props.name }
						value={ props.value }
						onChange={ () => props.changeCallback( props.value ) }
					/>
					<span className="ppcp-r-select-box__radio-presentation"></span>
				</div>
			) }
			{ props.type === 'checkbox' && (
				<div className="ppcp-r-select-box__checkbox">
					<input
						className="ppcp-r-select-box__checkbox-value"
						type="checkbox"
						checked={ props.currentValue.includes( props.value ) }
						name={ props.name }
						value={ props.value }
						onChange={ ( e ) =>
							handleCheckboxState( e.target.checked )
						}
					/>
					<span className="ppcp-r-select-box__checkbox-presentation">
						{ data().getImage( 'icon-checkbox.svg' ) }
					</span>
				</div>
			) }
			<div className="ppcp-r-select-box__content">
				{ data().getImage( props.icon ) }
				<div className="ppcp-r-select-box__content-inner">
					<span className="ppcp-r-select-box__title">
						{ props.title }
					</span>
					<p className="ppcp-r-select-box__description">
						{ props.description }
					</p>
					{ props.children && (
						<div className="ppcp-r-select-box__additional-content">
							{ props.children }
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default SelectBox;
