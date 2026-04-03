import classNames from 'classnames';
import { useScrollTarget } from '@ppcp-settings/hooks/useScrollHighlight';

const Action = ( { id, children } ) => {
	const { ref, isHighlighted } = useScrollTarget( id );

	return (
		<div
			className={ classNames( 'ppcp--action', {
				'ppcp-highlight': isHighlighted,
			} ) }
			{ ...( id ? { id, ref } : {} ) }
		>
			{ children }
		</div>
	);
};

export default Action;
