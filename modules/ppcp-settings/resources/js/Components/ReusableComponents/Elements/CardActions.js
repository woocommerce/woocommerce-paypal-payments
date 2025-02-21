import classNames from 'classnames';

const CardActions = ( { isDimmed = false, children } ) => {
	const className = classNames( 'ppcp--card-actions', {
		'ppcp--dimmed': isDimmed,
	} );

	return <div className={ className }>{ children }</div>;
};

export default CardActions;
