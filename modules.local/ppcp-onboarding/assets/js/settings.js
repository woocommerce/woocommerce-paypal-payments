(() => {
    document.querySelector('#field-toggle_manual_input').addEventListener(
        'click',
        (event) => {
            event.preventDefault();
            document.querySelector('#field-toggle_manual_input').style.display = 'none';
            document.querySelector('#field-client_id').style.display = 'table-row';
            document.querySelector('#field-client_secret').style.display = 'table-row';
        }
    )
})()