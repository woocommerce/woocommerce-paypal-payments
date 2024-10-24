import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { debounce } from '../../../../../ppcp-blocks/resources/js/Helper/debounce';

/**
 * Approach 1: Component Injection
 *
 * A generic wrapper that adds debounced store updates to any controlled component.
 *
 * @param {Object}              props
 * @param {React.ComponentType} props.control     The controlled component to render
 * @param {string|number}       props.value       The controlled value
 * @param {Function}            props.onChange    Change handler
 * @param {number}              [props.delay=300] Debounce delay in milliseconds
 */
const DataStoreControl = ( {
	control: ControlComponent,
	value: externalValue,
	onChange,
	delay = 300,
	...props
} ) => {
	const [ internalValue, setInternalValue ] = useState( externalValue );
	const onChangeRef = useRef( onChange );
	onChangeRef.current = onChange;

	const debouncedUpdate = useRef(
		debounce( ( value ) => {
			onChangeRef.current( value );
		}, delay )
	).current;

	useEffect( () => {
		setInternalValue( externalValue );
		debouncedUpdate?.cancel();
	}, [ externalValue ] );

	useEffect( () => {
		return () => debouncedUpdate?.cancel();
	}, [ debouncedUpdate ] );

	const handleChange = useCallback(
		( newValue ) => {
			setInternalValue( newValue );
			debouncedUpdate( newValue );
		},
		[ debouncedUpdate ]
	);

	return (
		<ControlComponent
			{ ...props }
			value={ internalValue }
			onChange={ handleChange }
		/>
	);
};

export default DataStoreControl;
