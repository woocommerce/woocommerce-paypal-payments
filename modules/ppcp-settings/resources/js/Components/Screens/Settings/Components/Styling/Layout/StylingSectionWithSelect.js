import { SelectControl } from '@wordpress/components';
import classNames from 'classnames';

import StylingSection from './StylingSection';

const StylingSectionWithSelect = ( {
	title,
	className = '',
	description = '',
	separatorAndGap = true,
	options,
	value,
	onChange,
	children,
} ) => {
	className = classNames( 'ppcp--has-select', className );

	return (
		<StylingSection
			title={ title }
			className={ className }
			description={ description }
			separatorAndGap={ separatorAndGap }
		>
			<SelectControl
				__nextHasNoMarginBottom
				options={ options }
				value={ value }
				onChange={ onChange }
			/>

			{ children }
		</StylingSection>
	);
};

export default StylingSectionWithSelect;
