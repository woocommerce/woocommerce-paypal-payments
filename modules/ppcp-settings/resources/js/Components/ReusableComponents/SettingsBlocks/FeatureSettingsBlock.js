import { Button } from '@wordpress/components';
import { Header, Title, Action, Description } from '../Elements';
import SettingsBlock from '../SettingsBlock';
import TitleBadge from '../TitleBadge';
import { CommonHooks } from '../../../data';

/**
 * Renders a feature settings block with title, description, and action buttons.
 *
 * @param {Object} props             Component properties
 * @param {string} props.title       The feature title
 * @param {string} props.description HTML description of the feature
 * @return {JSX.Element} The rendered component
 */
const FeatureSettingsBlock = ( { title, description, ...props } ) => {
	const { actionProps } = props;
	const { isSandbox } = CommonHooks.useMerchant();

	/**
	 * Gets the appropriate URL for a button based on environment
	 * Always prioritizes urls object over url when it exists
	 *
	 * @param {Object} buttonData                The button configuration object
	 * @param {string} [buttonData.url]          Single URL for the button
	 * @param {Object} [buttonData.urls]         Environment-specific URLs
	 * @param {string} [buttonData.urls.sandbox] URL for sandbox environment
	 * @param {string} [buttonData.urls.live]    URL for live environment
	 * @return {string|undefined} The appropriate URL to use for the button
	 */
	const getButtonUrl = ( buttonData ) => {
		const { url, urls } = buttonData;

		if ( urls ) {
			return isSandbox ? urls.sandbox : urls.live;
		}

		return url;
	};

	return (
		<SettingsBlock { ...props } className="ppcp-r-settings-block__feature">
			<Header>
				<Title>
					{ title }
					{ actionProps?.enabled && (
						<TitleBadge { ...actionProps?.badge } />
					) }
				</Title>
				<Description className="ppcp-r-settings-block__feature__description">
					<span
						className="ppcp-r-feature-item__description"
						dangerouslySetInnerHTML={ { __html: description } }
					/>

					{ actionProps?.notes?.length > 0 && (
						<span className="ppcp--item-notes">
							{ actionProps.notes.map( ( note, index ) => (
								<span key={ index }>{ note }</span>
							) ) }
						</span>
					) }
				</Description>
			</Header>

			<Action>
				<div className="ppcp--action-buttons">
					{ actionProps?.buttons.map( ( buttonData ) => {
						const {
							class: className,
							type,
							text,
							onClick,
						} = buttonData;

						const buttonUrl = getButtonUrl( buttonData );

						return (
							<Button
								key={ text }
								className={ className }
								variant={ type }
								isBusy={ actionProps.isBusy }
								href={ buttonUrl }
								target={ buttonUrl ? '_blank' : undefined }
								onClick={ ! buttonUrl ? onClick : undefined }
							>
								{ text }
							</Button>
						);
					} ) }
				</div>
			</Action>
		</SettingsBlock>
	);
};

export default FeatureSettingsBlock;
