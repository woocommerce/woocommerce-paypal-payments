document.addEventListener('DOMContentLoaded', () => {
    const script = document.createElement('script');
    script.setAttribute('src', 'https://c.paypal.com/da/r/fb.js');

    console.log(script)

    document.body.append(script);
});
