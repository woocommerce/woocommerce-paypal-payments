import { selectTab, TAB_IDS } from '../../../utils/tabSelector';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME as TODOS_STORE_NAME } from '../../../data/todos';

const TodoSettingsBlock = ( {
	todosData,
	className = '',
	setActiveModal,
	onDismissTodo,
} ) => {
	const [ dismissingIds, setDismissingIds ] = useState( new Set() );
	const { completedTodos, dismissedTodos } = useSelect(
		( select ) => ( {
			completedTodos:
				select( TODOS_STORE_NAME ).getCompletedTodos() || [],
			dismissedTodos:
				select( TODOS_STORE_NAME ).getDismissedTodos() || [],
		} ),
		[]
	);

	const { completeOnClick } = useDispatch( TODOS_STORE_NAME );

	useEffect( () => {
		if ( dismissedTodos.length === 0 ) {
			setDismissingIds( new Set() );
		}
	}, [ dismissedTodos ] );

	if ( todosData.length === 0 ) {
		return null;
	}

	const handleDismiss = ( todoId, e ) => {
		e.preventDefault();
		e.stopPropagation();
		setDismissingIds( ( prev ) => new Set( [ ...prev, todoId ] ) );

		setTimeout( () => {
			onDismissTodo( todoId );
		}, 300 );
	};

	const handleClick = async ( todo ) => {
		const { action } = todo;
		const highlight = Boolean( action.highlight );

		// Handle different action types.
		if ( action.type === 'tab' ) {
			const tabId = TAB_IDS[ action.tab.toUpperCase() ];
			await selectTab( tabId, action.section, highlight );
		} else if ( action.type === 'external' ) {
			window.open( action.url, '_blank' );
		}

		if ( action.completeOnClick ) {
			await completeOnClick( todo.id );
		}

		if ( action.modal ) {
			setActiveModal( action.modal );
		}
	};

	// Filter out dismissed todos for display and limit to 5.
	const visibleTodos = todosData
		.filter( ( todo ) => ! dismissedTodos.includes( todo.id ) )
		.slice( 0, 5 );

	return (
		<div
			className={ `ppcp-r-settings-block__todo ppcp-r-todo-items ${ className }` }
		>
			{ visibleTodos.map( ( todo ) => (
				<TodoItem
					key={ todo.id }
					id={ todo.id }
					title={ todo.title }
					description={ todo.description }
					isCompleted={ completedTodos.includes( todo.id ) }
					isDismissing={ dismissingIds.has( todo.id ) }
					onDismiss={ ( e ) => handleDismiss( todo.id, e ) }
					onClick={ () => handleClick( todo ) }
				/>
			) ) }
		</div>
	);
};

const TodoItem = ( {
	title,
	description,
	isCompleted,
	isDismissing,
	onClick,
	onDismiss,
} ) => {
	return (
		<div
			className={ `ppcp-r-todo-item ${
				isCompleted ? 'is-completed' : ''
			} ${ isDismissing ? 'is-dismissing' : '' }` }
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
				<button
					className="ppcp-r-todo-item__dismiss"
					onClick={ onDismiss }
					aria-label="Dismiss todo item"
				>
					<span className="dashicons dashicons-no-alt"></span>
				</button>
			</div>
		</div>
	);
};

export default TodoSettingsBlock;
