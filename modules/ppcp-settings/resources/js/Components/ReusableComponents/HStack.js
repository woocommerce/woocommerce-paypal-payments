/**
 * Temporary component, until the experimental HStack block editor component is stable.
 *
 * @see https://wordpress.github.io/gutenberg/?path=/docs/components-experimental-hstack--docs
 * @file
 */
import classNames from 'classnames';

const HStack = ( { className, spacing = 3, children } ) => {
	const wrapperClass = classNames(
		'components-flex components-h-stack',
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

export default HStack;
