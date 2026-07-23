'use strict';

const { spawnSync } = require('child_process');
const path = require('path');

process.env.ELECTRON_RUN_AS_NODE = '1';
const electronCli = path.join(__dirname, '..', 'node_modules', 'electron', 'cli.js');
const result = spawnSync(
  process.execPath,
  [electronCli, '-e', "console.log('electron ok', process.versions.electron)"],
  { stdio: 'inherit', env: process.env, shell: false },
);

process.exit(result.status === 0 ? 0 : 1);
