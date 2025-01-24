import classNames from 'classnames';

import { CheckboxGroup } from '../../../../../ReusableComponents/Fields';
import HStack from '../../../../../ReusableComponents/HStack';
import StylingSection from './StylingSection';

const StylingSectionWithCheckboxes = ( {
	title,
	className = '',
	description = '',
	separatorAndGap = true,
	options,
	value,
	onChange,
	children,
} ) => {
	className = classNames( 'ppcp--has-checkboxes', className );

	return (
		<StylingSection
			title={ title }
			className={ className }
			description={ description }
			separatorAndGap={ separatorAndGap }
		>
			<HStack spacing={ 6 }>
				<CheckboxGroup
					options={ options }
					value={ value }
					onChange={ onChange }
				/>
			</HStack>

			{ children }
		</StylingSection>
	);
};

export default StylingSectionWithCheckboxes;
