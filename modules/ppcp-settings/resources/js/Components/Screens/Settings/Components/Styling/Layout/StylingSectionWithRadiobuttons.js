import { RadioControl } from '@wordpress/components';
import classNames from 'classnames';

import HStack from '../../../../../ReusableComponents/HStack';
import StylingSection from './StylingSection';

const StylingSectionWithRadiobuttons = ( {
	title,
	className = '',
	description = '',
	separatorAndGap = true,
	options,
	selected,
	onChange,
	children,
} ) => {
	className = classNames( 'ppcp--has-radio-buttons', className );

	return (
		<StylingSection
			title={ title }
			className={ className }
			description={ description }
			separatorAndGap={ separatorAndGap }
		>
			<HStack>
				<RadioControl
					options={ options }
					selected={ selected }
					onChange={ onChange }
				/>
			</HStack>

			{ children }
		</StylingSection>
	);
};

export default StylingSectionWithRadiobuttons;
