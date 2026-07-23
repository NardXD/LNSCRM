'use strict';

const { spawnSync } = require('child_process');
const path = require('path');

const projectDir = path.join(__dirname, '..');

/**
 * Runs npm scripts sequentially without relying on shell `&&`
 * (more reliable on Windows PowerShell / mixed npm versions).
 */
function runNpmScript(scriptName) {
  const result = spawnSync('npm', ['run', scriptName], {
    cwd: projectDir,
    stdio: 'inherit',
    shell: true,
    env: process.env,
  });

  if (result.status !== 0 && result.status !== null) {
    process.exit(result.status);
  }

  if (result.error) {
    console.error(result.error);
    process.exit(1);
  }
}

runNpmScript('package:win');
runNpmScript('pack:ui');
console.log('\nDone. Next: compile installer.iss with Inno Setup (from clients/windows-recorder).');
