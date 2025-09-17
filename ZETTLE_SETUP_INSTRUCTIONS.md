# Zettle OAuth Setup Instructions

## Prerequisites
1. You need a Zettle developer account
2. Create an OAuth application at https://developer.zettle.com

## Setup Steps

### 1. Get your Zettle Client ID
- Go to https://developer.zettle.com
- Create a new OAuth application or use an existing one
- Copy your Client ID (not the client secret)
- Set the redirect URI to: `https://yoursite.com/wp-admin/admin.php?page=wc-zettle-settings`

### 2. Configure the Plugin
1. In WordPress admin, go to **WooCommerce > Zettle**
2. Enter your Zettle Client ID
3. Click **Save Settings**

### 3. Authorize with Zettle
1. Click **Connect to Zettle** button
2. You'll be redirected to Zettle to authorize
3. Log in with your Zettle account
4. Approve the permissions
5. You'll be redirected back to WordPress

### 4. Verify Connection
- The status should show "Connected to Zettle"
- You'll see the token expiration time
- The mobile app can now use PayPal card readers

## Troubleshooting

### Check Logs
Enable WordPress debug logging to see detailed error messages:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Check the debug log at: `wp-content/debug.log`

### Common Issues

1. **"Zettle Client ID is not configured"**
   - Go to WooCommerce > Zettle
   - Enter your Client ID and save

2. **"Invalid OAuth state"**
   - The authorization process timed out (10 minutes)
   - Try clicking "Connect to Zettle" again

3. **"Failed to exchange code for token"**
   - Check that your redirect URI in Zettle matches exactly
   - Ensure your Client ID is correct

### Testing the Token Endpoint

Once connected, test the token endpoint:

```bash
curl -X GET https://yoursite.com/wp-json/wc/v3/zettle/access-token \
  -u consumer_key:consumer_secret
```

You should get a response with an access token:
```json
{
  "access_token": "eyJ...",
  "token_type": "Bearer",
  "expires_in": 7200,
  "scope": "READ:PAYMENT READ:USERINFO WRITE:PAYMENT WRITE:REFUND2 WRITE:USERINFO",
  "issued_at": 1234567890
}
```

## Mobile App Configuration

The mobile app will automatically use the site-managed tokens when:
1. The site is connected to Zettle (completed above)
2. PayPal card reader is selected in the app
3. Card reader discovery is initiated

The app fetches fresh tokens from the backend automatically - no manual configuration needed in the app!