import FieldDisplayManager from "./common/FieldDisplayManager";
import moveWrappedElements from "./common/wrapped-elements";

document.addEventListener(
    'DOMContentLoaded',
    () => {

        // Wait for current execution context to end.
        setTimeout(function () {
            moveWrappedElements();
        }, 0);


        // Initialize FieldDisplayManager.
        const fieldDisplayManager = new FieldDisplayManager();

        jQuery( '*[data-ppcp-display]' ).each( (index, el) => {
            const rules = jQuery(el).data('ppcpDisplay');
            for (const rule of rules) {
                fieldDisplayManager.addRule(rule);
            }
        });
    }
);
