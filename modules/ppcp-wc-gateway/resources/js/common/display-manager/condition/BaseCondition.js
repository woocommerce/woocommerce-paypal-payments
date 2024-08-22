class BaseCondition {
	constructor( config, triggerUpdate ) {
		this.config = config;
		this.status = false;
		this.triggerUpdate = triggerUpdate;
	}

	get key() {
		return this.config.key;
	}

	register() {
		// To override.
	}
}

export default BaseCondition;
