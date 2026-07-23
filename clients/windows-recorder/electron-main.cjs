'use strict';

const { app, BrowserWindow, ipcMain, session, desktopCapturer } = require('electron');
const path = require('path');
const fs = require('fs/promises');
const { pathToFileURL } = require('url');

let mainWindow = null;
let RecorderClientClass = null;
let recorderInstance = null;

async function loadRecorderClass() {
  if (RecorderClientClass) {
    return RecorderClientClass;
  }

  const clientPath = path.join(__dirname, 'dist', 'recorder-client.js');
  const mod = await import(pathToFileURL(clientPath).href);
  RecorderClientClass = mod.RecorderClient;

  return RecorderClientClass;
}

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 620,
    height: 920,
    webPreferences: {
      preload: path.join(__dirname, 'preload.cjs'),
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: false,
    },
  });

  mainWindow.loadFile(path.join(__dirname, 'public', 'index.html'));
}

app.whenReady().then(async () => {
  session.defaultSession.setDisplayMediaRequestHandler((_request, callback) => {
    desktopCapturer
      .getSources({ types: ['screen'], thumbnailSize: { width: 1, height: 1 } })
      .then((sources) => {
        const first = sources[0];
        if (first) {
          callback({ video: first, audio: 'loopback' });
        } else {
          callback({});
        }
      })
      .catch(() => {
        callback({});
      });
  });

  await loadRecorderClass();
  createWindow();

  ipcMain.handle('login', async (_evt, payload) => {
    const apiBase = String(payload.apiBase || '')
      .trim()
      .replace(/\/$/, '') || 'http://localhost:8000';
    const RC = await loadRecorderClass();
    recorderInstance = new RC(apiBase);
    await recorderInstance.initialize();
    await recorderInstance.login({
      email: String(payload.email || '').trim(),
      password: String(payload.password || ''),
      companySubdomain: String(payload.companySubdomain || '').trim(),
      deviceId: String(payload.deviceId || '').trim() || 'windows-desktop',
    });

    const timezone = await recorderInstance.readCompanyTimezone();

    return { ok: true, timezone };
  });

  ipcMain.handle('logout', async () => {
    const RC = await loadRecorderClass();
    const client = recorderInstance ?? new RC('http://localhost');
    await client.logout();
    recorderInstance = null;

    return { ok: true };
  });

  ipcMain.handle('getDeviceId', async () => {
    const RC = await loadRecorderClass();
    const deviceId = await RC.getOrCreateDeviceId();

    return { deviceId };
  });

  ipcMain.handle('getTodaysDashboard', async () => {
    if (!recorderInstance) {
      return { server: [], local: [] };
    }

    const companyDate = await recorderInstance.getCompanyDateYmd();
    const [server, queue] = await Promise.all([
      recorderInstance.fetchTodaysRecordings(),
      recorderInstance.getQueue(),
    ]);
    const local = queue.filter((item) => item.date === companyDate);

    return { server, local };
  });

  ipcMain.handle('getCompanyDateYmd', async () => {
    if (!recorderInstance) {
      throw new Error('Log in first.');
    }

    return { date: await recorderInstance.getCompanyDateYmd() };
  });

  ipcMain.handle('timeIn', async () => {
    if (!recorderInstance) {
      throw new Error('Log in first.');
    }
    await recorderInstance.timeIn();

    return { ok: true };
  });

  ipcMain.handle('timeOut', async () => {
    if (!recorderInstance) {
      throw new Error('Log in first.');
    }
    await recorderInstance.timeOut();

    return { ok: true };
  });

  ipcMain.handle('getTimeTrackingStatus', async () => {
    if (!recorderInstance) {
      throw new Error('Log in first.');
    }

    return await recorderInstance.fetchTimeTrackingStatus();
  });

  ipcMain.handle('sync', async () => {
    if (!recorderInstance) {
      throw new Error('Log in first.');
    }
    await recorderInstance.syncQueue();

    return { ok: true };
  });

  ipcMain.handle('saveRecording', async (_evt, arrayBuffer, fileName) => {
    const buf = Buffer.from(arrayBuffer);
    const safeName = String(fileName || 'recording.webm').replace(/[^a-zA-Z0-9._-]/g, '_');
    const tmp = path.join(app.getPath('temp'), `iwpr-${Date.now()}-${safeName}`);
    await fs.writeFile(tmp, buf);

    return { path: tmp };
  });

  ipcMain.handle('queueRecording', async (_evt, meta) => {
    if (!recorderInstance) {
      throw new Error('Log in first.');
    }
    await recorderInstance.queueRecording({
      uploadId: meta.uploadId,
      date: meta.date,
      duration: meta.duration,
      filePath: meta.filePath,
      checksum: meta.checksum,
    });

    return { ok: true };
  });

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});
