import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import {
	Content,
	ContentWrapper,
	Header,
	Action,
	Description,
} from '@ppcp-settings/Components/ReusableComponents/Elements';
import { PPIcon } from '../../../ReusableComponents/Icons';
import classNames from 'classnames';
import { __ } from '@wordpress/i18n';
import {
	dismissAgenticBetaBanner,
	remindLaterAgenticBeta,
	applyForAgenticBeta,
} from '@ppcp-settings/data/agentic-beta/actions';

const BANNER_ID        = 'ppcp-agentic-beta-banner';
const BANNER_CLASS     = 'ppcp-r-settings-banner';
const BANNER_TITLE_ID  = `${ BANNER_ID }-title`;
// TODO: Replace with the real beta sign-up URL once it is confirmed by the PayPal team.
const APPLY_URL = 'https://example.com';

let isBannerDismissed = false;

const AgenticBetaBanner = () => {
	const [ isDismissed, setIsDismissed ] = useState( () => isBannerDismissed );

	const dismiss = () => {
		isBannerDismissed = true;
		setIsDismissed( true );
		dismissAgenticBetaBanner();
	};

	const remind = () => {
		isBannerDismissed = true;
		setIsDismissed( true );
		remindLaterAgenticBeta();
	};

	if ( isDismissed ) {
		return null;
	}

	return (
		<div
			id={ BANNER_ID }
			className={ classNames( 'ppcp-r-settings-card', BANNER_CLASS ) }
			role="region"
			aria-labelledby={ BANNER_TITLE_ID }
		>
			<ContentWrapper>
				<Content asCard={ false }>
					<Header>
						<div className="ppcp--title-wrapper">
							<h2
								id={ BANNER_TITLE_ID }
								className="ppcp-r-settings-card__title"
							>
								{ __(
									'Be among the first: Join the PayPal Store Sync Beta',
									'woocommerce-paypal-payments'
								) }
							</h2>
						</div>
						<Description>
							{ __(
								"AI-powered shopping agents are changing how customers discover and buy. We're looking for a small group of active US-based WooCommerce merchants to help shape this experience — get early access, direct input into the roadmap, and dedicated support from the PayPal & Syde team.",
								'woocommerce-paypal-payments'
							) }
						</Description>
					</Header>
					<Action>
						<div className="ppcp--action-buttons">
							<Button
								className="small-button"
								variant="secondary"
								href={ APPLY_URL }
								target="_blank"
								onClick={ applyForAgenticBeta }
							>
								{ __(
									'Apply for Early Access',
									'woocommerce-paypal-payments'
								) }
							</Button>
							<Button
								className="small-button"
								variant="tertiary"
								onClick={ remind }
							>
								{ __(
									'Remind me later',
									'woocommerce-paypal-payments'
								) }
							</Button>
						</div>
					</Action>
				</Content>
				<Content asCard={ false } className={ `${ BANNER_CLASS }__icon` }>
					<PPIcon imageName="icon-paypal-business-loan.svg" />
					<button
						className={ `${ BANNER_CLASS }__icon-close` }
						aria-label={ __(
							'Dismiss',
							'woocommerce-paypal-payments'
						) }
						onClick={ dismiss }
					>
						<PPIcon imageName="icon-close.svg" />
					</button>
				</Content>
			</ContentWrapper>
		</div>
	);
};

export default AgenticBetaBanner;
