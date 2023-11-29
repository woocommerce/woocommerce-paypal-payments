export const inputValue = (element) => {
    const $el = jQuery(element);

    if ($el.is(':checkbox') || $el.is(':radio')) {
        if ($el.is(':checked')) {
            return $el.val();
        } else {
            return null;
        }
    } else {
        return $el.val();
    }
}
