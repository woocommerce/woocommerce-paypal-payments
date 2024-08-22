import ConditionFactory from './ConditionFactory';
import ActionFactory from './ActionFactory';

class Rule {
	constructor( config, triggerUpdate ) {
		this.config = config;
		this.conditions = {};
		this.actions = {};
		this.triggerUpdate = triggerUpdate;
		this.status = null;

		const updateStatus = this.updateStatus.bind( this );
		for ( const conditionConfig of this.config.conditions ) {
			const condition = ConditionFactory.make(
				conditionConfig,
				updateStatus
			);
			this.conditions[ condition.key ] = condition;

			//console.log('Condition', condition);
		}

		for ( const actionConfig of this.config.actions ) {
			const action = ActionFactory.make( actionConfig );
			this.actions[ action.key ] = action;

			//console.log('Action', action);
		}
	}

	get key() {
		return this.config.key;
	}

	updateStatus( forceRunActions = false ) {
		let status = true;

		for ( const [ key, condition ] of Object.entries( this.conditions ) ) {
			status &= condition.status;
		}

		if ( status !== this.status ) {
			this.status = status;
			this.triggerUpdate();
			this.runActions();
		} else if ( forceRunActions ) {
			this.runActions();
		}
	}

	runActions() {
		for ( const [ key, action ] of Object.entries( this.actions ) ) {
			action.run( this.status );
		}
	}

	register() {
		for ( const [ key, condition ] of Object.entries( this.conditions ) ) {
			condition.register( this.updateStatus.bind( this ) );
		}
		for ( const [ key, action ] of Object.entries( this.actions ) ) {
			action.register();
		}

		this.updateStatus( true );
	}
}

export default Rule;
