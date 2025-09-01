import { Action } from '../Elements';
import classNames from 'classnames';
import CopyButton from '../Elements/CopyButton';

const ControlStaticValue = ( {
	value,
	showCopy = false,
	copyButtonProps = {},
	className,
	...props
} ) => {
	const wrapperClass = classNames( 'ppcp--static-value', {
		'ppcp--static-value-with-copy': showCopy,
		'ppcp--has-copy': showCopy,
	} );

	return (
		<Action className={ className } { ...props }>
			{ showCopy ? (
				<div className={ wrapperClass }>
					<div className="ppcp--static-value-text">{ value }</div>
					<CopyButton value={ value } { ...copyButtonProps } />
				</div>
			) : (
				<div className={ wrapperClass }>{ value }</div>
			) }
		</Action>
	);
};

export default ControlStaticValue;
