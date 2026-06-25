import { useDispatch, useSelect } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { SnackbarList } from '@wordpress/components';

const Notifications = () => {
	const notices = useSelect(
		( select ) => select( noticesStore ).getNotices(),
		[]
	);

	const { removeNotice } = useDispatch( noticesStore );

	return (
		<SnackbarList
			className="ppcp-r-notifications"
			notices={ notices }
			onRemove={ removeNotice }
		/>
	);
};

export default Notifications;
