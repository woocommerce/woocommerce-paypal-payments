const Separator = ( props ) => {
	let separatorClass = 'ppcp-r-separator';

	if ( props?.className ) {
		separatorClass += ' ' + props.className;
	}

	if ( props.text ) {
		return (
			<div className={ separatorClass }>
				<span className="ppcp-r-separator__line ppcp-r-separator__line--before"></span>

				<span className="ppcp-r-separator__text">{ props.text }</span>
				<span className="ppcp-r-separator__line ppcp-r-separator__line--after"></span>
			</div>
		);
	}

	return (
		<div className={ separatorClass }>
			<span className="ppcp-r-separator__line ppcp-r-separator__line--before"></span>
		</div>
	);
};

export default Separator;
