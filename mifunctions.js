// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

// Site: https://www.profmugomes.com.br

const { ipcMain, dialog, BrowserWindow, shell, Notification, Tray, Menu, nativeImage } = require('electron');
const fs = require('fs');
const path = require('path');

module.exports = {
    // isOfficialSender: valida que o IPC veio de uma pagina do proprio
    // servidor interno (evita que uma pagina externa use a bridge nativa)
    mifunctions: function (win, milang, miphantNewWindow, isOfficialSender) {
        const assertOfficialSender = (event) => {
            if (typeof isOfficialSender !== 'function' || !isOfficialSender(event)) {
                throw new Error('Unauthorized IPC request');
            }
        };

        const register = (channel, handler) => {
            ipcMain.handle(channel, async (event, ...args) => {
                assertOfficialSender(event);
                return handler(event, ...args);
            });
        };

        // Função para selecionar pasta
        register('appSelecionarDiretorio', async () => {
            const { canceled, filePaths } = await dialog.showOpenDialog({ properties: ['openDirectory'] });
            if (!canceled) {
                return filePaths[0];
            }
        });

        // Função para abrir arquivo
        register('appAbrirArquivo', async (_event, multi) => {
            let sProperties = [
                'openFile',
                (multi) ? 'multiSelections' : ''
            ]
            const { canceled, filePaths } = await dialog.showOpenDialog({ properties: sProperties });
            if (!canceled) {
                if (multi) {
                    return filePaths;
                } else {
                    return filePaths[0];
                }
            }
        });

        // Função para salvar arquivo
        register('appSalvarArquivo', async () => {
            const { canceled, filePath } = await dialog.showSaveDialog({});
            if (!canceled) {
                return filePath;
            }
        });

        // Abrir aplicativo externo
        register('appExterno', async (_event, url) => {
            shell.openExternal(url);
        });

        // Obter versão do aplicativo e recursos
        register('appVersao', async (_event, tipo) => {
            if (tipo == 'miphant') {
                return require('electron').app.getVersion();
            } else if (tipo == 'electron') {
                return process.versions.electron;
            } else if (tipo == 'node') {
                return process.versions.node;
            } else if (tipo == 'chromium') {
                return process.versions.chrome;
            }

            return '';
        });

        // Função para caixa de alerta
        register('appMessage', async (_event, title, msg, type, button) => {
            let sButtons = [button];

            let options = {
                type: type,
                buttons: sButtons,
                defaultId: 1,
                cancelId: 2,
                title: title,
                message: msg
            }
            return dialog.showMessageBoxSync(null, options);
        });

        // Função para caixa de confirmação
        register('appConfirm', async (_event, title, msg, type, ...buttons) => {
            let sButtons = [...buttons];

            let options = {
                type: type,
                buttons: sButtons,
                defaultId: 1,
                cancelId: 2,
                title: title,
                message: msg
            }
            return dialog.showMessageBoxSync(null, options);
        });

        // Abre uma nova janela personalizada
        register('appNewWindow', async (_event, url, width, height, resizable, frame, hide, menu) => {
            miphantNewWindow(url, width, height, resizable, frame, hide, menu);
        });

        // Traduzir
        register('appTraduzir', async (_event, text, ...values) => {
            return milang.traduzir(text, ...values);
        });

        // DevTools
        register('appDevTools', async () => {
            const focusedWindow = BrowserWindow.getFocusedWindow();
            if (focusedWindow) {
                focusedWindow.webContents.openDevTools();
            }
        });

        // Notification
        register('appNotification', async (_event, title, text) => {
            new Notification({ title: title, body: text }).show();
        });

        // Check File Exists
        register('appFileExists', async (_event, filename) => {
            return fs.existsSync(filename);
        });

        // Tray
        register('appTray', async (_event, title, tooltip, image, menus) => {
            const icon = nativeImage.createFromPath(image);
            let tray = new Tray(icon);

            let template = [];

            let menuData = JSON.parse(menus);

            Object.keys(menuData).forEach((key) => {
                template.push({
                    label: milang.traduzir(key),
                    type: menuData[key].type,
                    click: () => {
                        // Encontra uma janela válida (não destruída)
                        const allWindows = BrowserWindow.getAllWindows();
                        const targetWindow = allWindows.find(w => !w.isDestroyed()) || win;

                        if (!targetWindow || targetWindow.isDestroyed()) {
                            return;
                        }

                        if (menuData[key].page) {
                            if (menuData[key].newwindow) {
                                miphantNewWindow(menuData[key].page);
                            } else {
                                targetWindow.webContents.executeJavaScript(`window.location.assign('${menuData[key].page}');`);
                            }
                        } else if (menuData[key].script) {
                            targetWindow.webContents.executeJavaScript(menuData[key].script);
                        }
                    }
                });
            });

            const contextMenu = Menu.buildFromTemplate(template);
            tray.setContextMenu(contextMenu);
            tray.setToolTip(tooltip);
            tray.setTitle(title);
        });

        // ExportPDF
        register('appExportPDF', async (_event, filename, options) => {
            let pdfOptions = options;
            if (!pdfOptions) {
                pdfOptions = {
                    pageSize: 'A4'
                };
            }

            BrowserWindow.getFocusedWindow().webContents.printToPDF(pdfOptions).then(data => {
                const dirPath = path.dirname(filename);
                if (!fs.existsSync(dirPath)) {
                    fs.mkdirSync(dirPath);
                }

                fs.writeFile(filename, data, (error) => {
                    if (error) throw error
                    console.log(milang.traduzir('PDF successfully saved to %s', filename))
                })
            }).catch(error => {
                console.log(milang.traduzir('Error when trying to generate the PDF in %s', filename), error)
            })
        });
    }
}
