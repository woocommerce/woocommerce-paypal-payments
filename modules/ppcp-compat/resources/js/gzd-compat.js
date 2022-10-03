document.addEventListener(
    'DOMContentLoaded',
    () => {
        const orderTrackingContainerId = "ppcp_order-tracking";
        const orderTrackingContainerSelector = "#ppcp_order-tracking";
        const gzdSaveButton = document.getElementById('order-shipments-save');
        const loadLocation = location.href + " " + orderTrackingContainerSelector + ">*";

        const disableTrackingFields = function () {
            var childNodes = document.getElementById(orderTrackingContainerId).getElementsByTagName('*');
            for (var node of childNodes) {
                node.disabled = true;
            }
        }

        const enableTrackingFields = function () {
            var childNodes = document.getElementById(orderTrackingContainerId).getElementsByTagName('*');
            for (var node of childNodes) {
                node.disabled = false;
            }
        }

        const waitForTrackingUpdate = function () {
            if (jQuery('#order-shipments-save').css('display') !== 'none') {
                disableTrackingFields();
                setTimeout(waitForTrackingUpdate, 100)
            } else {
                jQuery(orderTrackingContainerSelector).load(loadLocation,"");
            }
        }

        if (typeof(gzdSaveButton) != 'undefined' && gzdSaveButton != null) {
            gzdSaveButton.addEventListener('click', function (event) {
                waitForTrackingUpdate();
                enableTrackingFields();
            })
        }
    },
);
