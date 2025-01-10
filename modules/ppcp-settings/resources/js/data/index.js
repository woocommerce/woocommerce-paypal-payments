import { addDebugTools } from './debug';
import * as Onboarding from './onboarding';
import * as Common from './common';
import * as PaymentMethods from './payment-methods';

Onboarding.initStore();
Common.initStore();
PaymentMethods.initStore();

export const OnboardingHooks = Onboarding.hooks;
export const CommonHooks = Common.hooks;

export const OnboardingStoreName = Onboarding.STORE_NAME;
export const CommonStoreName = Common.STORE_NAME;

export * from './constants';

addDebugTools( window.ppcpSettings, [ Onboarding, Common ] );
