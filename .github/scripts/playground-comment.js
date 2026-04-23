const generateWordpressPlaygroundBlueprint = (runId, prNumber, artifactName) => {
    const defaultSchema = {
        landingPage: '/wp-admin/admin.php?page=wc-settings&tab=advanced&section=blueprint&activate-multi=true',

        preferredVersions: {
            php: '8.0',
            wp: 'latest',
        },

        phpExtensionBundles: ['kitchen-sink'],

        // Enable networking for API calls and external connections
        features: {
            networking: true
        },

        steps: [
            // Step 1: Install and activate WooCommerce
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

            // Step 2: Install PayPal Payments plugin from PR artifact
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

            // Step 3: Skip WooCommerce onboarding wizard
            {
                step: 'setSiteOptions',
                options: {
                    woocommerce_onboarding_profile: {
                        skipped: true,
                    },
                },
            },

            // Step 4: Enable Coming Soon mode
            {
                step: 'setSiteOptions',
                options: {
                    woocommerce_coming_soon: 'yes',
                },
            },

            // Step 5: Set up admin user login
            {
                step: 'login',
                username: 'admin',
                password: 'password',
            },
        ],

        // Initialize empty plugins array (can be extended later)
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

        // Check for existing playground comment to update instead of creating duplicate
        const comments = await github.rest.issues.listComments(commentInfo);

        const existingComment = comments.data.find(comment =>
            comment.user.type === 'Bot' &&
            comment.body.includes('Test using WordPress Playground')
        );

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
> 🔄 This link updates automatically with each new commit to the PR.
>
> ⚠️ This URL is valid for 30 days from when this comment was last updated.

---
<sub>🤖 Auto-generated for commit ${context.payload.pull_request.head.sha} • Last updated: ${new Date().toISOString()}</sub>`;

        if (existingComment) {
            // Update existing comment
            await github.rest.issues.updateComment({
                owner: context.repo.owner,
                repo: context.repo.repo,
                comment_id: existingComment.id,
                body: body
            });

            core.info(`Successfully updated existing playground comment #${existingComment.id} on PR #${context.issue.number}`);
        } else {
            // Create new comment
            await github.rest.issues.createComment({
                ...commentInfo,
                body: body
            });

            core.info(`Successfully created new playground comment on PR #${context.issue.number}`);
        }

    } catch (error) {
        core.setFailed(`Failed to create/update playground comment: ${error.message}`);
        core.error(`Error details: ${error.stack}`);
    }
}

module.exports = { run };
