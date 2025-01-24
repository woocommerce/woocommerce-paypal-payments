/**
 * TODO: Replace this with the WordPress select control once V2 with multi-select is ready.
 *
 * This component has a lot of compatibility logic to (a) make the ReactSelect component look like
 * a WordPress select component, and (b) convert values from Redux-format (value-strings) to
 * ReactSelect values (objects containing value and label). When switching to the
 * SelectControl from `@wordpress/components`, we can remove a lot of this code.
 *
 * @see https://wordpress.github.io/gutenberg/?path=/story/components-customselectcontrol-v2--multiple-selection
 * @file
 */

import { default as ReactSelect, components } from 'react-select';
import { Icon } from '@wordpress/components';
import { chevronDown, chevronUp } from '@wordpress/icons';
import { useCallback, useEffect, useState } from '@wordpress/element';

const DropdownIndicator = ( props ) => (
	<components.DropdownIndicator { ...props }>
		<Icon icon={ props.selectProps.menuIsOpen ? chevronUp : chevronDown } />
	</components.DropdownIndicator>
);

const IndicatorSeparator = () => null;

// Convert a plain value string/array to react-select objects.
const toInternalValue = ( selected, options ) => {
	if ( Array.isArray( selected ) ) {
		return selected.map( ( value ) =>
			options.find( ( option ) => option.value === value )
		);
	}

	return options.find( ( option ) => option.value === selected );
};

// Convert react-select object(s) to a plain value string/array.
const toStoreValue = ( selected ) => {
	if ( ! selected ) {
		return null;
	}
	if ( Array.isArray( selected ) ) {
		return selected.map( ( value ) => value.value );
	}
	return selected.value;
};

const Select = ( { options, value, onChange, isMulti, placeholder } ) => {
	const [ internalValue, setInternalValue ] = useState(
		toInternalValue( value, options )
	);

	const onInternalValueChange = useCallback(
		( selected ) => {
			setInternalValue( selected );

			if ( Array.isArray( selected ) ) {
				return onChange( selected.map( ( option ) => option.id ) );
			}
			return onChange( selected.id );
		},
		[ onChange ]
	);

	// Forward changes of the internal ReactSelect value to the onChange callback.
	useEffect( () => {
		onChange( toStoreValue( internalValue ) );
	}, [ internalValue, onChange ] );

	return (
		<ReactSelect
			className="ppcp-r-select"
			classNamePrefix="ppcp"
			isMulti={ isMulti }
			options={ options }
			value={ internalValue }
			onChange={ onInternalValueChange }
			placeholder={ placeholder }
			components={ { DropdownIndicator, IndicatorSeparator } }
		/>
	);
};

export default Select;
