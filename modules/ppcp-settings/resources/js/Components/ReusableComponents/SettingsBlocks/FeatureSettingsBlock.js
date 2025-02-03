import { Button } from '@wordpress/components';

import { Header, Title, Action, Description } from '../Elements';
import SettingsBlock from '../SettingsBlock';
import TitleBadge from '../TitleBadge';

const FeatureSettingsBlock = ( { title, description, ...props } ) => {
	const printNotes = () => {
		const notes = props.actionProps?.notes;
		if ( ! notes || ( Array.isArray( notes ) && notes.length === 0 ) ) {
			return null;
		}

		return (
			<span className="ppcp--item-notes">
				{ notes.map( ( note, index ) => (
					<span key={ index }>{ note }</span>
				) ) }
			</span>
		);
	};

	const FeatureButton = ( {
		className,
		variant,
		text,
		isBusy,
		url,
		urls,
		onClick,
	} ) => {
		const buttonProps = {
			className,
			isBusy,
			variant,
		};

		if ( url || urls ) {
			buttonProps.href = urls ? urls.live : url;
			buttonProps.target = '_blank';
		}
		if ( ! buttonProps.href ) {
			buttonProps.onClick = onClick;
		}

		return <Button { ...buttonProps }>{ text }</Button>;
	};

	const renderDescription = () => {
		return (
			<span
				className="ppcp-r-feature-item__description ppcp-r-settings-block__feature__description"
				dangerouslySetInnerHTML={ { __html: description } }
			/>
		);
	};

	return (
		<SettingsBlock { ...props } className="ppcp-r-settings-block__feature">
			<Header>
				<Title>
					{ title }
					{ props.actionProps?.enabled && (
						<TitleBadge { ...props.actionProps?.badge } />
					) }
				</Title>
				<Description className="ppcp-r-settings-block__feature__description">
					{ renderDescription() }
					{ printNotes() }
				</Description>
			</Header>
			<Action>
				<div className="ppcp--action-buttons">
					{ props.actionProps?.buttons.map(
						( {
							class: className,
							type,
							text,
							url,
							urls,
							onClick,
						} ) => (
							<FeatureButton
								key={ text }
								className={ className }
								variant={ type }
								text={ text }
								isBusy={ props.actionProps.isBusy }
								url={ url }
								urls={ urls }
								onClick={ onClick }
							/>
						)
					) }
				</div>
			</Action>
		</SettingsBlock>
	);
};

export default FeatureSettingsBlock;
