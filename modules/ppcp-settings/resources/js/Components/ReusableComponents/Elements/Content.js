import classNames from 'classnames';

const Content = ( { children, asCard = true, className = '', id = '' } ) => {
	const elementClasses = classNames( 'ppcp--content', className, {
		'ppcp--is-card': asCard,
	} );

	return (
		<div id={ id } className={ elementClasses }>
			{ children }
		</div>
	);
};

export default Content;
