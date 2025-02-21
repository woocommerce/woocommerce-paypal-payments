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
const DataStoreControl = React.forwardRef(
	(
		{
			control: ControlComponent,
			value: externalValue,
			onChange,
			onConfirm = null,
			delay = 300,
			...props
		},
		ref
	) => {
		const [ internalValue, setInternalValue ] = useState( externalValue );
		const onChangeRef = useRef( onChange );
		const onConfirmRef = useRef( onConfirm );
		onChangeRef.current = onChange;
		onConfirmRef.current = onConfirm;

		const debouncedUpdate = useRef(
			debounce( ( value ) => {
				onChangeRef.current( value );
			}, delay )
		).current;

		useEffect( () => {
			setInternalValue( externalValue );
			debouncedUpdate?.cancel();
		}, [ debouncedUpdate, externalValue ] );

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

		const handleKeyDown = useCallback(
			( event ) => {
				if ( onConfirmRef.current && event.key === 'Enter' ) {
					event.preventDefault();
					debouncedUpdate.flush();
					onConfirmRef.current();
					return false;
				}
			},
			[ debouncedUpdate ]
		);

		return (
			<ControlComponent
				ref={ ref }
				{ ...props }
				value={ internalValue }
				onChange={ handleChange }
				onKeyDown={ handleKeyDown }
			/>
		);
	}
);

export default DataStoreControl;
