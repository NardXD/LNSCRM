import { spawnSync } from 'node:child_process';
import { RecorderClient } from './recorder-client.js';

function showWindowsNotice(title: string, text: string): void {
  const escapePs = (s: string): string => s.replace(/'/g, "''");
  const command = `[void][System.Reflection.Assembly]::LoadWithPartialName('System.Windows.Forms'); [System.Windows.Forms.MessageBox]::Show('${escapePs(text)}','${escapePs(title)}')`;
  const r = spawnSync('powershell.exe', [
    '-NoProfile',
    '-ExecutionPolicy',
    'Bypass',
    '-WindowStyle',
    'Hidden',
    '-Command',
    command,
  ], {
    windowsHide: true,
    encoding: 'utf-8',
  });
  if (r.status !== 0) {
    const cmdSafe = `${title}: ${text}`.replace(/[&<>|]/g, ' ').slice(0, 240);
    spawnSync('cmd.exe', ['/c', 'start', 'cmd', '/k', `echo ${cmdSafe} && echo. && pause`], {
      windowsHide: true,
      stdio: 'ignore',
    });
  }
}

async function run(): Promise<void> {
  const client = new RecorderClient(process.env.RECORDER_API_URL ?? 'http://localhost:8000');
  await client.initialize();
  await client.syncQueue();
  if (process.platform === 'win32' && process.env.RECORDER_HEADLESS !== '1') {
    showWindowsNotice(
      'ItsWorkPlace Recorder',
      'Upload sync finished. If you had files in the queue, they were processed. Click OK to close.',
    );
  }
}

void run().catch((err: unknown) => {
  if (process.platform === 'win32' && process.env.RECORDER_HEADLESS !== '1') {
    showWindowsNotice(
      'ItsWorkPlace Recorder',
      `Something went wrong:\n${err instanceof Error ? err.message : String(err)}`,
    );
  }
  process.exitCode = 1;
});
