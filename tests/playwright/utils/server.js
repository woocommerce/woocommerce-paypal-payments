const { exec } = require('node:child_process');

/**
 * Executes the command on the server (inside DDEV). Can be called inside and outside DDEV.
 */
export const serverExec = async (cmd) => {
    const isDdev = process.env.IS_DDEV_PROJECT === 'true';
    if (!isDdev) {
        cmd = 'ddev exec ' + cmd;
    }

    console.log(cmd);

    return new Promise((resolve) => exec(cmd, (error, stdout, stderr) => {
        if (stderr) {
            console.error(stderr);
        }
        if (stdout) {
            console.log(stdout);
        }
        if (error) {
            throw error;
        } else {
            resolve(stdout);
        }
    }))
}
