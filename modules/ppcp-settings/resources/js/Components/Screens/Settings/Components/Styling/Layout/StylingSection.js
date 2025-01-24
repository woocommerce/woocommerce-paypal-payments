import SettingsBlock from '../../../../../ReusableComponents/SettingsBlock';
import {
	Description,
	Header,
	Title,
	Content,
} from '../../../../../ReusableComponents/Elements';

const StylingSection = ( {
	title,
	bigTitle = false,
	className = '',
	description = '',
	separatorAndGap = true,
	children,
} ) => {
	return (
		<SettingsBlock
			className={ className }
			separatorAndGap={ separatorAndGap }
		>
			<Header>
				<Title altStyle={ true } big={ bigTitle }>
					{ title }
				</Title>
				<Description>{ description }</Description>
			</Header>

			<Content asCard={ false } className="section-content">
				{ children }
			</Content>
		</SettingsBlock>
	);
};

export default StylingSection;
