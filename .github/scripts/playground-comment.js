const generateWordpressPlaygroundBlueprint = (runId, prNumber, artifactName) => {
    const defaultSchema = {
        landingPage: '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway',
        preferredVersions: {
            php: '8.0',
            wp: 'latest',
        },
        phpExtensionBundles: ['kitchen-sink'],
        features: { networking: true },
        steps: [
            {
                step: 'installPlugin',
                pluginData: {
                    resource: 'wordpress.org/plugins',
                    slug: 'woocommerce'
                },
                options: {
                    activate: true
                }
            },
            {
                step: 'installPlugin',
                pluginZipFile: {
                    resource: 'url',
                    url: `https://playground.wordpress.net/plugin-proxy.php?org=woocommerce&repo=woocommerce-paypal-payments&workflow=PR%20Playground%20Demo&artifact=${artifactName}&pr=${prNumber}`,
                },
                options: {
                    activate: true,
                },
            },
            {
                step: 'setSiteOptions',
                options: {
                    woocommerce_onboarding_profile: {
                        skipped: true,
                    },
                },
            },
            {
                step: 'login',
                username: 'admin',
                password: 'password',
            },
        ],
        plugins: [],
    };
    return defaultSchema;
};

async function run({ github, context, core }) {
    try {
        const commentInfo = {
            owner: context.repo.owner,
            repo: context.repo.repo,
            issue_number: context.issue.number,
        };

        // Validate required environment variables
        if (!process.env.PLUGIN_VERSION || !process.env.ARTIFACT_NAME) {
            core.setFailed('Missing required environment variables: PLUGIN_VERSION or ARTIFACT_NAME');
            return;
        }

        // Comments are only added after the first successful build.
        // The caller should check for existing comments before calling this function.

        const defaultSchema = generateWordpressPlaygroundBlueprint(
            context.runId,
            context.issue.number,
            process.env.ARTIFACT_NAME
        );

        const url = `https://playground.wordpress.net/#${JSON.stringify(defaultSchema)}`;

        const body = `## Test using WordPress Playground
The changes in this pull request can be previewed and tested using a [WordPress Playground](https://developer.wordpress.org/playground/) instance.
[WordPress Playground](https://developer.wordpress.org/playground/) is an experimental project that creates a full WordPress instance entirely within the browser.

**🔗 [Test this pull request with WordPress Playground](${url})**

### What's included:
- ✅ WordPress (latest)
- ✅ WooCommerce (latest)
- ✅ PayPal Payments plugin v${process.env.PLUGIN_VERSION} (built from this PR)

### Login credentials:
- **Username:** \`admin\`
- **Password:** \`password\`

### Plugin Details:
- **Version:** ${process.env.PLUGIN_VERSION}
- **Commit:** ${context.payload.pull_request.head.sha}
- **Artifact:** ${process.env.ARTIFACT_NAME}

> 💡 The demo environment resets each time you refresh. Perfect for testing!
>
> ⚠️ This URL is valid for 30 days from when this comment was last updated. You can refresh it by pushing a new commit.

---
<sub>🤖 Auto-generated for commit ${context.payload.pull_request.head.sha}</sub>`;

        // Create the comment
        commentInfo.body = body;
        await github.rest.issues.createComment(commentInfo);

        core.info('Successfully created playground comment on PR');

    } catch (error) {
        core.setFailed(`Failed to create playground comment: ${error.message}`);
    }
}

module.exports = { run };
