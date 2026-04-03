import classNames from 'classnames';
import { Description, Header, Title, TitleExtra, Content } from './Elements';
import { useScrollTarget } from '@ppcp-settings/hooks/useScrollHighlight';

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
	const { ref, isHighlighted } = useScrollTarget( id );

	if ( ! visible ) {
		return null;
	}

	const blockClassName = classNames( 'ppcp-r-settings-block', className, {
		'ppcp--no-gap': ! separatorAndGap,
		'ppcp--horizontal': horizontalLayout,
		'ppcp-highlight': isHighlighted,
	} );

	const props = {
		className: blockClassName,
		...( id && { id, ref } ),
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
