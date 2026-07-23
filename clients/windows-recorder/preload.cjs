'use strict';

const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('recorder', {
  login: (payload) => ipcRenderer.invoke('login', payload),
  logout: () => ipcRenderer.invoke('logout'),
  getDeviceId: () => ipcRenderer.invoke('getDeviceId'),
  getCompanyDateYmd: () => ipcRenderer.invoke('getCompanyDateYmd'),
  getTodaysDashboard: () => ipcRenderer.invoke('getTodaysDashboard'),
  timeIn: () => ipcRenderer.invoke('timeIn'),
  timeOut: () => ipcRenderer.invoke('timeOut'),
  getTimeTrackingStatus: () => ipcRenderer.invoke('getTimeTrackingStatus'),
  sync: () => ipcRenderer.invoke('sync'),
  saveRecording: (arrayBuffer, fileName) => ipcRenderer.invoke('saveRecording', arrayBuffer, fileName),
  queueRecording: (meta) => ipcRenderer.invoke('queueRecording', meta),
});
