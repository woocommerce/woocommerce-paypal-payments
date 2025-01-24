import classNames from 'classnames';

const Description = ( { children, className = '' } ) => {
	// Don't output anything if description is empty.
	if ( ! children ) {
		return null;
	}

	const elementClasses = classNames( 'ppcp--description', className );

	if ( 'string' !== typeof children ) {
		return <span className={ elementClasses }>{ children }</span>;
	}

	return (
		<span
			className={ elementClasses }
			dangerouslySetInnerHTML={ { __html: children } }
		/>
	);
};

export default Description;
