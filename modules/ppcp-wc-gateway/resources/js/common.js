import moveWrappedElements from "./common/wrapped-elements";
document.addEventListener(
    'DOMContentLoaded',
    () => {
        // Wait for current execution context to end.
        setTimeout(function () {
            moveWrappedElements();
        }, 0);
    }
);
