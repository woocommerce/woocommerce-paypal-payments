/**
 * Temporary component, until the experimental VStack/HStack block editor component is stable.
 *
 * @see https://wordpress.github.io/gutenberg/?path=/docs/components-experimental-hstack--docs
 * @see https://wordpress.github.io/gutenberg/?path=/docs/components-experimental-vstack--docs
 * @file
 */
import classNames from 'classnames';

const Stack = ( { type, className, spacing, children } ) => {
	const wrapperClass = classNames(
		'components-flex',
		`components-${ type }-stack`,
		className
	);

	const styles = {
		gap: `calc(${ 4 * spacing }px)`,
	};

	return (
		<div className={ wrapperClass } style={ styles }>
			{ children }
		</div>
	);
};

export const HStack = ( { className, spacing = 3, children } ) => {
	return (
		<Stack type="h" className={ className } spacing={ spacing }>
			{ children }
		</Stack>
	);
};

export const VStack = ( { className, spacing = 3, children } ) => {
	return (
		<Stack type="v" className={ className } spacing={ spacing }>
			{ children }
		</Stack>
	);
};
