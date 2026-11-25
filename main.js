// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

// Site: https://www.profmugomes.com.br

const { app, BrowserWindow, Menu, MenuItem, ipcMain, session, shell } = require('electron');
const path = require('path');
const fs = require('fs');
const crypto = require('crypto');
const sOS = require('os');
const { spawn } = require('child_process');
const { createSelfSignedCertificate } = require('./server/certificates');
const { startPhp, stopPhp } = require('./server/php-manager');
const {
    startHttpsServer,
    stopHttps,
    setPublicRoot,
    getHttpsPort,
    getServerOrigin,
    setRouter
} = require('./server/http-server');
const { setDebugEnabled, log: loggerLog } = require('./server/logger');
const { HOST } = require('./server/config');
const security = require('./server/security');

const sPlatform = sOS.platform().toLowerCase();
const miphantPath = app.getAppPath().replace('app.asar', '');

// Argumentos
let sArgs = process.argv;
let sArgv = (sArgs[1] == '.') ? sArgs.slice(2).toString() : sArgs.slice(1).toString();

const milangs = require(path.join(app.getAppPath(), '/milang.js'));
const milang = new milangs(sPlatform, miphantPath);

process.on('uncaughtException', (error) => {
    console.error(milang.traduzir('Unhandled exception:'), error);
});

if (!fs.existsSync(path.join(miphantPath, '/app/config.json'))) {
    console.error(milang.traduzir('Unable to find file %s', '"config.json"'));

    app.quit();
    return false;
}

const config = JSON.parse(fs.readFileSync(path.join(miphantPath, '/app/config.json'), 'utf-8'));

if (config.app.desativarAceleracaoHardware) {
    app.disableHardwareAcceleration();
}

if (config.app.name) {
    app.setName(config.app.name);
}

let miphantIcon = path.join(miphantPath, '/app/icon/', config.app.icon);

if (!fs.existsSync(miphantIcon)) {
    miphantIcon = path.join(app.getAppPath(), '/icon/miphant.png');
}

let sStartApp = true;
let sServerName;

function createMenu(sWin, sFileMenu) {
    if (fs.existsSync(path.join(miphantPath, '/app/menus/', `${sFileMenu}.json`))) {
        fs.readFile(path.join(miphantPath, '/app/menus/', `${sFileMenu}.json`), (err, data) => {
            if (err) {
                console.error(milang.traduzir('Error reading JSON file'), err);
                return;
            }

            const menuData = JSON.parse(data);
            const mainMenu = Menu.buildFromTemplate(getMenuTemplate(sWin, menuData));
            sWin.setMenu(mainMenu);
        });
    } else {
        const mainMenu = Menu.buildFromTemplate(getMenuTemplate(sWin, ''));
        sWin.setMenu(mainMenu);
    }
}

const createWindow = () => {
    miphantNewWindow('', config.app.width, config.app.height, config.app.resizable, config.app.frame, config.app.hide);
}

let shuttingDown = false;

// Caminhos
function getPublicRoot() {
    return path.join(miphantPath, 'app', 'public');
}

function getResourcesRoot() {
    return miphantPath;
}

function getCertificateDirectory() {
    const directory = path.join(
        app.getPath('userData'),
        'server',
        'certificate'
    );
    require('fs').mkdirSync(directory, { recursive: true });
    return directory;
}

// ============================================================
// PINNING DO CERTIFICADO
//
// Antes: qualquer certificado era aceito para localhost, o que
// permitia MITM local com CA propria. Agora somente o nosso
// certificado (fingerprint SHA-256) e aceito.
// ============================================================

let trustedFingerprint = null;

function normalizeFingerprint(value) {
    return String(value || '').replace(/:/g, '').toLowerCase();
}

function fingerprintOfCertificate(certificate) {
    if (!certificate || !certificate.data) {
        return null;
    }

    try {
        return normalizeFingerprint(
            new crypto.X509Certificate(certificate.data).fingerprint256
        );
    } catch {
        return null;
    }
}

function configureElectronCertificateTrust(certificate) {
    try {
        trustedFingerprint = normalizeFingerprint(
            new crypto.X509Certificate(
                fs.readFileSync(certificate.cert)
            ).fingerprint256
        );
    } catch (error) {
        console.error('[HTTPS] Falha ao calcular fingerprint:', error);
        trustedFingerprint = null;
    }

    session.defaultSession.setCertificateVerifyProc((request, callback) => {
        const hostname = request.hostname;

        if (
            hostname !== 'localhost' &&
            hostname !== HOST
        ) {
            callback(request.errorCode);
            return;
        }

        const presented = fingerprintOfCertificate(request.certificate);

        if (!trustedFingerprint || !presented || presented !== trustedFingerprint) {
            loggerLog('[HTTPS] Certificado recusado (fingerprint divergente).');
            callback(-2);
            return;
        }

        callback(0);
    });
}

// ============================================================
// INJECAO DO HEADER DE AUTENTICACAO NO RENDERER
//
// Feita pelo Chromium (webRequest), nunca pelo JS da pagina.
// Injeta apenas na porta exata do servidor e apenas quando a
// requisicao parte de uma pagina do MiPhant.
// ============================================================

let removeRendererAuth = null;

function installRendererAuth(electronSession, port) {
    const auth = security.getHttpAuthHeader();

    if (!auth) {
        throw new Error('Contexto de seguranca nao inicializado.');
    }

    const expectedPort = String(port);
    const allowedOrigins = new Set([
        `https://localhost:${expectedPort}`,
        `https://127.0.0.1:${expectedPort}`
    ]);

    const listener = (details, callback) => {
        let parsed;

        try {
            parsed = new URL(details.url);
        } catch {
            callback({});
            return;
        }

        const isTarget =
            parsed.protocol === 'https:' &&
            parsed.port === expectedPort &&
            ['localhost', '127.0.0.1'].includes(parsed.hostname);

        const isMainFrame = details.resourceType === 'mainFrame';
        const fromOurPage = allowedOrigins.has(details.initiatorOrigin);

        // Fora do nosso servidor ou originada por outra pagina: nao injeta
        if (!isTarget || (!isMainFrame && !fromOurPage)) {
            callback({});
            return;
        }

        const requestHeaders = { ...details.requestHeaders };

        for (const name of Object.keys(requestHeaders)) {
            if (name.toLowerCase() === auth.name) {
                delete requestHeaders[name];
            }
        }

        requestHeaders[auth.name] = auth.value;
        callback({ requestHeaders });
    };

    electronSession.webRequest.onBeforeSendHeaders(
        { urls: ['https://localhost/*', 'https://127.0.0.1/*'] },
        listener
    );

    removeRendererAuth = () => {
        electronSession.webRequest.onBeforeSendHeaders(null);
        removeRendererAuth = null;
    };
}

// ============================================================
// ORIGEM OFICIAL (IPC e navegacao)
// ============================================================

function isInternalUrl(targetUrl) {
    const origin = getServerOrigin();

    if (!origin) {
        return false;
    }

    try {
        return new URL(targetUrl).origin === origin;
    } catch {
        return false;
    }
}

function isOfficialSender(event) {
    const origin = getServerOrigin();

    if (!origin || !event || !event.senderFrame) {
        return false;
    }

    const frameUrl = event.senderFrame.url;

    return typeof frameUrl === 'string' && frameUrl.startsWith(origin + '/');
}

async function shutdown() {
    if (shuttingDown) return;

    shuttingDown = true;
    loggerLog('[SERVER] Encerrando...');

    await stopHttps();
    await stopPhp();

    // Remove a injecao do header e apaga a chave da memoria
    if (removeRendererAuth) {
        removeRendererAuth();
    }

    security.wipe();

    loggerLog('[SERVER] Finalizado.');
}

// Inicia o MiPhantServer
async function startMiPhantServer(win) {
    let sMiPhantServer;

    try {
        // 0. Environment — DEVE estar antes de startPhp() para que
        //    getPhpEnvironment() leia as variáveis de process.env
        process.env.MIPHANT_ARGV = sArgv;
        process.env.MIPHANT_USERNAME = sOS.userInfo().username;
        process.env.MIPHANT_HOMEDIR = sOS.userInfo().homedir;
        process.env.MIPHANT_PLATFORM = sPlatform;
        process.env.MIPHANT_DEV = (config.dev && config.dev.tools) ? '1' : '0';
        // MIPHANT_LANG já foi definida em milang.js (antes de main.js executar)

        // Debug mode
        setDebugEnabled(config.dev.tools || process.env.MIPHANT_DEBUG === '1');

        // 1. Contexto de seguranca (PSK) — antes do PHP e do servidor.
        //    Protecao obrigatoria: nao ha chave em config.json.
        security.create();
        loggerLog('[SECURITY] PSK fingerprint:', security.getFingerprint());

        // 2. Certificado
        const certDir = getCertificateDirectory();
        const certificate = createSelfSignedCertificate(certDir);

        // 3. Trust + pinning do certificado no Electron
        configureElectronCertificateTrust(certificate);

        // 4. Public root
        setPublicRoot(getPublicRoot());

        // 4.1 Router (URL Amigavel)
        if (config.server && config.server.router) {
            setRouter(true);
        }

        // 5. PHP (guard auto_prepend obrigatorio)
        await startPhp(getResourcesRoot(), app.getPath('userData'));

        // 6. Servidor HTTPS
        await startHttpsServer(certificate);

        // 7. Injecao do header de autenticacao — antes do primeiro loadURL
        installRendererAuth(session.defaultSession, getHttpsPort());

        // 8. Janela
        sServerName = `https://localhost:${getHttpsPort()}/`;

        loggerLog('[ELECTRON]', sServerName);

        win.loadURL(sServerName);
    } catch (error) {
        console.error('[SERVER] Erro fatal:', error);
        await shutdown();
        app.quit();
    }
}

// Nova Janela
async function miphantNewWindow(url, width, height, resizable, frame, hide, menu) {
    let sWidth = (width) ? width : config.app.width;
    let sHeight = (height) ? height : config.app.height;
    let sResizable = (resizable == true || resizable == false) ? resizable : config.app.resizable;
    let sFrame = (frame == true || frame == false) ? frame : config.app.frame;
    let sHide = (hide == true || hide == false) ? hide : config.app.hide;

    const sNewWindow = new BrowserWindow({
        width: sWidth,
        height: sHeight,
        resizable: sResizable,
        frame: sFrame,
        icon: miphantIcon,
        webPreferences: {
            preload: path.join(app.getAppPath(), '/preload.js'),
        }
    });

    if (sHide) {
        sNewWindow.hide();
    }

    sNewWindow.setMenu(null);

    if (sStartApp) {
        await startMiPhantServer(sNewWindow);

        const mifunctions = require(path.join(app.getAppPath(), '/mifunctions.js'));
        mifunctions.mifunctions(sNewWindow, milang, miphantNewWindow, isOfficialSender);

        ipcMain.handle('appSair', async (event) => {
            if (!isOfficialSender(event)) {
                throw new Error('Unauthorized IPC request');
            }

            app.quit();
        });

        createMenu(sNewWindow, 'menu');

        sStartApp = false;
    }

    const cleanUrl = url.replace(sServerName, '');
    if (cleanUrl) {
        sNewWindow.loadURL(`${sServerName}${cleanUrl}`);

        const menuFile = cleanUrl.replace(/(\.php|\/)$/, '.json');
        if (fs.existsSync(path.join(miphantPath, '/app/menus/', menuFile))) {
            createMenu(sNewWindow, menuFile.replace('.json', ''));
        }
    }

    if (config.dev.tools) {
        sNewWindow.webContents.openDevTools();
    }

    // Diagnostico: falha de carregamento nunca mais silenciosa
    sNewWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription, validatedURL, isMainFrame) => {
        if (isMainFrame) {
            console.error('[ELECTRON] Falha ao carregar', validatedURL, errorCode, errorDescription);
        }
    });

    sNewWindow.webContents.on('did-navigate', (event, url, httpResponseCode) => {
        loggerLog('[ELECTRON] Navegou', url, 'HTTP', httpResponseCode);
    });

    createMenuContext(sNewWindow);

    // Navegacao interna (menus, links relativos) e permitida; qualquer
    // destino externo abre no navegador do sistema. Sem isso, uma pagina
    // externa herdaria a bridge `window.miphant` desta janela.
    sNewWindow.webContents.on('will-navigate', (event, targetUrl) => {
        if (isInternalUrl(targetUrl)) {
            return;
        }

        event.preventDefault();

        if (/^https?:/i.test(targetUrl)) {
            shell.openExternal(targetUrl);
        }
    });

    sNewWindow.webContents.setWindowOpenHandler(({ url }) => {
        if (url !== '') {
            // URL interna (localhost) — abre nova janela preservando sessão
            if (isInternalUrl(url)) {
                miphantNewWindow(url);
            } else {
                // URL externa — abre no navegador do sistema
                shell.openExternal(url);
            }

            return { action: 'deny' }
        }

        return { action: 'allow' }
    });
}

// Template de Menu
function getMenuTemplate(win, menuData) {
    let template = [];

    if (config.dev.menu) {
        let devMenu = {
            label: milang.traduzir('Dev'),
            submenu: [
                {
                    label: milang.traduzir('Refresh'),
                    accelerator: 'F5',
                    click: () => {
                        win.reload();
                    }
                },
                {
                    type: 'separator'
                },
                {
                    label: milang.traduzir('Tools'),
                    accelerator: 'F12',
                    click: () => {
                        win.openDevTools();
                    }
                }
            ]
        }

        template.push(devMenu);
    }

    // Loop sobre as chaves do objeto JSON
    Object.keys(menuData).forEach((sKey) => {
        let submenu = [];

        // Loop sobre os itens do submenu
        Object.keys(menuData[sKey]).forEach((sSubMenuKey) => {
            let menuItem = {};

            if (sSubMenuKey.indexOf('separator') == 0) {
                menuItem = { type: 'separator' };
            } else {
                menuItem = {
                    label: milang.traduzir(sSubMenuKey),
                    accelerator: menuData[sKey][sSubMenuKey].key,
                    click: () => {
                        // Verifica se é uma página ou URL
                        if (menuData[sKey][sSubMenuKey].page) {
                            if (menuData[sKey][sSubMenuKey].newwindow) {
                                miphantNewWindow(menuData[sKey][sSubMenuKey].page, menuData[sKey][sSubMenuKey].width, menuData[sKey][sSubMenuKey].height, menuData[sKey][sSubMenuKey].resizable, menuData[sKey][sSubMenuKey].frame, menuData[sKey][sSubMenuKey].hide, menuData[sKey][sSubMenuKey].menu)
                            } else {
                                win.loadURL(sServerName + menuData[sKey][sSubMenuKey].page);
                            }
                        } else if (menuData[sKey][sSubMenuKey].url) {
                            require('electron').shell.openExternal(menuData[sKey][sSubMenuKey].url);
                        } else if (menuData[sKey][sSubMenuKey].script) {
                            win.webContents.executeJavaScript(menuData[sKey][sSubMenuKey].script);
                        }
                    }
                };
            }

            submenu.push(menuItem);
        });

        // Adiciona o submenu ao item do menu principal
        template.push({ label: milang.traduzir(sKey), submenu });
    });

    return template;
}

function createMenuContext(win) {
    const contextMenu = new Menu();
    contextMenu.append(new MenuItem({
        label: milang.traduzir('Cut'),
        role: 'cut'
    }));
    contextMenu.append(new MenuItem({
        label: milang.traduzir('Copy'),
        role: 'copy'
    }));
    contextMenu.append(new MenuItem({
        label: milang.traduzir('Paste'),
        role: 'paste'
    }));
    contextMenu.append(new MenuItem({
        type: "separator"
    }));
    contextMenu.append(new MenuItem({
        label: milang.traduzir('Select All'),
        role: 'selectall'
    }));

    win.webContents.on('context-menu', (event, params) => {
        if (params.formControlType == 'input-text' || params.formControlType == 'text-area') {
            contextMenu.popup({
                window: win,
                x: params.x,
                y: params.y
            });
        }
    });
}

app.whenReady().then(() => {
    createWindow()

    // Enquanto os aplicativos do Linux são encerrados quando não há janelas abertas, os aplicativos do macOS geralmente continuam em execução mesmo sem nenhuma janela aberta, e ativar o aplicativo quando não há janelas disponíveis deve abrir um novo.
    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) createWindow()
    });
});

// Para sair do aplicativo no Linux
// Se for MACOS não roda esse comando
app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('before-quit', event => {
    if (shuttingDown) return;

    event.preventDefault();

    // Timeout de segurança: se o shutdown travar, força saída após 10s
    const forceQuitTimeout = setTimeout(() => {
        console.warn('[SERVER] Timeout no shutdown, forçando saída.');
        app.exit(1);
    }, 10000);

    shutdown().then(() => {
        clearTimeout(forceQuitTimeout);
        app.exit(0);
    });
});


process.on('SIGINT', () => {
    shutdown().then(() => app.quit());
});

process.on('SIGTERM', () => {
    shutdown().then(() => app.quit());
});


process.on('uncaughtException', error => {
    console.error('[UNCAUGHT EXCEPTION]', error);
});

process.on('unhandledRejection', error => {
    console.error('[UNHANDLED REJECTION]', error);
});