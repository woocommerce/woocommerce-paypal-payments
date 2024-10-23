import data from '../../utils/data';

const OnboardingHeader = ( props ) => {
	return (
		<section className="ppcp-r-onboarding-header">
			<div className="ppcp-r-onboarding-header__gradient">
				<div className="ppcp-r-onboarding-header__logo-wrapper">
					{ data().getImage( 'logo-paypal.svg' ) }
				</div>
			</div>
			<div className="ppcp-r-onboarding-header__content">
				<h1 className="ppcp-r-onboarding-header__title">
					{ props.title }
				</h1>
				{ props.description && (
					<p className="ppcp-r-onboarding-header__description">
						{ props.description }
					</p>
				) }
			</div>
		</section>
	);
};

export default OnboardingHeader;
