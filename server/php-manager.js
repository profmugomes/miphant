// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

// Site: https://www.profmugomes.com.br

'use strict';

const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const os = require('os');
const { spawn } = require('child_process');

const { exists, findFreePort, waitForPort, waitForSocket } = require('./utils');
const { log: loggerLog } = require('./logger');
const { HOST, PHP_SOCKET_MAX_PATH } = require('./config');
const security = require('./security');

// ============================================================
// ESTADO
// ============================================================

let phpProcess = null;
let phpPort = 0;
let phpEndpoint = null;

// ============================================================
// CAMINHOS
// ============================================================

function getPhpDirectory(resourcesPath) {
    return path.join(resourcesPath, 'php');
}

function getPhpExecutable(resourcesPath) {
    const phpDir = getPhpDirectory(resourcesPath);

    if (process.platform === 'win32') {
        return path.join(phpDir, 'php-cgi.exe');
    }

    return path.join(phpDir, 'php-fpm');
}

function getPhpRuntimeDirectory(userDataPath) {
    const directory = path.join(userDataPath, 'server', 'php');
    fs.mkdirSync(directory, { recursive: true });
    return directory;
}

// Guard carregado via auto_prepend_file em toda requisicao PHP
function getPhpGuardPath(resourcesPath) {
    return path.join(resourcesPath, 'app', 'guard', 'php-guard.php');
}

// ============================================================
// AMBIENTE PHP (BUG FIX: ambiente limpo)
//
// Antes: copiava TODO process.env — expunha tokens, chaves,
// variaveis internas do Electron, etc.
// Agora: copia apenas variaveis necessarias.
// ============================================================

function getPhpEnvironment(resourcesPath) {
    const phpDir = getPhpDirectory(resourcesPath);

    const environment = {
        HOME: process.env.HOME || '',
        PATH: process.env.PATH || '',
        LANG: process.env.LANG || '',
        USER: process.env.USER || process.env.LOGNAME || 'www-data',

        // MiPhant environment — acessíveis via $_ENV no PHP
        MIPHANT_ARGV: process.env.MIPHANT_ARGV || '',
        MIPHANT_USERNAME: process.env.MIPHANT_USERNAME || '',
        MIPHANT_HOMEDIR: process.env.MIPHANT_HOMEDIR || '',
        MIPHANT_PLATFORM: process.env.MIPHANT_PLATFORM || '',
        MIPHANT_LANG: process.env.MIPHANT_LANG || '',
        // Modo dev: paginas de diagnostico (ex.: phpinfo) so abrem aqui
        MIPHANT_DEV: process.env.MIPHANT_DEV || '0',

        // Chave esperada pelo guard PHP (auto_prepend_file). Vem do
        // ambiente do PROCESSO, nunca da requisicao — e o que impede
        // que outro processo assine a propria requisição.
        MIPHANT_GUARD_KEY: security.getPhpKeyHex(),

        PHPRC: path.join(phpDir, 'php.ini'),
        PHP_INI_SCAN_DIR: path.join(phpDir, 'conf.d')
    };

    if (process.platform === 'linux') {
        environment.LD_LIBRARY_PATH =
            phpDir + ':' + (process.env.LD_LIBRARY_PATH || '');
    }

    return environment;
}

// ============================================================
// CONFIG PHP-FPM
// ============================================================

function sanitizeUser(value) {
    return String(value || 'www-data').replace(/[^a-zA-Z0-9_-]/g, '');
}

// Valores de INI com espacos/aspas precisam de quoting
function quoteIniValue(value) {
    if (/[\s"'\\]/.test(value)) {
        return `"${value.replace(/\\/g, '\\\\').replace(/"/g, '\\"')}"`;
    }

    return value;
}

async function createPhpFpmConfig(userDataPath, options) {
    const runtime = getPhpRuntimeDirectory(userDataPath);
    const configFile = path.join(runtime, 'php-fpm.conf');
    const logFile = path.join(runtime, 'php-fpm.log');
    const slowLog = path.join(runtime, 'php-slow.log');

    const useSocket = options.socket === true;
    const listen = options.listen;
    const guardPath = options.guardPath || '';

    const maxChildren = Math.max(2, Math.min(os.cpus().length * 2, 16));

    if (process.platform === 'win32') {
        return null;
    }

    // Só incluir user/group quando FPM roda como root (drop de privilégios)
    // Quando roda como usuário comum, FPM herda o usuário do pai — diretivas são inúteis
    let userDirective = '';
    let groupDirective = '';

    if (process.getuid && process.getuid() === 0) {
        const user = sanitizeUser(process.env.USER || 'www-data');
        const group = sanitizeUser(process.env.GROUP || process.env.USER || 'www-data');
        userDirective = `user = ${user}\n`;
        groupDirective = `group = ${group}\n`;
    }

    // Socket Unix: somente o usuario dono do processo pode conectar
    const listenMode = useSocket ? 'listen.mode = 0600\n' : '';

    // Guard de autenticacao Node -> PHP. php_admin_value impede que o
    // script do usuario sobrescreva auto_prepend_file em runtime.
    const guardDirective = guardPath
        ? `php_admin_value[auto_prepend_file] = ${quoteIniValue(guardPath)}\n`
        : '';

    const config = `
[global]
pid = ${path.join(runtime, 'php-fpm.pid')}
error_log = ${logFile}
daemonize = no

[www]
listen = ${quoteIniValue(listen)}
${listenMode}listen.backlog = 128
pm = dynamic
pm.max_children = ${maxChildren}
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = ${Math.max(2, Math.floor(maxChildren / 2))}
pm.max_requests = 500
clear_env = no
catch_workers_output = yes
request_slowlog_timeout = 5s
slowlog = ${slowLog}
${userDirective}${groupDirective}chdir = /
security.limit_extensions = .php
${guardDirective}php_admin_value[error_log] = ${logFile}
php_admin_flag[log_errors] = on
`;

    await fsp.writeFile(configFile, config.trim() + '\n', 'utf8');
    return configFile;
}

// ============================================================
// INICIAR PROCESSO PHP
// ============================================================

function removeFile(file) {
    try {
        fs.rmSync(file, { force: true });
    } catch {
        // arquivo inexistente ou em uso
    }
}

function launchPhp({ executable, args, resourcesPath, waitForReady }) {
    return new Promise((resolve, reject) => {
        let settled = false;
        let child = null;

        const finish = error => {
            if (settled) return;
            settled = true;

            if (error) {
                try {
                    if (child) child.kill('SIGKILL');
                } catch {
                    // processo ja finalizado
                }

                reject(error);
                return;
            }

            resolve();
        };

        loggerLog('[PHP] Iniciando:', executable);

        child = spawn(executable, args, {
            cwd: getPhpDirectory(resourcesPath),
            env: getPhpEnvironment(resourcesPath),
            windowsHide: true,
            stdio: ['ignore', 'pipe', 'pipe']
        });

        phpProcess = child;

        // Consumir stdout/stderr para evitar bloqueio do pipe
        child.stdout.on('data', data => {
            const text = data.toString();
            if (text.trim()) {
                loggerLog('[PHP]', text.trim());
            }
        });

        child.stderr.on('data', data => {
            const text = data.toString();
            if (text.trim()) {
                console.error('[PHP]', text.trim());
            }
        });

        child.on('error', error => {
            console.error('[PHP] Processo:', error);
            finish(error);
        });

        child.on('exit', (code, signal) => {
            loggerLog(`[PHP] Finalizado code=${code} signal=${signal}`);

            if (phpProcess === child) {
                phpProcess = null;
            }

            finish(
                new Error(
                    `PHP encerrou antes de ficar disponivel (code=${code} signal=${signal})`
                )
            );
        });

        waitForReady().then(
            () => finish(),
            error => finish(error)
        );
    });
}

async function startPhpUnix({ resourcesPath, userDataPath, phpExecutable, socketPath, guardPath }) {
    const configFile = await createPhpFpmConfig(userDataPath, {
        listen: socketPath,
        socket: true,
        guardPath
    });

    // Remove socket orfao de execucao anterior
    removeFile(socketPath);

    await launchPhp({
        executable: phpExecutable,
        args: ['-y', configFile, '-F'],
        resourcesPath,
        waitForReady: () => waitForSocket(socketPath, 10000)
    });

    phpPort = 0;
    phpEndpoint = { type: 'unix', path: socketPath };

    loggerLog(`[PHP] Socket: ${socketPath}`);
}

async function startPhpTcp({ resourcesPath, userDataPath, phpExecutable, guardPath }) {
    phpPort = await findFreePort(HOST);
    loggerLog(`[PHP] Port: ${phpPort}`);

    let args;

    if (process.platform === 'win32') {
        // php-cgi nao suporta socket Unix: TCP + guard via -d
        args = [];

        if (guardPath) {
            args.push('-d', `auto_prepend_file=${guardPath}`);
        }

        args.push('-b', `${HOST}:${phpPort}`);
    } else {
        const configFile = await createPhpFpmConfig(userDataPath, {
            listen: `${HOST}:${phpPort}`,
            socket: false,
            guardPath
        });

        args = ['-y', configFile, '-F'];
    }

    await launchPhp({
        executable: phpExecutable,
        args,
        resourcesPath,
        waitForReady: () => waitForPort(HOST, phpPort, 10000)
    });

    phpEndpoint = { type: 'tcp', host: HOST, port: phpPort };
}

async function startPhp(resourcesPath, userDataPath) {
    const phpExecutable = getPhpExecutable(resourcesPath);

    if (!exists(phpExecutable)) {
        throw new Error(`PHP nao encontrado:\n${phpExecutable}`);
    }

    // Guard sempre aplicado: nao existe opcao de desativar
    const guardPath = getPhpGuardPath(resourcesPath);

    if (!exists(guardPath)) {
        console.error('[PHP] Guard nao encontrado:', guardPath);
    }

    if (process.platform !== 'win32') {
        const socketPath = path.join(
            getPhpRuntimeDirectory(userDataPath),
            'php-fpm.sock'
        );

        if (Buffer.byteLength(socketPath) <= PHP_SOCKET_MAX_PATH) {
            try {
                await startPhpUnix({
                    resourcesPath,
                    userDataPath,
                    phpExecutable,
                    socketPath,
                    guardPath
                });

                loggerLog('[PHP] Disponivel (socket).');
                return phpEndpoint;
            } catch (error) {
                console.error('[PHP] Socket Unix indisponivel, usando TCP:', error.message);

                removeFile(socketPath);
                phpEndpoint = null;
                phpPort = 0;
            }
        }
    }

    await startPhpTcp({
        resourcesPath,
        userDataPath,
        phpExecutable,
        guardPath
    });

    loggerLog('[PHP] Disponivel.');
    return phpEndpoint;
}

// ============================================================
// PARAR PHP (BUG FIX: retorna Promise aguardando exit)
// ============================================================

function stopPhp() {
    const endpoint = phpEndpoint;

    return new Promise(resolve => {
        let resolved = false;

        const cleanup = () => {
            if (endpoint && endpoint.type === 'unix') {
                removeFile(endpoint.path);
            }

            if (phpEndpoint === endpoint) {
                phpEndpoint = null;
            }

            phpPort = 0;
        };

        if (!phpProcess) {
            cleanup();
            resolve();
            return;
        }

        const processToStop = phpProcess;
        phpProcess = null;

        loggerLog('[PHP] Encerrando...');

        const done = () => {
            if (resolved) return;
            resolved = true;

            cleanup();
            loggerLog('[PHP] Finalizado.');
            resolve();
        };

        processToStop.once('exit', done);

        try {
            if (process.platform === 'win32') {
                processToStop.kill();
            } else {
                processToStop.kill('SIGTERM');
            }
        } catch (error) {
            console.error('[PHP]', error);
            done();
        }

        // Timeout de seguranca: 5s
        setTimeout(() => {
            try {
                processToStop.kill('SIGKILL');
            } catch {
                // Processo ja finalizado
            }
            done();
        }, 5000);
    });
}

// ============================================================
// GETTERS
// ============================================================

function getPhpProcess() {
    return phpProcess;
}

function getPhpPort() {
    return phpPort;
}

function getPhpEndpoint() {
    return phpEndpoint;
}

// ============================================================
// EXPORTS
// ============================================================

module.exports = {
    startPhp,
    stopPhp,
    getPhpProcess,
    getPhpPort,
    getPhpEndpoint,
    getPhpDirectory,
    getPhpGuardPath
};
