import { selectTab, TAB_IDS } from '../../../utils/tabSelector';

const TodoSettingsBlock = ( { todosData, className = '' } ) => {
	if ( todosData.length === 0 ) {
		return null;
	}

	return (
		<div
			className={ `ppcp-r-settings-block__todo ppcp-r-todo-items ${ className }` }
		>
			{ todosData.slice( 0, 5 ).map( ( todo ) => (
				<TodoItem
					key={ todo.id }
					title={ todo.title }
					description={ todo.description }
					isCompleted={ todo.isCompleted }
					onClick={ async () => {
						if ( todo.action.type === 'tab' ) {
							const tabId =
								TAB_IDS[ todo.action.tab.toUpperCase() ];
							await selectTab( tabId, todo.action.section );
						} else if ( todo.action.type === 'external' ) {
							window.open( todo.action.url, '_blank' );
						}
					} }
				/>
			) ) }
		</div>
	);
};

const TodoItem = ( { title, description, isCompleted, onClick } ) => {
	return (
		<div
			className={ `ppcp-r-todo-item ${
				isCompleted ? 'is-completed' : ''
			}` }
			onClick={ onClick }
		>
			<div className="ppcp-r-todo-item__inner">
				<div className="ppcp-r-todo-item__icon">
					{ isCompleted && (
						<span className="dashicons dashicons-yes"></span>
					) }
				</div>
				<div className="ppcp-r-todo-item__content">
					<div className="ppcp-r-todo-item__description">
						{ title }
					</div>
					{ description && (
						<div className="ppcp-r-todo-item__secondary-description">
							{ description }
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default TodoSettingsBlock;
