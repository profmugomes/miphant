// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

'use strict';

const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const os = require('os');
const { spawn } = require('child_process');

const { exists, findFreePort, waitForPort } = require('./utils');
const { log: loggerLog } = require('./logger');
const { HOST } = require('./config');

// ============================================================
// ESTADO
// ============================================================

let phpProcess = null;
let phpPort = 0;

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

async function createPhpFpmConfig(resourcesPath, userDataPath, port) {
    const runtime = getPhpRuntimeDirectory(userDataPath);
    const configFile = path.join(runtime, 'php-fpm.conf');
    const logFile = path.join(runtime, 'php-fpm.log');
    const slowLog = path.join(runtime, 'php-slow.log');

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

    const config = `
[global]
pid = ${path.join(runtime, 'php-fpm.pid')}
error_log = ${logFile}
daemonize = no

[www]
listen = ${HOST}:${port}
listen.backlog = 128
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
php_admin_value[error_log] = ${logFile}
php_admin_flag[log_errors] = on
`;

    await fsp.writeFile(configFile, config.trim() + '\n', 'utf8');
    return configFile;
}

// ============================================================
// INICIAR PHP
// ============================================================

async function startPhp(resourcesPath, userDataPath) {
    const phpExecutable = getPhpExecutable(resourcesPath);

    if (!exists(phpExecutable)) {
        throw new Error(`PHP nao encontrado:\n${phpExecutable}`);
    }

    phpPort = await findFreePort(HOST);
    loggerLog(`[PHP] Port: ${phpPort}`);

    const environment = getPhpEnvironment(resourcesPath);

    let args;

    if (process.platform === 'win32') {
        args = ['-b', `${HOST}:${phpPort}`];
    } else {
        const config = await createPhpFpmConfig(
            resourcesPath,
            userDataPath,
            phpPort
        );
        args = ['-y', config, '-F'];
    }

    loggerLog('[PHP] Iniciando:', phpExecutable);

    phpProcess = spawn(phpExecutable, args, {
        cwd: getPhpDirectory(resourcesPath),
        env: environment,
        windowsHide: true,
        stdio: ['ignore', 'pipe', 'pipe']
    });

    // Consumir stdout/stderr para evitar bloqueio do pipe
    phpProcess.stdout.on('data', data => {
        const text = data.toString();
        if (text.trim()) {
            loggerLog('[PHP]', text.trim());
        }
    });

    phpProcess.stderr.on('data', data => {
        const text = data.toString();
        if (text.trim()) {
            console.error('[PHP]', text.trim());
        }
    });

    phpProcess.on('error', error => {
        console.error('[PHP] Processo:', error);
    });

    phpProcess.on('exit', (code, signal) => {
        loggerLog(`[PHP] Finalizado code=${code} signal=${signal}`);
        phpProcess = null;
    });

    await waitForPort(HOST, phpPort, 10000);
    loggerLog('[PHP] Disponivel.');

    return phpPort;
}

// ============================================================
// PARAR PHP (BUG FIX: retorna Promise aguardando exit)
// ============================================================

function stopPhp() {
    return new Promise(resolve => {
        if (!phpProcess) {
            resolve();
            return;
        }

        const processToStop = phpProcess;
        phpProcess = null;

        loggerLog('[PHP] Encerrando...');

        const done = () => {
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
            resolve();
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

// ============================================================
// EXPORTS
// ============================================================

module.exports = {
    startPhp,
    stopPhp,
    getPhpProcess,
    getPhpPort,
    getPhpDirectory
};
