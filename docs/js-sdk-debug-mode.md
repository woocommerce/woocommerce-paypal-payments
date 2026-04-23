# PayPal JS SDK debug mode
## Enabling debug mode
Set `WP_DEBUG` constant to `true`. This will add the `debug` [query parameter](https://developer.paypal.com/sdk/js/configuration/#debug) to the script URL, enabling debug mode. 
## What debug mode does
When debug mode is enabled, logging in browser console becomes much more detailed. Also, JS SDK is non-minified.
There may be other debug features, but official PayPal documentation says almost nothing about debug mode.
