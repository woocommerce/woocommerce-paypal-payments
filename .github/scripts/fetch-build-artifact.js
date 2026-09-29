/**
 * Waits for the "Build and distribute" run for the PR head commit and downloads its artifact zip.
 *
 * Sets the `has_build` output to 'true' once the artifact is downloaded, or 'false' when no
 * completed build exists for the commit.
 */
const run = async ({ github, context, core, zipPath, maxWait = 5 * 60 * 1000 }) => {
    const fs = require('fs');
    const headSha = context?.payload?.pull_request?.head?.sha;
    if (!headSha) {
        core.setFailed('No head SHA found for pull request in context');
        return;
    }
    core.info(`Looking for Build and distribute run for SHA: ${headSha}`);

    // Find the "Build and distribute" workflow
    const workflows = await github.rest.actions.listRepoWorkflows({
        owner: context.repo.owner,
        repo: context.repo.repo,
    });
    const bdWorkflow = workflows.data.workflows.find(w => w.name === 'Build and distribute');
    if (!bdWorkflow) {
        core.setFailed('Could not find "Build and distribute" workflow');
        return;
    }
    core.info(`Found workflow ID: ${bdWorkflow.id}`);

    // Poll for the workflow run to appear and complete
    const interval = 30 * 1000;
    const start = Date.now();

    let run = null;
    while (Date.now() - start < maxWait) {
        const runs = await github.rest.actions.listWorkflowRuns({
            owner: context.repo.owner,
            repo: context.repo.repo,
            workflow_id: bdWorkflow.id,
            head_sha: headSha,
        });

        if (runs.data.workflow_runs.length > 0) {
            run = runs.data.workflow_runs[0];
            core.info(`Found run #${run.id} — status: ${run.status}, conclusion: ${run.conclusion}`);

            if (run.status === 'completed') {
                break;
            }
        }
        core.info('No completed run found yet, waiting...');

        await new Promise(r => setTimeout(r, interval));
    }

    // No B&D run found — build workflow did not trigger for this commit, skip gracefully
    if (!run) {
        core.info('No Build and distribute run found for this commit — skipping');
        core.setOutput('has_build', 'false');
        return;
    }
    if (run.status !== 'completed') {
        core.info('Build and distribute did not complete in time — skipping');
        core.setOutput('has_build', 'false');
        return;
    }
    if (run.conclusion !== 'success') {
        core.setFailed(`Build and distribute finished with: ${run.conclusion}`);
        return;
    }

    core.setOutput('has_build', 'true');

    // Download the artifact
    const artifacts = await github.rest.actions.listWorkflowRunArtifacts({
        owner: context.repo.owner,
        repo: context.repo.repo,
        run_id: run.id,
    });

    if (artifacts.data.artifacts.length === 0) {
        core.setFailed('No artifacts found in Build and distribute run');
        return;
    }

    const artifact = artifacts.data.artifacts[0];
    core.info(`Downloading artifact: ${artifact.name} (ID: ${artifact.id})`);

    const download = await github.rest.actions.downloadArtifact({
        owner: context.repo.owner,
        repo: context.repo.repo,
        artifact_id: artifact.id,
        archive_format: 'zip',
    });

    fs.writeFileSync(zipPath, Buffer.from(download.data));
    core.info(`Artifact downloaded to ${zipPath}`);
};

module.exports = { run };
