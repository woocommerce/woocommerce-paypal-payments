import classNames from 'classnames';
import { Description, Header, Title, TitleExtra, Content } from './Elements';

const SettingsBlock = ( {
	className,
	children,
	title,
	titleSuffix,
	description,
	horizontalLayout = false,
	separatorAndGap = true,
} ) => {
	const blockClassName = classNames( 'ppcp-r-settings-block', className, {
		'ppcp--no-gap': ! separatorAndGap,
		'ppcp--horizontal': horizontalLayout,
	} );

	return (
		<div className={ blockClassName } id={ className }>
			<BlockTitle
				blockTitle={ title }
				blockSuffix={ titleSuffix }
				blockDescription={ description }
			/>

			<Content asCard={ false }>{ children }</Content>
		</div>
	);
};

export default SettingsBlock;

const BlockTitle = ( { blockTitle, blockSuffix, blockDescription } ) => {
	if ( ! blockTitle && ! blockDescription ) {
		return null;
	}

	return (
		<Header>
			<Title>
				{ blockTitle }
				<TitleExtra>{ blockSuffix }</TitleExtra>
			</Title>
			<Description>{ blockDescription }</Description>
		</Header>
	);
};
