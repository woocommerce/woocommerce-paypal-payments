import VisibilityAction from './action/VisibilityAction';
import AttributeAction from './action/AttributeAction';

class ActionFactory {
	static make( actionConfig ) {
		switch ( actionConfig.type ) {
			case 'visibility':
				return new VisibilityAction( actionConfig );
			case 'attribute':
				return new AttributeAction( actionConfig );
		}

		throw new Error(
			'[ActionFactory] Unknown action: ' + actionConfig.type
		);
	}
}

export default ActionFactory;
