import * as Store from './data';

export function App() {
	// We need to "use" the Store variable, to prevent webpack from tree-shaking it.
	console.log( 'Store ready:', Store );

	return <div className="red">App with Store</div>;
}
