import classNames from 'classnames';

const Header = ( { children, className = '' } ) => {
	if ( ! children ) {
		return null;
	}

	const elementClasses = classNames( 'ppcp--header', className );

	return <div className={ elementClasses }>{ children }</div>;
};

export default Header;
