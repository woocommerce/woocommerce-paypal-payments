import { Icon } from '@wordpress/components';

import { LogoPayPal } from '../../../ReusableComponents/Icons';

const OnboardingHeader = ( props ) => {
	return (
		<section className="ppcp-r-onboarding-header">
			<div className="ppcp-r-onboarding-header__logo">
				<div className="ppcp-r-onboarding-header__logo-wrapper">
					<Icon icon={ LogoPayPal } width={ 110 } height={ 38 } />
				</div>
			</div>
			<div className="ppcp-r-onboarding-header__content">
				<h1 className="ppcp-r-onboarding-header__title">
					{ props.title }
				</h1>
				{ props.description && (
					<p
						className="ppcp-r-onboarding-header__description"
						dangerouslySetInnerHTML={ {
							__html: props.description,
						} }
					></p>
				) }
			</div>
		</section>
	);
};

export default OnboardingHeader;
