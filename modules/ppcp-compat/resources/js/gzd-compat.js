document.addEventListener(
    'DOMContentLoaded',
    () => {
        const orderTrackingContainerId = "ppcp_order-tracking";
        const orderTrackingContainerSelector = "#ppcp_order-tracking";
        const gzdSaveButton = document.getElementById('order-shipments-save');
        const loadLocation = location.href + " " + orderTrackingContainerSelector + ">*";

        const setEnabled = function (enabled) {
            let childNodes = document.getElementById(orderTrackingContainerId).getElementsByTagName('*');
            for (let node of childNodes) {
                node.disabled = !enabled;
            }
        }

        const waitForTrackingUpdate = function () {
            if (jQuery('#order-shipments-save').css('display') !== 'none') {
                setEnabled(false);
                setTimeout(waitForTrackingUpdate, 100)
            } else {
                jQuery(orderTrackingContainerSelector).load(loadLocation,"");
            }
        }

        if (typeof(gzdSaveButton) != 'undefined' && gzdSaveButton != null) {
            gzdSaveButton.addEventListener('click', function (event) {
                waitForTrackingUpdate();
                setEnabled(true);
            })
        }
    },
);
