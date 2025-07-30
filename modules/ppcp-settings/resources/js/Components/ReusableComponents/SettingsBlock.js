import classNames from 'classnames';
import { Description, Header, Title, TitleExtra, Content } from './Elements';

const SettingsBlock = ( {
	id,
	className,
	children,
	title,
	titleSuffix,
	description,
	horizontalLayout = false,
	separatorAndGap = true,
	visible = true,
} ) => {
	if ( ! visible ) {
		return null;
	}

	const blockClassName = classNames( 'ppcp-r-settings-block', className, {
		'ppcp--no-gap': ! separatorAndGap,
		'ppcp--horizontal': horizontalLayout,
	} );

	const props = {
		className: blockClassName,
		...( id && { id } ),
	};

	return (
		<div { ...props }>
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
