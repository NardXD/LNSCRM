/* global recorder */

const $ = (id) => document.getElementById(id);

const statusEl = $('status');
const panelLogin = $('panelLogin');
const panelApp = $('panelApp');
const tbodyServer = $('tbodyServer');
const tbodyLocal = $('tbodyLocal');
const btnTimeIn = $('btnTimeIn');
const btnTimeOut = $('btnTimeOut');
const btnLogout = $('btnLogout');
const btnSignOutRecorder = $('btnSignOutRecorder');
const nextRecordingCountdown = $('nextRecordingCountdown');
const timeInDetailsSection = $('timeInDetailsSection');
const timeInDetailsDisplay = $('timeInDetailsDisplay');
const btnServerPrev = $('btnServerPrev');
const btnServerNext = $('btnServerNext');
const serverPageInfo = $('serverPageInfo');
const btnLocalPrev = $('btnLocalPrev');
const btnLocalNext = $('btnLocalNext');
const localPageInfo = $('localPageInfo');

let deviceId = '';
let hourlyTimer = null;
let countdownTimer = null;
let nextRecordingAtMs = null;
let captureInProgress = false;
let mediaRecorder = null;
let recordedChunks = [];
let recordStartedAt = 0;
let mediaStream = null;
let isLoggedIn = false;
const PAGE_SIZE = 5;
let serverRows = [];
let localRows = [];
let serverPage = 1;
let localPage = 1;

function setStatus(text) {
  statusEl.textContent = text;
}

function shortId(id) {
  if (!id) {
    return '—';
  }

  return id.length > 12 ? `${id.slice(0, 8)}…` : id;
}

function stopMediaTracks() {
  if (mediaStream) {
    mediaStream.getTracks().forEach((t) => t.stop());
  }
  mediaStream = null;
}

function applyTimeClockVisibility(clockedIn) {
  btnTimeIn.classList.toggle('panel-hidden', clockedIn);
  btnTimeOut.classList.toggle('panel-hidden', !clockedIn);
  btnLogout.classList.toggle('panel-hidden', !clockedIn);
  btnSignOutRecorder.classList.toggle('panel-hidden', clockedIn);
}

async function refreshTimeClockUi() {
  if (!isLoggedIn) {
    return;
  }

  try {
    const { clockedIn, record } = await window.recorder.getTimeTrackingStatus();
    applyTimeClockVisibility(clockedIn);
    if (clockedIn && record?.date && record?.time_in) {
      timeInDetailsSection.classList.remove('panel-hidden');
      const timeIn = String(record.time_in).slice(0, 8);
      timeInDetailsDisplay.textContent = `${record.date} ${timeIn}`;
    } else {
      timeInDetailsSection.classList.add('panel-hidden');
      timeInDetailsDisplay.textContent = '—';
    }
  } catch {
    applyTimeClockVisibility(false);
    timeInDetailsSection.classList.add('panel-hidden');
    timeInDetailsDisplay.textContent = '—';
  }
}

function updateCountdownUi() {
  if (!nextRecordingAtMs || !isLoggedIn) {
    nextRecordingCountdown.textContent = '--:--';
    return;
  }

  const remainingMs = Math.max(0, nextRecordingAtMs - Date.now());
  const totalSeconds = Math.floor(remainingMs / 1000);
  const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
  const seconds = String(totalSeconds % 60).padStart(2, '0');
  nextRecordingCountdown.textContent = `${minutes}:${seconds}`;
}

function setNextRecordingTime(targetMs) {
  nextRecordingAtMs = targetMs;
  updateCountdownUi();
}

function startCountdownTimer() {
  if (countdownTimer) {
    clearInterval(countdownTimer);
  }
  countdownTimer = setInterval(updateCountdownUi, 1000);
}

function stopCountdownTimer() {
  if (countdownTimer) {
    clearInterval(countdownTimer);
    countdownTimer = null;
  }
  nextRecordingAtMs = null;
  updateCountdownUi();
}

function renderTablePage(rows, page, tbodyEl, columns, emptyText) {
  tbodyEl.innerHTML = '';
  if (rows.length === 0) {
    const tr = document.createElement('tr');
    tr.innerHTML = `<td colspan="${columns}">${emptyText}</td>`;
    tbodyEl.appendChild(tr);
    return;
  }
  const start = (page - 1) * PAGE_SIZE;
  const pageRows = rows.slice(start, start + PAGE_SIZE);
  for (const tr of pageRows) {
    tbodyEl.appendChild(tr);
  }
}

function renderServerPage() {
  const totalPages = Math.max(1, Math.ceil(serverRows.length / PAGE_SIZE));
  serverPage = Math.min(Math.max(1, serverPage), totalPages);
  renderTablePage(serverRows, serverPage, tbodyServer, 4, 'No uploads yet for today.');
  serverPageInfo.textContent = `Page ${serverPage} / ${totalPages}`;
  btnServerPrev.disabled = serverPage <= 1;
  btnServerNext.disabled = serverPage >= totalPages;
}

function renderLocalPage() {
  const totalPages = Math.max(1, Math.ceil(localRows.length / PAGE_SIZE));
  localPage = Math.min(Math.max(1, localPage), totalPages);
  renderTablePage(localRows, localPage, tbodyLocal, 3, 'Nothing waiting on this device.');
  localPageInfo.textContent = `Page ${localPage} / ${totalPages}`;
  btnLocalPrev.disabled = localPage <= 1;
  btnLocalNext.disabled = localPage >= totalPages;
}

async function refreshDashboard() {
  if (!isLoggedIn) {
    return;
  }

  try {
    const { server, local } = await window.recorder.getTodaysDashboard();

    serverRows = [];
    for (const row of server) {
      const tr = document.createElement('tr');
      const t = row.created_at ? new Date(row.created_at).toLocaleTimeString() : '—';
      tr.innerHTML = `<td>${t}</td><td>${row.screen_recording_duration ?? 0}s</td><td>${row.sync_status}</td><td>${shortId(row.upload_id)}</td>`;
      serverRows.push(tr);
    }
    serverPage = 1;
    renderServerPage();

    localRows = [];
    for (const row of local) {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${row.duration}s</td><td>${row.retryCount}</td><td>${shortId(row.uploadId)}</td>`;
      localRows.push(tr);
    }
    localPage = 1;
    renderLocalPage();
    await refreshTimeClockUi();
  } catch (e) {
    setStatus(`Could not refresh list: ${e?.message || e}`);
  }
}

async function tryAutoSync() {
  if (!isLoggedIn || !navigator.onLine) {
    return;
  }

  try {
    await window.recorder.sync();
    setStatus(`${statusEl.textContent}\nAuto-sync finished.`);
    await refreshDashboard();
  } catch (e) {
    setStatus(`${statusEl.textContent}\nAuto-sync skipped: ${e?.message || e}`);
  }
}

function stopHourlySchedule() {
  if (hourlyTimer) {
    clearInterval(hourlyTimer);
    hourlyTimer = null;
  }
}

async function runTimedRecording(durationSec) {
  if (captureInProgress) {
    return;
  }

  captureInProgress = true;
  recordedChunks = [];
  setStatus(`Starting ${durationSec}s capture…`);

  try {
    mediaStream = await navigator.mediaDevices.getDisplayMedia({
      video: true,
      audio: true,
    });
    const options = { mimeType: 'video/webm; codecs=vp9' };
    if (!MediaRecorder.isTypeSupported(options.mimeType)) {
      options.mimeType = 'video/webm';
    }
    mediaRecorder = new MediaRecorder(mediaStream, options);
    mediaRecorder.ondataavailable = (ev) => {
      if (ev.data && ev.data.size > 0) {
        recordedChunks.push(ev.data);
      }
    };
    mediaRecorder.onerror = (ev) => {
      setStatus(`Recorder error: ${ev.error?.message || ev}`);
    };
    mediaRecorder.start(250);
    recordStartedAt = Date.now();

    await new Promise((resolve) => {
      let settled = false;
      const done = () => {
        if (settled) {
          return;
        }
        settled = true;
        clearTimeout(timer);
        resolve();
      };
      const timer = setTimeout(() => {
        mediaRecorder.onstop = done;
        try {
          mediaRecorder.requestData();
        } catch {
          // Older runtimes may omit requestData.
        }
        mediaRecorder.stop();
      }, durationSec * 1000);
      mediaRecorder.addEventListener('error', done, { once: true });
    });

    stopMediaTracks();

    const blob = new Blob(recordedChunks, { type: 'video/webm' });
    const durationActual = Math.max(1, Math.round((Date.now() - recordStartedAt) / 1000));
    const uploadId = crypto.randomUUID();
    const { date } = await window.recorder.getCompanyDateYmd();
    const fileName = `${uploadId}.webm`;

    const arrayBuffer = await blob.arrayBuffer();
    const { path } = await window.recorder.saveRecording(arrayBuffer, fileName);
    await window.recorder.queueRecording({
      uploadId,
      date,
      duration: durationActual,
      filePath: path,
    });

    setStatus(`Saved ${durationActual}s clip to queue.`);
    await refreshDashboard();
    await tryAutoSync();
  } catch (e) {
    setStatus(`Capture failed: ${e?.message || e}`);
    stopMediaTracks();
  } finally {
    mediaRecorder = null;
    recordedChunks = [];
    captureInProgress = false;
  }
}

async function startSessionAfterLogin() {
  isLoggedIn = true;
  panelLogin.classList.add('panel-hidden');
  panelApp.classList.remove('panel-hidden');
  startCountdownTimer();
  await refreshDashboard();

  await runTimedRecording(30);

  setNextRecordingTime(Date.now() + 60 * 60 * 1000);
  stopHourlySchedule();
  hourlyTimer = setInterval(() => {
    if (isLoggedIn) {
      void runTimedRecording(30).then(() => {
        setNextRecordingTime(Date.now() + 60 * 60 * 1000);
      });
    }
  }, 60 * 60 * 1000);

  setStatus('Hourly 30-second capture schedule is running (next run in one hour).');
}

async function initDeviceId() {
  try {
    const { deviceId: id } = await window.recorder.getDeviceId();
    deviceId = id;
  } catch (e) {
    setStatus(`Device ID initialization failed: ${e?.message || e}`);
  }
}

void initDeviceId();

$('btnLogin').addEventListener('click', async () => {
  setStatus('Signing in…');
  try {
    if (!deviceId) {
      const { deviceId: id } = await window.recorder.getDeviceId();
      deviceId = id;
    }
    await window.recorder.login({
      apiBase: $('apiBase').value.trim(),
      companySubdomain: $('companySubdomain').value.trim(),
      email: $('email').value.trim(),
      password: $('password').value,
      deviceId,
    });
    setStatus('Signed in. Starting first capture…');
    await startSessionAfterLogin();
  } catch (e) {
    setStatus(`Login failed: ${e?.message || e}`);
  }
});

$('btnTimeIn').addEventListener('click', async () => {
  setStatus('Recording time in…');
  try {
    await window.recorder.timeIn();
    setStatus('Time in recorded.');
    await refreshTimeClockUi();
  } catch (e) {
    setStatus(`Time in failed: ${e?.message || e}`);
  }
});

$('btnTimeOut').addEventListener('click', async () => {
  setStatus('Recording time out…');
  try {
    await window.recorder.timeOut();
    setStatus('Time out recorded.');
    await refreshTimeClockUi();
  } catch (e) {
    setStatus(`Time out failed: ${e?.message || e}`);
  }
});

$('btnSync').addEventListener('click', async () => {
  setStatus('Syncing…');
  try {
    await window.recorder.sync();
    setStatus('Sync finished.');
    await refreshDashboard();
  } catch (e) {
    setStatus(`Sync failed: ${e?.message || e}`);
  }
});

async function signOutOfRecorder() {
  stopHourlySchedule();
  if (mediaRecorder && mediaRecorder.state !== 'inactive') {
    try {
      mediaRecorder.stop();
    } catch {
      // ignore
    }
  }
  stopMediaTracks();
  stopCountdownTimer();
  captureInProgress = false;
  isLoggedIn = false;

  try {
    await window.recorder.logout();
  } catch (e) {
    setStatus(`Logout warning: ${e?.message || e}`);
  }

  panelApp.classList.add('panel-hidden');
  panelLogin.classList.remove('panel-hidden');
  serverRows = [];
  localRows = [];
  renderServerPage();
  renderLocalPage();
  btnTimeIn.classList.remove('panel-hidden');
  btnTimeOut.classList.add('panel-hidden');
  btnLogout.classList.add('panel-hidden');
  btnSignOutRecorder.classList.remove('panel-hidden');
  timeInDetailsSection.classList.add('panel-hidden');
  timeInDetailsDisplay.textContent = '—';
  setStatus('Signed out.');
}

$('btnLogout').addEventListener('click', () => void signOutOfRecorder());
$('btnSignOutRecorder').addEventListener('click', () => void signOutOfRecorder());

window.addEventListener('online', () => {
  void tryAutoSync();
});

btnServerPrev.addEventListener('click', () => {
  serverPage -= 1;
  renderServerPage();
});
btnServerNext.addEventListener('click', () => {
  serverPage += 1;
  renderServerPage();
});
btnLocalPrev.addEventListener('click', () => {
  localPage -= 1;
  renderLocalPage();
});
btnLocalNext.addEventListener('click', () => {
  localPage += 1;
  renderLocalPage();
});

renderServerPage();
renderLocalPage();
updateCountdownUi();
