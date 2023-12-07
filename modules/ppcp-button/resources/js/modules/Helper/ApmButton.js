
export const apmButtonInit = (selector = '.ppcp-button-apm') => {
    if (window.apmButton) {
        return;
    }
    window.apmButton = new ApmButton(selector);
}

export class ApmButton {

    constructor(selector) {
        this.selector = selector;
        this.containers = [];

        jQuery(window).resize(() => {
            this.refresh();
        }).resize();

        // TODO: detect changes don't rely on setInterval
        setInterval(() => {
            jQuery(selector).each((index, el) => {
                const parent = jQuery(el).parent();
                if (!this.containers.some($el => $el.is(parent))) {
                    this.containers.push(parent);
                }
            });

            this.refresh();
        }, 100);
    }

    refresh() {
        for (const container of this.containers) {
            const containerClasses = [];
            const $container = jQuery(container);

            // Check width and add classes
            const width = $container.width();

            if (width >= 500) {
                containerClasses.push('ppcp-width-500');
            } else if (width >= 300) {
                containerClasses.push('ppcp-width-300');
            } else {
                containerClasses.push('ppcp-width-min');
            }

            $container.removeClass('ppcp-width-500 ppcp-width-300 ppcp-width-min');
            $container.addClass(containerClasses.join(' '));

            // Check first apm button
            const $firstApmButton = $container.find(this.selector + ':visible').first();
            const $firstElement = $container.children(':visible').first();

            let $spacingTopButton = null;
            if (!$firstApmButton.is($firstElement)) {
                $spacingTopButton = $firstApmButton;
            }

            // Check last apm button
            const $lastApmButton = $container.find(this.selector + ':visible').last();

            // Assign margins to buttons
            $container.find(this.selector).each((index, el) => {
                const $el = jQuery(el);
                const height = $el.height();

                if ($el.is($spacingTopButton)) {
                    $el.css('margin-top', `${Math.round(height * 0.3)}px`);
                }

                if ($el.is($lastApmButton)) {
                    $el.css('margin-bottom', `0px`);
                    return true;
                }

                $el.css('margin-bottom', `${Math.round(height * 0.3)}px`);
            });

        }
    }

}
