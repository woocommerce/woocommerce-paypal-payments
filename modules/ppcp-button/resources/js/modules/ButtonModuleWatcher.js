class ButtonModuleWatcher {
	constructor() {
		this.contextBootstrapRegistry = {};
		this.contextBootstrapWatchers = [];
	}

	watchContextBootstrap( callable ) {
		this.contextBootstrapWatchers.push( callable );
		Object.values( this.contextBootstrapRegistry ).forEach( callable );
	}

	registerContextBootstrap( context, handler ) {
		this.contextBootstrapRegistry[ context ] = {
			context,
			handler,
		};

		// Call registered watchers
		for ( const callable of this.contextBootstrapWatchers ) {
			callable( this.contextBootstrapRegistry[ context ] );
		}
	}
}

window.ppcpResources = window.ppcpResources || {};
const buttonModuleWatcher = ( window.ppcpResources.ButtonModuleWatcher =
	window.ppcpResources.ButtonModuleWatcher || new ButtonModuleWatcher() );

export default buttonModuleWatcher;
