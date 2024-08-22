import Rule from './Rule';

class DisplayManager {
	constructor() {
		this.rules = {};
		this.ruleStatus = {}; // The current status for each rule. Maybe not necessary, for now just for logging.

		document.ppcpDisplayManagerLog = () => {
			console.log( 'DisplayManager', this );
		};
	}

	addRule( ruleConfig ) {
		const updateStatus = () => {
			this.ruleStatus[ ruleConfig.key ] =
				this.rules[ ruleConfig.key ].status;
			//console.log('ruleStatus', this.ruleStatus);
		};

		this.rules[ ruleConfig.key ] = new Rule(
			ruleConfig,
			updateStatus.bind( this )
		);
		//console.log('Rule', this.rules[ruleConfig.key]);
	}

	register() {
		this.ruleStatus = {};
		for ( const [ key, rule ] of Object.entries( this.rules ) ) {
			rule.register();
		}
	}
}

export default DisplayManager;
