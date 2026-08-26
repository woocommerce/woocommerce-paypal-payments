/* global describe, test, expect, beforeEach, afterEach, jest */
jest.mock("../Helper/CheckoutMethodState", () => ({
    getCurrentPaymentMethod: jest.fn(),
    PaymentMethods: { PAYPAL: "ppcp-gateway" },
}));

jest.mock("../Helper/ResumeFlowHelper", () => ({
    __esModule: true,
    default: {
        isResumeFlow: jest.fn(),
        cleanHashParams: jest.fn(),
        isReturnFromPayPal: jest.fn(),
        urlWithoutPayPalParams: jest.fn(),
        markSubmitAfterReturn: jest.fn(),
    },
}));

jest.mock("../Helper/Spinner", () => ({
    __esModule: true,
    default: {
        fullPage: jest.fn(() => ({
            block: jest.fn(),
            unblock: jest.fn(),
        })),
    },
}));

import onApproveForPayNow from "./onApproveForPayNow";
import { getCurrentPaymentMethod, PaymentMethods } from "../Helper/CheckoutMethodState";
import resumeFlowHelper from "../Helper/ResumeFlowHelper";

// jsdom has no real navigation: window.location.replace() falls through to
// its "not implemented: navigation" stub whenever the target URL differs
// from the current one, and the test environment forwards that to
// console.error. That makes it an observable, unmockable signal that
// replace() was actually invoked (see jsdom's navigation.js and
// @jest/environment-jsdom-abstract's `jsdomError` forwarding). `replace`,
// like `reload`, is a non-configurable unforgeable on window.location in
// jsdom 26, so it cannot be spied on directly, and the string it was called
// with cannot be recovered from the emitted error either - the assertions
// below confirm the navigation happened and that it was driven by
// urlWithoutPayPalParams()'s return value, rather than inspecting the call
// argument.
describe("onApproveForPayNow", () => {
    let context;
    let errorHandler;
    let checkedProp;
    let placeOrderClickSpy;

    const CLEAN_URL = "http://localhost/checkout/";

    const successResponse = () => ({
        ok: true,
        json: async () => ({ success: true }),
    });

    const failureResponse = (code, message = "Something went wrong") => ({
        ok: true,
        json: async () => ({ success: false, data: { code, message } }),
    });

    beforeEach(() => {
        jest.clearAllMocks();

        document.body.innerHTML = '<button id="place_order"></button>';
        placeOrderClickSpy = jest.spyOn(document.querySelector("#place_order"), "click");

        context = {
            config: {
                ajax: {
                    approve_order: {
                        endpoint: "/approve-order",
                        nonce: "nonce-value",
                    },
                },
            },
        };

        errorHandler = {
            clear: jest.fn(),
            message: jest.fn(),
            genericError: jest.fn(),
        };

        checkedProp = jest.fn();
        global.jQuery = jest.fn(() => ({
            prop: checkedProp,
            block: jest.fn(),
            unblock: jest.fn(),
        }));

        global.fetch = jest.fn();

        getCurrentPaymentMethod.mockReturnValue("ppcp-gateway");
        resumeFlowHelper.isResumeFlow.mockReturnValue(false);
        resumeFlowHelper.isReturnFromPayPal.mockReturnValue(false);
        resumeFlowHelper.urlWithoutPayPalParams.mockReturnValue(CLEAN_URL);
    });

    afterEach(() => {
        delete global.jQuery;
        delete global.fetch;
    });

    test("submits the checkout form when the page load is not a return from PayPal", async () => {
        resumeFlowHelper.isResumeFlow.mockReturnValue(false);
        resumeFlowHelper.isReturnFromPayPal.mockReturnValue(false);
        fetch.mockResolvedValueOnce(successResponse());

        await onApproveForPayNow(context, errorHandler)({ orderID: "1" }, {});

        expect(placeOrderClickSpy).toHaveBeenCalled();
        expect(resumeFlowHelper.markSubmitAfterReturn).not.toHaveBeenCalled();
    });

    test("navigates to the cleaned URL instead of submitting when returning from PayPal via AppSwitch", async () => {
        resumeFlowHelper.isResumeFlow.mockReturnValue(true);
        resumeFlowHelper.isReturnFromPayPal.mockReturnValue(true);
        fetch.mockResolvedValueOnce(successResponse());

        await onApproveForPayNow(context, errorHandler)({ orderID: "1" }, {});

        expect(console).toHaveErrored();
        expect(resumeFlowHelper.urlWithoutPayPalParams).toHaveBeenCalled();
        expect(resumeFlowHelper.markSubmitAfterReturn).toHaveBeenCalled();
        expect(placeOrderClickSpy).not.toHaveBeenCalled();
    });

    test("navigates to the cleaned URL instead of submitting when returning from PayPal without a resume-flow hash", async () => {
        // isReturnFromPayPal() no longer derives from the URL, so a plain
        // redirect return (no switch_initiated_time hash, isResumeFlow false)
        // still triggers navigation on its own.
        resumeFlowHelper.isResumeFlow.mockReturnValue(false);
        resumeFlowHelper.isReturnFromPayPal.mockReturnValue(true);
        fetch.mockResolvedValueOnce(successResponse());

        await onApproveForPayNow(context, errorHandler)({ orderID: "1" }, {});

        expect(console).toHaveErrored();
        expect(resumeFlowHelper.urlWithoutPayPalParams).toHaveBeenCalled();
        expect(resumeFlowHelper.cleanHashParams).not.toHaveBeenCalled();
        expect(resumeFlowHelper.markSubmitAfterReturn).toHaveBeenCalled();
        expect(placeOrderClickSpy).not.toHaveBeenCalled();
    });

    describe("when the approval request fails", () => {
        // With no actions.restart() available (classic checkout has none), the
        // handler re-throws after reporting the error so the outer .catch() in
        // the SDK bootstrap can surface it too.
        test("shows the returned message for a code 100 failure", async () => {
            fetch.mockResolvedValueOnce(failureResponse(100, "Card declined"));

            await expect(
                onApproveForPayNow(context, errorHandler)({ orderID: "1" }, {}),
            ).rejects.toThrow("Card declined");

            expect(errorHandler.message).toHaveBeenCalledWith("Card declined");
            expect(errorHandler.genericError).not.toHaveBeenCalled();
            expect(placeOrderClickSpy).not.toHaveBeenCalled();
        });

        test("shows a generic error for a non-100 failure code", async () => {
            fetch.mockResolvedValueOnce(failureResponse(500, "Server error"));

            await expect(
                onApproveForPayNow(context, errorHandler)({ orderID: "1" }, {}),
            ).rejects.toThrow("Server error");

            expect(errorHandler.genericError).toHaveBeenCalled();
            expect(errorHandler.message).not.toHaveBeenCalled();
        });

        test("restarts the PayPal action instead of throwing when restart() is available", async () => {
            fetch.mockResolvedValueOnce(failureResponse(100, "Card declined"));
            const restart = jest.fn().mockResolvedValue();

            await expect(
                onApproveForPayNow(context, errorHandler)({ orderID: "1" }, { restart }),
            ).resolves.toBeUndefined();

            expect(restart).toHaveBeenCalled();
        });
    });

    test("checks the PayPal radio when a different payment method ended up selected", async () => {
        getCurrentPaymentMethod.mockReturnValue("other-gateway");
        fetch.mockResolvedValueOnce(successResponse());

        await onApproveForPayNow(context, errorHandler)({ orderID: "1" }, {});

        expect(jQuery).toHaveBeenCalledWith(
            `input[name="payment_method"][value="${PaymentMethods.PAYPAL}"]`,
        );
        expect(checkedProp).toHaveBeenCalledWith("checked", true);
    });
});
