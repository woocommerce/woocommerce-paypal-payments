import data from '../../utils/data';

const SelectBox = ( props ) => {
	return (
		<div className="ppcp-r-select-box">
			<div className="ppcp-r-select-box__radio">
				<input
					checked="checked"
					className="ppcp-r-select-box__radio-value"
					type="radio"
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
