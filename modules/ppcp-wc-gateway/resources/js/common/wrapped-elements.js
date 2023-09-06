
// This function is needed because WordPress moves our custom notices to the global placeholder.
function moveWrappedElements() {
    (($) => {
        $('*[data-ppcp-wrapper]').each(function() {
            let $wrapper = $('.' + $(this).data('ppcpWrapper'));
            if ($wrapper.length) {
                $wrapper.append(this);
            }
        });
    })(jQuery)
}

export default moveWrappedElements;
