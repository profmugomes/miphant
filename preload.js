// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

const { contextBridge, ipcRenderer } = require('electron')

ipcRenderer.setMaxListeners(20);

contextBridge.exposeInMainWorld('miphant', {
    version: (type) => ipcRenderer.invoke('appVersao', type),
    alert: (title, msg, type, button) => ipcRenderer.invoke('appMessage', title, msg, type, button),
    confirm: (title, msg, type, ...buttons) => ipcRenderer.invoke('appConfirm', title, msg, type, ...buttons),
    newWindow: (url, width, height, resizable, frame, hide, menu) => ipcRenderer.invoke('appNewWindow', url, width, height, resizable, frame, hide, menu),
    openURL: (url) => ipcRenderer.invoke('appExterno', url),
    translate: (text, ...values) => ipcRenderer.invoke('appTraduzir', text, ...values),
    selectDirectory: () => ipcRenderer.invoke('appSelecionarDiretorio'),
    openFile: (multi) => ipcRenderer.invoke('appAbrirArquivo', multi),
    saveFile: () => ipcRenderer.invoke('appSalvarArquivo'),
    notification: (title, text) => ipcRenderer.invoke('appNotification', title, text),
    tray: (title, tooltip, icon, menu) => ipcRenderer.invoke('appTray', title, tooltip, icon, menu),
    fileExists: (filename) => ipcRenderer.invoke('appFileExists', filename),
    exportPDF: (filename, options) => ipcRenderer.invoke('appExportPDF', filename, options),
    devTools: () => ipcRenderer.invoke('appDevTools'),
    close: () => ipcRenderer.invoke('appSair'),
});