# MiPhant

[![License](https://img.shields.io/badge/license-PolyForm%20Perimeter%201.0.1-5351FB)](LICENSE.md)

MiPhant is a desktop application runner that lets you build and run PHP applications as native desktop apps on **Linux** and **Windows**. It combines Electron with a built-in HTTPS server that executes PHP through the FastCGI protocol, using platform-native PHP runtimes.

## Features

- **Cross-platform**: Linux (PHP-FPM) and Windows (PHP-CGI)
- **Built-in HTTPS server** with auto-generated self-signed certificates
- **FastCGI protocol** for PHP execution with persistent process management
- **MiPhantLibs**: PHP library for config, i18n, file operations, dialogs, routing and more
- **Dark design system** with CSS variables and responsive components
- **System tray**, notifications, dialogs, multi-window support
- **i18n** with automatic language detection and fallback chain
- **Static PHP binary** compiled via [static-php-cli](https://github.com/crazywhalecc/static-php-cli)
- **17+ demo pages** showcasing all features

## Architecture

```
┌──────────────────────────────────────────────────────────┐
│                       Electron                           │
│  ┌───────────────┐    ┌──────────────────────────────┐   │
│  │  BrowserWindow │    │        Main Process           │   │
│  │  (Renderer)    │◄──►│         (Node.js)             │   │
│  │  preload.js    │IPC │                               │   │
│  └───────────────┘    │  ┌────────────────────────┐  │   │
│                        │  │    HTTPS Server         │  │   │
│                        │  │    (Node.js + TLS)      │  │   │
│                        │  └───────────┬────────────┘  │   │
│                        │              │ FastCGI        │   │
│                        │  ┌───────────▼────────────┐  │   │
│                        │  │ Linux: php-fpm          │  │   │
│                        │  │ Windows: php-cgi.exe    │  │   │
│                        │  │ (static binary)         │  │   │
│                        │  └────────────────────────┘  │   │
│                        └──────────────────────────────┘   │
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
| `http-server.js` | HTTPS server with TLS, static file serving, PHP protocol routing |
| `php-manager.js` | PHP process lifecycle: start, stop, FPM pool config (Linux), CGI binding (Windows) |
| `php-protocol.js` | PHP protocol implementation for TCP communication with PHP-FPM/CGI |
| `config.js` | Centralized configuration: HOST, ports, timeouts, limits |
| `certificates.js` | Auto-generation of self-signed TLS certificates |
| `logger.js` | Centralized logger with verbose mode controlled by `config.dev.tools` |
| `utils.js` | Utility functions: free port finder, MIME types, port wait |

## PHP Execution

### Linux (PHP-FPM)

- **Binary**: `php-fpm`
- **Mode**: Persistent process manager (FPM)
- **Config**: Dynamically generated `php-fpm.conf` with pool settings
- **Workers**: Dynamic pool based on CPU cores (2 to 16 children)
- **Advantages**: Persistent processes, better performance for multiple requests

### Windows (PHP-CGI)

- **Binary**: `php-cgi.exe`
- **Mode**: CGI binary bound to a port in FastCGI mode
- **Config**: No configuration file needed
- **Arguments**: `-b 127.0.0.1:<port>`
- **Advantages**: No external dependencies, works out of the box

### PHP Protocol

Both platforms use the same TCP protocol for communication. The Node.js server sends CGI parameters (request method, headers, query string, script filename, etc.) to the PHP process, which returns HTTP headers and body. The HTTPS server parses the PHP response and forwards it to the Electron renderer.

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

### Usage Example

```php
require_once __DIR__ . '/libs/app/config.php';
require_once __DIR__ . '/libs/app/functions.php';
require_once __DIR__ . '/libs/langs/translate.php';
require_once __DIR__ . '/libs/system/server.php';
require_once __DIR__ . '/libs/system/env.php';

use MiPhantLibs\app\config;
use MiPhantLibs\app\functions;
use MiPhantLibs\langs\translate;

$cfg = new config();
$func = new functions();
$translate = new translate();

// Read config
$width = $cfg->get('app', 'width');

// Show translated alert
$func->alert('Info', $translate->get('Server has been started successfully.'), 'info');

// Open new window
$func->noTag()->newWindow('page.php', 800, 600);
```

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

## Configuration

The application is configured via `app/config.json`:

```json
{
  "app": {
    "id": "miphant",
    "name": "MiPhant",
    "version": "6.0.0",
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
    "license": "SEE LICENSE IN LICENSE.md",
    "copyright": "Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>"
  },
  "server": {
    "perm": false,
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
- `perm`: Auto-apply execute permission to PHP binary (Linux)
- `router`: Enable PHP built-in router

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
<link rel="stylesheet" href="style.css">
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
      "url": "https://example.com"
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

## Demo Pages

MiPhant includes 17+ demo pages showcasing all features:

| Page | Description |
|---|---|
| `index.php` | Home page with navigation to all demos |
| `about.php` | System information and license |
| `env.php` | Environment variables display |
| `args.php` | Command-line arguments |
| `message.php` | Alert and confirm dialogs |
| `notification.php` | System notifications |
| `openfile.php` | Open file dialog |
| `openfiles.php` | Multiple file selection |
| `savefile.php` | Save file dialog |
| `selectdirectory.php` | Directory selection |
| `cookies.php` | Cookie management |
| `session.php` | Session management |
| `sqlite.php` | SQLite database operations |
| `formget.php` | GET form handling |
| `formpost.php` | POST form handling |
| `translate.php` | i18n translation demo |
| `timezone.php` | Timezone configuration |
| `pdf.php` | PDF export |
| `extramenu.php` | Custom menus per window |
| `phpinfo.php` | PHP configuration info |
| `libs.php` | MiPhantLibs API documentation |
| `preload-doc.php` | Preload API documentation |

## Project Structure

```
miphant/
├── main.js                 # Electron main process
├── preload.js              # Preload bridge (miphant API)
├── mifunctions.js          # IPC handlers (dialogs, tray, PDF, etc.)
├── milang.js               # Language detection and translation
├── server/                 # Node.js server modules
│   ├── http-server.js      # HTTPS server with PHP protocol routing
│   ├── php-manager.js      # PHP process management (FPM/CGI)
│   ├── php-protocol.js     # PHP protocol implementation (TCP)
│   ├── config.js           # Centralized configuration
│   ├── certificates.js     # Self-signed certificate generation
│   ├── logger.js           # Centralized logger
│   └── utils.js            # Utility functions
├── app/                    # PHP application files
│   ├── config.json         # Application configuration
│   ├── style.css           # Dark theme design system
│   ├── index.php           # Default start page
│   ├── langs/              # Translation files (pt.json, en.json)
│   ├── menus/              # Menu definitions (menu.json)
│   ├── libs/               # MiPhantLibs PHP library
│   │   ├── app/            # App classes (config, functions, file, path, about, router)
│   │   ├── langs/          # Translation class
│   │   ├── system/         # System classes (env, server, platform)
│   │   └── security/       # Security utilities
│   └── *.php               # Demo pages
├── php/                    # PHP binaries and configuration
│   ├── php.ini             # PHP configuration file
│   ├── php-fpm             # PHP-FPM binary (Linux)
│   └── php-cgi.exe         # PHP-CGI binary (Windows)
├── staticphp/              # Static PHP build artifacts
│   ├── linux/              # Linux: php-fpm + build metadata
│   └── win32/              # Windows: php-cgi.exe + build metadata
├── tests/                  # Test suite
│   └── test-libs.php       # MiPhantLibs tests (101 tests)
├── LICENSE.md              # PolyForm Perimeter 1.0.1
└── electron-builder.yml    # Electron Builder configuration
```

## Building

### Prerequisites

- Node.js 18+
- npm

### Install dependencies

```bash
npm install
```

### Run in development

```bash
npm start
```

### Configure Chrome sandbox (Linux)

```bash
./config-dev.sh
```

### Build for Linux

```bash
npm run dist-linux
```

### Build for Windows

```bash
npm run dist-win
```

### Build for both platforms

```bash
./compile.sh
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

Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All rights reserved.

This project is licensed under the [PolyForm Perimeter License 1.0.1](https://polyformproject.org/licenses/perimeter/1.0.1).

### Summary

You may:

- ✔ Use the software for any purpose (personal, educational, commercial).
- ✔ Inspect and study the source code.
- ✔ Modify the software and create derivative works.
- ✔ Distribute copies of the software (with or without modifications).

You may not:

- ✖ Provide a product that competes with the software.

### What counts as competition?

A product competes with MiPhant if it is offered as a substitute for its functionality or value, regardless of:

- How it is designed or deployed.
- Whether it is provided via an interface (service, library, or plugin).
- Whether it is ported to another platform or programming language.
- Whether it is provided for free.

### Permitted use examples

- Using MiPhant to build desktop applications for yourself.
- Using MiPhant in educational or research environments.
- Modifying MiPhant to suit your needs.
- Distributing MiPhant to third parties (without competitive intent).

### NOT permitted use examples

- Creating a product that functions as an alternative to MiPhant.
- Offering a service that replaces MiPhant's functionality.
- Selling a modified version of MiPhant as a competing product.

See the full license terms in [LICENSE.md](LICENSE.md).

This summary is provided for convenience only.
