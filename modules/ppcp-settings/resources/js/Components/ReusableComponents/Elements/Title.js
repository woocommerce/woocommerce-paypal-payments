import classNames from 'classnames';

const Title = ( { children, noCaps = false, big = false, className = '' } ) => {
	if ( ! children ) {
		return null;
	}

	const elementClasses = classNames( 'ppcp--title', className, {
		'ppcp--no-caps': noCaps,
		'ppcp--big': big,
	} );

	return <span className={ elementClasses }>{ children }</span>;
};

export default Title;
