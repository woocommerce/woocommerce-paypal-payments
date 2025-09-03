// Mock for @wordpress/components
export const Button = ( { children, ...props } ) => {
	return <button { ...props }>{ children }</button>;
};
