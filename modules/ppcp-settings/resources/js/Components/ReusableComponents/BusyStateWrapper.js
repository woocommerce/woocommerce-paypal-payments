import {
	Children,
	isValidElement,
	cloneElement,
	useMemo,
	createContext,
	useContext,
} from '@wordpress/element';
import classNames from 'classnames';

import { CommonHooks } from '../../data';
import SpinnerOverlay from './SpinnerOverlay';

// Create context to track the busy state across nested wrappers
const BusyContext = createContext( false );

/**
 * Wraps interactive child elements and modifies their behavior based on the global `isBusy` state.
 * Allows custom processing of child props via the `onBusy` callback.
 *
 * @param {Object}   props             - Component properties.
 * @param {Children} props.children    - Child components to wrap.
 * @param {boolean}  props.enabled     - Enables or disables the busy-state logic.
 * @param {boolean}  props.busySpinner - Allows disabling the spinner in busy-state.
 * @param {string}   props.className   - Additional class names for the wrapper.
 * @param {Function} props.onBusy      - Callback to process child props when busy.
 * @param {boolean}  props.isBusy      - Optional. Additional condition to determine if the component is busy.
 */
const BusyStateWrapper = ( {
	children,
	enabled = true,
	busySpinner = true,
	className = '',
	onBusy = () => ( { disabled: true } ),
	isBusy = false,
} ) => {
	const { isBusy: globalIsBusy } = CommonHooks.useBusyState();
	const hasBusyParent = useContext( BusyContext );

	const isBusyComponent = ( isBusy || globalIsBusy ) && enabled;
	const showSpinner = busySpinner && isBusyComponent && ! hasBusyParent;

	const wrapperClassName = classNames( 'ppcp-r-busy-wrapper', className, {
		'ppcp--is-loading': isBusyComponent,
	} );

	const memoizedChildren = useMemo(
		() =>
			Children.map( children, ( child ) =>
				isValidElement( child )
					? cloneElement(
							child,
							isBusyComponent ? onBusy( child.props ) : {}
					  )
					: child
			),
		[ children, isBusyComponent, onBusy ]
	);

	return (
		<BusyContext.Provider value={ isBusyComponent }>
			<div className={ wrapperClassName }>
				{ showSpinner && <SpinnerOverlay /> }
				{ memoizedChildren }
			</div>
		</BusyContext.Provider>
	);
};

export default BusyStateWrapper;
