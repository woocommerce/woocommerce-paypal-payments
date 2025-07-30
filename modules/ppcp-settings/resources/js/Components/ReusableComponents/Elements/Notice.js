import classNames from 'classnames';

const Notice = ( { children, type = 'info', className = '' } ) => {
	if ( ! children ) {
		return null;
	}

	const elementClasses = classNames(
		'ppcp--notice',
		`type--${ type }`,
		className
	);

	return <span className={ elementClasses }>{ children }</span>;
};

export default Notice;
