const Action = ( { id, children } ) => (
	<div className="ppcp--action" { ...( id ? { id } : {} ) }>
		{ children }
	</div>
);

export default Action;
