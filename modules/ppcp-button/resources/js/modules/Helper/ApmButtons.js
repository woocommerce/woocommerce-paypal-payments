
export const apmButtonsInit = (selector = '.ppcp-button-apm') => {
    if (window.ppcpApmButtons) {
        return;
    }
    window.ppcpApmButtons = new ApmButtons(selector);
}

export class ApmButtons {

    constructor(selector) {
        this.selector = selector;
        this.containers = [];

        // Reloads button containers.
        this.reloadContainers();

        // Refresh button layout.
        jQuery(window).resize(() => {
            this.refresh();
        }).resize();

        // Observes for new buttons.
        (new MutationObserver(this.observeElementsCallback.bind(this)))
            .observe(document.body, { childList: true, subtree: true });
    }

    observeElementsCallback(mutationsList, observer) {
        const observeSelector = this.selector + ', .widget_shopping_cart, .widget_shopping_cart_content';

        let shouldReload = false;
        for (let mutation of mutationsList) {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach(node => {
                    if (node.matches && node.matches(observeSelector)) {
                        shouldReload = true;
                    }
                });
            }
        }

        if (shouldReload) {
            this.reloadContainers();
            this.refresh();
        }
    };

    reloadContainers() {
        jQuery(this.selector).each((index, el) => {
            const parent = jQuery(el).parent();
            if (!this.containers.some($el => $el.is(parent))) {
                this.containers.push(parent);
            }
        });
        console.log('this.containers', this.containers);
    }

    refresh() {
        for (const container of this.containers) {
            const $container = jQuery(container);

            // Check width and add classes
            const width = $container.width();

            $container.removeClass('ppcp-width-500 ppcp-width-300 ppcp-width-min');

            if (width >= 500) {
                $container.addClass('ppcp-width-500');
            } else if (width >= 300) {
                $container.addClass('ppcp-width-300');
            } else {
                $container.addClass('ppcp-width-min');
            }

            // Check first apm button
            const $firstApmButton = $container.find(this.selector + ':visible').first();
            const $firstElement = $container.children(':visible').first();

            let isFirstElement = false;
            if ($firstApmButton.is($firstElement)) {
                isFirstElement = true;
            }

            // Assign margins to buttons
            $container.find(this.selector).each((index, el) => {
                const $el = jQuery(el);
                const height = $el.height();

                if (isFirstElement) {
                    $el.css('margin-top', `0px`);
                    return true;
                }

                $el.css('margin-top', `${Math.round(height * 0.3)}px`);
            });

        }
    }

}
