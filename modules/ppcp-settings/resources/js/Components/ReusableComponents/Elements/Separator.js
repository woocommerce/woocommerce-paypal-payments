const Separator = ( { className = '', text = '', withLine = true } ) => {
	const separatorClass = [ 'ppcp-r-separator' ];
	const innerClass = withLine
		? 'ppcp-r-separator__line'
		: 'ppcp-r-separator__space';

	if ( className ) {
		separatorClass.push( className );
	}

	const getClass = ( type ) => `${ innerClass } ${ innerClass }--${ type }`;

	const renderSeparator = () => {
		if ( text ) {
			return (
				<>
					<span className={ getClass( 'before' ) }></span>
					<span className="ppcp-r-separator__text">{ text }</span>
					<span className={ getClass( 'after' ) }></span>
				</>
			);
		}

		return <span className={ getClass( 'full' ) }></span>;
	};

	return (
		<div className={ separatorClass.join( ' ' ) }>
			{ renderSeparator() }
		</div>
	);
};

export default Separator;
