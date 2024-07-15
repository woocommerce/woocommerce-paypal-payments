import BaseAction from './BaseAction';

class AttributeAction extends BaseAction {
	run( status ) {
		if ( status ) {
			jQuery( this.config.selector ).addClass( this.config.html_class );
		} else {
			jQuery( this.config.selector ).removeClass(
				this.config.html_class
			);
		}
	}
}

export default AttributeAction;
