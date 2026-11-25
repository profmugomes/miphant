# MiPhant

[![License](https://img.shields.io/badge/license-MIT-5351FB)](LICENSE.md)

MiPhant is a desktop application runner that lets you build and run PHP applications as native desktop apps on **Linux** and **Windows**. It combines Electron with a built-in HTTPS server that executes PHP through the FastCGI protocol, using platform-native PHP runtimes.

## Features

- **Cross-platform**: Linux (PHP-FPM) and Windows (PHP-CGI)
- **Built-in HTTPS server** with auto-generated self-signed certificates
- **Runtime PSK security**: a new 256-bit key is generated on every launch, so only official MiPhant windows can reach the internal server (see [Security](#security-runtime-psk))
- **FastCGI protocol** for PHP execution with persistent process management
- **MiPhantLibs**: PHP library for config, i18n, file operations, dialogs, routing and more
- **Dark design system** with CSS variables and responsive components
- **System tray**, notifications, dialogs, multi-window support
- **i18n** with automatic language detection and fallback chain
- **Static PHP binary** compiled via [static-php-cli](https://github.com/crazywhalecc/static-php-cli)

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│                       Electron                           │
│  ┌───────────────┐    ┌──────────────────────────────┐   │
│  │  BrowserWindow│    │        Main Process          │   │
│  │  (Renderer)   │◄──►│         (Node.js)            │   │
│  │  preload.js   │IPC │                              │   │
│  └───────────────┘    │  ┌────────────────────────┐  │   │
│                       │  │    HTTPS Server        │  │   │
│                       │  │    (Node.js + TLS)     │  │   │
│                       │  └───────────┬────────────┘  │   │
│                       │              │ FastCGI       │   │
│                       │  ┌───────────▼────────────┐  │   │
│                       │  │ Linux: php-fpm         │  │   │
│                       │  │ Windows: php-cgi.exe   │  │   │
│                       │  │ (static binary)        │  │   │
│                       │  └────────────────────────┘  │   │
│                       └──────────────────────────────┘   │
└──────────────────────────────────────────────────────────┘
```

- **Renderer Process**: Runs the PHP application output (HTML/CSS/JS)
- **Preload Bridge**: Exposes the `miphant` API to the renderer via `contextBridge`
- **Main Process**: Manages windows, menus, dialogs and the server lifecycle
- **HTTPS Server**: Node.js server with auto-generated self-signed certificate, routes requests to PHP via FastCGI
- **PHP Runtime**: Static PHP binary communicating over FastCGI protocol
  - **Linux**: `php-fpm` — persistent process manager with dynamic worker pool
  - **Windows**: `php-cgi.exe` — CGI binary in FastCGI mode

## Server Modules

The `server/` directory contains modular Node.js server components:

| Module | Description |
|---|---|
| `http-server.js` | HTTPS server with TLS, static file serving, PHP protocol routing, runtime PSK gate |
| `security.js` | Per-launch PSK: key generation, HKDF subkeys, HTTP authorization, Node→PHP request MAC, memory wipe |
| `php-manager.js` | PHP process lifecycle: start, stop, FPM pool config (Linux), CGI binding (Windows), guard wiring |
| `php-protocol.js` | PHP protocol implementation for Unix socket (Linux) and TCP (Windows) communication with PHP-FPM/CGI |
| `config.js` | Centralized configuration: HOST, ports, timeouts, limits, security constants |
| `certificates.js` | Auto-generation of self-signed TLS certificates |
| `logger.js` | Centralized logger with verbose mode controlled by `config.dev.tools` |
| `utils.js` | Utility functions: free port finder, MIME types, port/socket wait |

## PHP Execution

### Linux (PHP-FPM)

- **Binary**: `php-fpm`
- **Mode**: Persistent process manager (FPM)
- **Transport**: Unix socket `<userData>/server/php/php-fpm.sock` with `listen.mode = 0600` (falls back to TCP if the path is too long or the bind fails)
- **Config**: Dynamically generated `php-fpm.conf` with pool settings and `php_admin_value[auto_prepend_file]` pointing to the security guard
- **Workers**: Dynamic pool based on CPU cores (2 to 16 children)
- **Advantages**: Persistent processes, better performance for multiple requests, PHP unreachable by other local processes

### Windows (PHP-CGI)

- **Binary**: `php-cgi.exe`
- **Mode**: CGI binary bound to a port in FastCGI mode
- **Transport**: TCP `127.0.0.1:<ephemeral>` (PHP-CGI has no Unix socket support)
- **Config**: No configuration file needed
- **Arguments**: `-d auto_prepend_file=<guard> -b 127.0.0.1:<port>`
- **Advantages**: No external dependencies, works out of the box

### PHP Protocol

Both platforms use the same FastCGI protocol (over a Unix socket on Linux, TCP on Windows). The Node.js server sends CGI parameters (request method, headers, query string, script filename, security MAC, etc.) to the PHP process, which returns HTTP headers and body. The HTTPS server parses the PHP response and forwards it to the Electron renderer.

## Security (Runtime PSK)

MiPhant's internal server listens on loopback and would otherwise be reachable by any local process. To prevent that, **every launch generates a 256-bit pre-shared key (PSK)** that exists only in the main process memory.

### Key lifecycle

1. `server/security.js` calls `crypto.randomBytes(32)` during startup.
2. Two subkeys are derived with HKDF-SHA256:
   - `httpKey` (`miphant/http/v1`) — authorizes window → HTTPS server
   - `phpKey` (`miphant/php/v1`) — authorizes Node → PHP
3. Keys are stored in `Buffer`s so they can be zeroed.
4. On shutdown (`before-quit`, `SIGINT`, `SIGTERM`, fatal error) MiPhant stops HTTPS and PHP, removes the Chromium header-injection listener and calls `security.wipe()` (`Buffer.fill(0)` on every key).

Nothing is written to disk, `process.env`, cookies or `contextBridge`. A key from a previous run is useless.

### Window → HTTPS server

Chromium injects `X-MiPhant-Auth: <httpKey hex>` through `session.webRequest.onBeforeSendHeaders`, before the first `loadURL`. The header is added **only** when:

- the request targets the exact HTTPS port of the MiPhant server, and
- it is the main frame **or** the initiator origin is the MiPhant origin.

That covers the document, `style.css` and images, `fetch`, XHR, `EventSource`/SSE, `HEAD` and preflight. The key never reaches page JavaScript, so XSS cannot steal it.

`server/http-server.js` rejects everything else **before any parsing or I/O**:

- `X-MiPhant-Auth` compared in constant time (SHA-256 digests + `timingSafeEqual`);
- exact `Host` allowlist (`localhost:<port>`, `127.0.0.1:<port>`) — blocks DNS rebinding;
- `Origin`, when present, must be the MiPhant origin;
- `Sec-Fetch-Site: cross-site` or `same-site` is rejected;
- after 20 failures within 10 s, unauthenticated connections are dropped (authenticated requests are never throttled).

Unauthorized requests receive an empty `404` with `Connection: close` — indistinguishable from a missing route.

### Node → PHP

Each request carries `MIPHANT_PHP_TS`, `MIPHANT_PHP_NONCE` and `MIPHANT_PHP_AUTH`, an HMAC-SHA256 over method, script name, request URI, query string, content length, content type, timestamp and nonce.

The expected key is **not** sent in the request: it lives in the PHP process environment as `MIPHANT_GUARD_KEY`, set by Node when spawning PHP. `app/guard/php-guard.php` is loaded via `auto_prepend_file` and, on every request:

- skips only CLI SAPIs (`cli`, `cli-server`);
- validates format, timestamp (±30 s) and HMAC with `hash_equals`;
- removes `MIPHANT_GUARD_KEY`, `MIPHANT_PHP_TS`, `MIPHANT_PHP_NONCE` and `MIPHANT_PHP_AUTH` from `$_SERVER`/`$_ENV` **before** the user script runs, so `phpinfo()` and debug pages never see them;
- answers `403 Forbidden` with no details on any failure (fail closed).

Because the key is never taken from the request, an attacker connecting directly to the PHP port cannot sign their own request.

### Platform differences

| | Linux | Windows |
|---|---|---|
| PHP transport | Unix socket `<userData>/server/php/php-fpm.sock`, `listen.mode = 0600` | TCP `127.0.0.1:<ephemeral>` |
| Direct access from other processes | Blocked by filesystem permissions | Blocked by the HMAC guard |
| Extra protection | Guard (defense in depth) | Guard + loopback-only binding |

If the socket path is too long (Flatpak/Snap) or PHP-FPM cannot bind it, MiPhant falls back to TCP and logs a warning; the guard stays active.

### Other protections

- **Certificate pinning**: `setCertificateVerifyProc` accepts only the SHA-256 fingerprint of the MiPhant certificate for `localhost`. A local proxy with its own CA no longer gets through.
- **No port race**: the HTTPS server binds with `listen(0, '127.0.0.1')` and reads the assigned port from `server.address()`.
- **IPC validation**: every `ipcMain.handle` checks `event.senderFrame.url` against the MiPhant origin.
- **Navigation lock**: `will-navigate` blocks anything outside the internal origin and sends real external links to the system browser, so a foreign page can never inherit the `window.miphant` bridge.
- **Diagnostics restricted**: `/phpinfo` returns `404` unless `dev.tools` is enabled.

### There is no configuration switch

The PSK is **always on**. `app/config.json` has no key, no `enabled` flag and no mode: policy constants live in `server/config.js` and the guard cannot be bypassed. This is intentional — a security feature that a user can turn off is a security feature that will be turned off.

### Threat model

| Protects against | Does **not** protect against |
|---|---|
| Other local apps or browsers hitting `https://localhost:<port>` | A same-user process reading memory (`/proc/<pid>/mem`, `ptrace`, debugger) |
| Port scans followed by requests | Someone with DevTools open (the Network panel shows the header) |
| CSRF / DNS rebinding from web pages | Root/malware, or swap/hibernation writing memory to disk |
| Direct FastCGI access to PHP | Local DoS by exhausting connections |
| Reusing a key after the app closes | |
| Local MITM with a custom CA | |

For stronger isolation keep `kernel.yama.ptrace_scope >= 1` and never ship builds with `--inspect` or `--remote-debugging-port`.

## MiPhantLibs

PHP library included in `app/libs/` for building desktop applications. No Composer required — files are loaded directly via `require_once`.

### Namespace `MiPhantLibs\app`

| Class | Description |
|---|---|
| `config` | Read values from `app/config.json` with nested key access |
| `functions` | PHP helpers that generate JavaScript calls (alerts, confirm, newWindow, tray, etc.) |
| `about` | About page generator with license display |
| `file` | File operations: exists, open, save, create, remove |
| `path` | Cross-platform path builder using OS separator |
| `router` | URL router for multi-page PHP applications |

### Namespace `MiPhantLibs\langs`

| Class | Description |
|---|---|
| `translate` | i18n translation with automatic fallback chain (`pt-br` → `pt.json` → `en.json`) |

### Namespace `MiPhantLibs\system`

| Class | Description |
|---|---|
| `env` | Access MiPhant environment variables (`MIPHANT_LANG`, `MIPHANT_USERNAME`, etc.) |
| `server` | Server helpers: domain, URI, document root |
| `platform` | OS detection: `osLinux()`, `osWindows()` |

## MiPhant API

The `miphant` object is available in the renderer process (via `preload.js`) and provides the following methods:

### Application

| Method | Description |
|---|---|
| `miphant.version(type)` | Get version: `'miphant'`, `'electron'`, `'node'`, `'chromium'` |
| `miphant.close()` | Close the application |

### Dialogs

| Method | Description |
|---|---|
| `miphant.alert(title, msg, type, button)` | Display an alert dialog |
| `miphant.confirm(title, msg, type, ...buttons)` | Display a confirmation dialog |
| `miphant.openFile(multi)` | Open file dialog. `multi: true` for multiple selection |
| `miphant.saveFile()` | Save file dialog |
| `miphant.selectDirectory()` | Select directory dialog |

### Windows

| Method | Description |
|---|---|
| `miphant.newWindow(url, width, height, resizable, frame, hide, menu)` | Open a new application window |
| `miphant.openURL(url)` | Open URL in the external browser |

### System

| Method | Description |
|---|---|
| `miphant.notification(title, text)` | Display a system notification |
| `miphant.tray(title, tooltip, icon, menu)` | Create a system tray icon with context menu |
| `miphant.devTools()` | Open Chromium DevTools |
| `miphant.fileExists(filename)` | Check if a file exists |
| `miphant.exportPDF(filename, options)` | Export the current page to PDF |

### Translation

| Method | Description |
|---|---|
| `miphant.translate(text, ...values)` | Translate a text string using the language files |

### Environment Variables (PHP)

Accessed via `$_ENV` in PHP:

| Variable | Description |
|---|---|
| `$_ENV['MIPHANT_LANG']` | System language (e.g. `'pt-br'`, `'en'`) |
| `$_ENV['MIPHANT_USERNAME']` | Current system username |
| `$_ENV['MIPHANT_HOMEDIR']` | User home directory |
| `$_ENV['MIPHANT_PLATFORM']` | Platform: `'linux'` or `'win32'` |
| `$_ENV['MIPHANT_ARGV']` | Command-line arguments passed to the app |
| `$_ENV['MIPHANT_DEV']` | `'1'` when `dev.tools` is enabled, `'0'` otherwise |

> `MIPHANT_GUARD_KEY` is internal to the PHP process: it is used by `app/guard/php-guard.php` and removed from `$_SERVER`/`$_ENV` before your script runs.

## Configuration

The application is configured via `app/config.json`:

```json
{
  "app": {
    "id": "miphant",
    "name": "MiPhant",
    "version": "7.2.0",
    "width": 800,
    "height": 600,
    "resizable": true,
    "frame": true,
    "hide": false,
    "icon": "miphant.png",
    "disableAccelerationHardware": true,
    "author": {
      "name": "Murilo Gomes",
      "email": "profmugomes@gmail.com",
      "url": "https://www.profmugomes.com.br"
    },
    "homepage": "https://github.com/profmugomes/miphant/",
    "license": "MIT",
    "copyright": "Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>"
  },
  "server": {
    "router": false
  },
  "dev": {
    "menu": true,
    "tools": false
  }
}
```

### Options

**app**
- `id`: Application identifier
- `name`: Application name
- `version`: Application version
- `width` / `height`: Default window dimensions
- `resizable`: Allow window resizing
- `frame`: Show window frame (title bar)
- `hide`: Start with window hidden
- `icon`: Icon filename (placed in `app/icon/`)
- `disableAccelerationHardware`: Disable GPU hardware acceleration
- `author`: Author info (name, email, url)
- `homepage`: Project homepage URL
- `license`: License type
- `copyright`: Copyright notice

**server**
- `router`: Enable PHP built-in router

> Security is **not** configurable. The runtime PSK, certificate pinning, IPC validation and the PHP guard are always active; see [Security](#security-runtime-psk).

**dev**
- `menu`: Show the Dev menu (Build, Refresh, Tools)
- `tools`: Auto-open DevTools on window creation

## Design System

MiPhant includes a dark theme design system in `app/style.css` with:

- CSS custom properties for colors, spacing, and typography
- Card, button, table, badge, and form components
- Responsive grid layout
- Consistent visual language across all demo pages

To use in your pages:

```html
<link rel="stylesheet" href="/style.css">
```

### Available classes

| Class | Description |
|---|---|
| `.card` | Content container with dark background |
| `.btn` | Button base class |
| `.btn-primary` | Primary action button |
| `.btn-outline` | Outlined button |
| `.badge` | Small label |
| `.badge-info` | Info-colored badge |
| `.text-muted` | Muted text color |
| `.collapsible` | Collapsible button (for license sections) |

## Menus

Menus are defined as JSON files in `app/menus/`. The main menu is `menu.json`. Each PHP page can have a custom menu by creating a JSON file with the same name (e.g., `message.json` for `message.php`).

### Menu JSON format

```json
{
  "Menu Label": {
    "Item Label": {
      "key": "Ctrl+O",
      "page": "/page.php",
      "newwindow": true,
      "width": 600,
      "height": 400
    },
    "separator1": {},
    "External Link": {
      "url": "https://seu-dominio"
    },
    "Run Script": {
      "script": "console.log('hello')"
    }
  }
}
```

### Options

- `key`: Keyboard shortcut (Electron accelerator format)
- `page`: PHP page path to navigate to
- `newwindow`: Open in a new window (default: `false`, navigates in current window)
- `width` / `height`: Window dimensions for new windows
- `resizable` / `frame` / `hide`: Window options for new windows
- `url`: Open an external URL in the system browser
- `script`: Execute JavaScript in the current window

## Languages

MiPhant supports multiple languages with automatic fallback chain. Create JSON files in `app/langs/` with the language code as filename.

### Fallback chain

When a language is detected (e.g. `pt-br`), the system tries:

1. `pt-br.json` — exact match
2. `pt.json` — base language
3. `en.json` — English fallback

### Translation file format

```json
{
  "Continue": "Continuar",
  "Cancel": "Cancelar",
  "Unable to find file %s": "Não foi possível encontrar o arquivo %s",
  "Server has been started successfully.": "O servidor foi iniciado com sucesso."
}
```

## System Requirements

- Architecture: x64

### Linux

- Debian 12 or higher
- Ubuntu 22.04 or higher

### Windows

- Windows 10 or higher
- Visual C++ Redistributable 14.42

### PHP Extensions

The static PHP binary includes the following extensions:

bcmath, calendar, ctype, curl, dom, exif, fileinfo, filter, gd, iconv, mbstring, mbregex, mysqli, mysqlnd, opcache, openssl, pcntl, pdo, pdo_mysql, pdo_sqlite, phar, posix, readline, redis, session, simplexml, sockets, sodium, sqlite3, tokenizer, xml, xmlreader, xmlwriter, zip, zlib

## Support

- GitHub: https://github.com/sponsors/profmugomes/
- LivePix: https://livepix.gg/profmugomes

## License

Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>

Licensed under the [MIT](https://github.com/profmugomes/miphant/blob/main/LICENSE) license.

All contributions to the MiPhant are subject to this license.