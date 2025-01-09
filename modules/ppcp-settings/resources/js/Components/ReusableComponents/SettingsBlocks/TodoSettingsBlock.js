const TodoSettingsBlock = ( { todosData, className = '' } ) => {
	if ( todosData.length === 0 ) {
		return null;
	}

	return (
		<div
			className={ `ppcp-r-settings-block__todo ppcp-r-todo-items ${ className }` }
		>
			{ todosData
				.slice( 0, 5 )
				.filter( ( todo ) => {
					return ! todo.isCompleted();
				} )
				.map( ( todo ) => (
					<TodoItem
						key={ todo.id }
						title={ todo.title }
						onClick={ todo.onClick }
					/>
				) ) }
		</div>
	);
};

const TodoItem = ( props ) => {
	return (
		<div className="ppcp-r-todo-item" onClick={ props.onClick }>
			<div className="ppcp-r-todo-item__inner">
				<div className="ppcp-r-todo-item__icon"></div>
				<div className="ppcp-r-todo-item__description">
					{ props.title }
				</div>
			</div>
		</div>
	);
};

export default TodoSettingsBlock;
