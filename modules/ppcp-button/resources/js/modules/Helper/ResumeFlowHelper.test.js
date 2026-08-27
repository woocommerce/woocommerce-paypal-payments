/* global describe, test, expect, beforeEach */
import resumeFlowHelper from "./ResumeFlowHelper";

// history.replaceState() updates document._URL directly in jsdom, unlike
// setting window.location.search/hash, which routes through jsdom's
// unimplemented navigation stub and never actually changes the URL. It is
// also how the module itself relocates the page (see cleanHashParams()), so
// it is the realistic way to set up a "current URL" for these tests.
const setUrl = (pathAndQueryAndHash) => {
    window.history.replaceState(null, "", pathAndQueryAndHash);
};

describe("ResumeFlowHelper", () => {
    beforeEach(() => {
        setUrl("/checkout/");
        // orderCreatedInDocument is module state, not derived from anything
        // reset by setUrl(); without this, markOrderCreated() from one test
        // would silently leak into the next.
        resumeFlowHelper.orderCreatedInDocument = false;
        window.sessionStorage.clear();
    });

    describe("isReturnFromPayPal()", () => {
        test("is true when nothing has created an order in this document yet", () => {
            expect(resumeFlowHelper.isReturnFromPayPal()).toBe(true);
        });

        test("is false once markOrderCreated() has run in this document", () => {
            resumeFlowHelper.markOrderCreated();

            expect(resumeFlowHelper.isReturnFromPayPal()).toBe(false);
        });

        test("stays true with token and PayerID in the query string, because the SDK strips them before onApprove runs and reading the URL would report a false negative", () => {
            setUrl("/checkout/?token=8HY42288U3810663S&PayerID=2Q7KYG2RLJT2S");

            expect(resumeFlowHelper.isReturnFromPayPal()).toBe(true);
        });

        test("stays false after markOrderCreated() even with a completely clean URL", () => {
            resumeFlowHelper.markOrderCreated();
            setUrl("/checkout/");

            expect(resumeFlowHelper.isReturnFromPayPal()).toBe(false);
        });
    });

    describe("markSubmitAfterReturn()", () => {
        test("writes '1' under AUTO_SUBMIT_KEY in sessionStorage", () => {
            resumeFlowHelper.markSubmitAfterReturn();

            // AUTO_SUBMIT_KEY's value is duplicated in
            // ButtonModule::SUBMIT_AFTER_RETURN_KEY on the PHP side, which is
            // now the sole reader of this key; a rename that breaks the pair
            // would only show up there, so it is asserted here too.
            expect(window.sessionStorage.getItem(resumeFlowHelper.AUTO_SUBMIT_KEY)).toBe("1");
        });

        test("does not throw when sessionStorage is unavailable, as in private browsing", () => {
            // jsdom's Storage is a Proxy with a named-property setter, so
            // assigning window.sessionStorage.setItem directly creates a new
            // storage entry rather than replacing the method. The real
            // method lives on the prototype, so that is what has to be
            // stubbed.
            jest.spyOn(Storage.prototype, "setItem").mockImplementation(() => {
                throw new Error("SecurityError");
            });

            expect(() => resumeFlowHelper.markSubmitAfterReturn()).not.toThrow();

            jest.restoreAllMocks();
        });
    });

    describe("urlWithoutPayPalParams()", () => {
        test("strips PayPal params from the query string while preserving unrelated params", () => {
            setUrl("/checkout/?token=ABC&PayerID=XYZ&order-received=1");

            expect(resumeFlowHelper.urlWithoutPayPalParams()).toBe(
                "http://localhost/checkout/?order-received=1",
            );
        });

        test("strips PayPal params from the hash while preserving unrelated params", () => {
            setUrl("/checkout/#switch_initiated_time=123&scrollTo=payment");

            expect(resumeFlowHelper.urlWithoutPayPalParams()).toBe(
                "http://localhost/checkout/#scrollTo=payment",
            );
        });

        test("strips PayPal params from both the query string and the hash at once", () => {
            setUrl(
                "/checkout/?token=ABC&PayerID=XYZ&order-received=1#switch_initiated_time=123&scrollTo=payment",
            );

            expect(resumeFlowHelper.urlWithoutPayPalParams()).toBe(
                "http://localhost/checkout/?order-received=1#scrollTo=payment",
            );
        });

        test("drops an empty hash entirely once its only params are removed", () => {
            setUrl("/checkout/?order-received=1#switch_initiated_time=123");

            expect(resumeFlowHelper.urlWithoutPayPalParams()).toBe(
                "http://localhost/checkout/?order-received=1",
            );
        });

        test("returns the URL unchanged when there are no PayPal params to strip", () => {
            setUrl("/checkout/?order-received=1");

            expect(resumeFlowHelper.urlWithoutPayPalParams()).toBe(
                "http://localhost/checkout/?order-received=1",
            );
        });
    });
});
