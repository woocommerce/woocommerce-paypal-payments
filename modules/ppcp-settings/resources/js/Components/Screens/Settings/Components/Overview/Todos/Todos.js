import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { Button, Icon } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { reusableBlock } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';
import { TodoSettingsBlock } from '../../../../../ReusableComponents/SettingsBlocks';
import SettingsCard from '../../../../../ReusableComponents/SettingsCard';
import { useTodos } from '../../../../../../data/todos/hooks';
import { STORE_NAME as COMMON_STORE_NAME } from '../../../../../../data/common';
import { STORE_NAME as TODOS_STORE_NAME } from '../../../../../../data/todos';
import { NOTIFICATION_SUCCESS } from '../../../../../ReusableComponents/Icons';

const Todos = () => {
	const [ isResetting, setIsResetting ] = useState( false );
	const { todos, isReady: areTodosReady, dismissTodo } = useTodos();
	// eslint-disable-next-line no-shadow
	const { setActiveModal, setActiveHighlight } =
		useDispatch( COMMON_STORE_NAME );
	const { resetDismissedTodos, setDismissedTodos } =
		useDispatch( TODOS_STORE_NAME );
	const { createSuccessNotice } = useDispatch( noticesStore );

	const showTodos = areTodosReady && todos.length > 0;

	const resetHandler = async () => {
		setIsResetting( true );
		try {
			await setDismissedTodos( [] );
			await resetDismissedTodos();

			createSuccessNotice(
				__(
					'Dismissed items restored successfully.',
					'woocommerce-paypal-payments'
				),
				{ icon: NOTIFICATION_SUCCESS }
			);
		} finally {
			setIsResetting( false );
		}
	};

	if ( ! showTodos ) {
		return null;
	}

	return (
		<SettingsCard
			className="ppcp-r-tab-overview-todo"
			title={ __( 'Things to do next', 'woocommerce-paypal-payments' ) }
			description={
				<>
					<p>
						{ __(
							'Complete these tasks to keep your store updated with the latest products and services.',
							'woocommerce-paypal-payments'
						) }
					</p>
					<Button
						variant="tertiary"
						onClick={ resetHandler }
						disabled={ isResetting }
					>
						<Icon icon={ reusableBlock } size={ 18 } />
						{ isResetting
							? __( 'Restoring…', 'woocommerce-paypal-payments' )
							: __(
									'Restore dismissed Things To Do',
									'woocommerce-paypal-payments'
							  ) }
					</Button>
				</>
			}
		>
			<TodoSettingsBlock
				todosData={ todos }
				setActiveModal={ setActiveModal }
				setActiveHighlight={ setActiveHighlight }
				onDismissTodo={ dismissTodo }
			/>
		</SettingsCard>
	);
};

export default Todos;
