# Pull Request Playground Testing

Every pull request in the WooCommerce PayPal Payments repository automatically gets a one-click WordPress Playground link for testing changes in a live browser-based environment.

## Overview

When a pull request is opened or updated, a GitHub Actions workflow builds the plugin and posts a comment on the PR with a link to a WordPress Playground instance. This allows developers and QA testers to validate changes without setting up a local environment.

The Playground environment includes:

- WordPress (latest)
- WooCommerce (latest)
- PayPal Payments built from the PR branch

## How It Works

1. A pull request is opened, updated, or reopened
2. The **PR Playground Demo** workflow waits for the **Build and distribute** workflow to finish building the plugin
3. The built plugin artifact is re-uploaded with a PR-specific version name
4. A bot comments on the PR with a WordPress Playground link
5. When new commits are pushed to the PR, the comment is automatically updated with a fresh link

## Using the Playground

1. Open the pull request on GitHub
2. Find the bot comment titled **Test using WordPress Playground**
3. Click the Playground link
4. Log in with the credentials below

### Login Credentials

- **Username:** `admin`
- **Password:** `password`

### Landing Page

The Playground opens on the WooCommerce Blueprints setup page (`/wp-admin/admin.php?page=wc-settings&tab=advanced&section=blueprint`). From there you can import a PayPal Blueprint to configure the plugin, or navigate to any other area of the admin.

### Environment Details

- PHP 8.0
- Networking enabled (PayPal API calls work)
- WooCommerce onboarding wizard is skipped
- Coming Soon mode is enabled (required for Blueprint imports)
- The environment resets each time you refresh the page

## Limitations

- **Main repository only** — Playground links are only generated for PRs in the `woocommerce/woocommerce-paypal-payments` repository, not from forks. This is a security measure to prevent untrusted code from being built and distributed.
- **30-day expiry** — Playground links are valid for 30 days from when the comment was last updated, as GitHub Artifacts expire after that period.
- **Build dependency** — The Playground link is only posted if the Build and distribute workflow completes successfully. If the build fails, no comment is posted.
- **Browser-based** — The Playground runs entirely in the browser. Performance may vary depending on your machine and browser.

## Workflow Details

The feature is powered by two files:

- `.github/workflows/pr-playground-demo.yml` — The GitHub Actions workflow that orchestrates the build fetch, artifact upload, and comment creation.
- `.github/scripts/playground-comment.js` — The script that generates the WordPress Playground Blueprint and posts or updates the PR comment.

### Artifact Naming

Plugin artifacts are versioned using the format:

```
woocommerce-paypal-payments-{BASE_VERSION}-pr{PR_NUMBER}-{RUN_ID}-g{SHORT_SHA}
```

This ensures each build is uniquely identifiable and traceable to a specific commit.
