import data from '../../utils/data';

const SelectBox = ( props ) => {
	let boxClassName = 'ppcp-r-select-box';

	if ( props.value === props.currentValue ) {
		boxClassName += ' selected';
	}
	return (
		<div className={ boxClassName }>
			<div className="ppcp-r-select-box__radio">
				<input
					className="ppcp-r-select-box__radio-value"
					type="radio"
					name={ props.name }
					value={ props.value }
					onChange={ () => props.changeCallback( props.value ) }
				/>
				<span className="ppcp-r-select-box__radio-presentation"></span>
			</div>
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
