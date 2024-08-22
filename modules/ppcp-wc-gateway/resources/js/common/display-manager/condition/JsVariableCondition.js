import BaseCondition from './BaseCondition';

class JsVariableCondition extends BaseCondition {
	register() {
		jQuery( document ).on( 'ppcp-display-change', () => {
			const status = this.check();
			if ( status !== this.status ) {
				this.status = status;
				this.triggerUpdate();
			}
		} );

		this.status = this.check();
	}

	check() {
		const value = document[ this.config.variable ];
		return this.config.value === value;
	}
}

export default JsVariableCondition;
