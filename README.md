# MiPhant

MiPhant is a desktop application runner that allows you to create and run applications using HTML, CSS, JavaScript and PHP.

Built with Node.js, Electron and Chromium. PHP scripts are executed through a built-in HTTPS server that communicates with PHP via the FastCGI protocol. On Linux, PHP-FPM is used as a persistent process manager. On Windows, PHP-CGI is used in FastCGI mode. Each application runs on its own random port, allowing multiple applications to run simultaneously without conflicts.

We use a static version of PHP, compiled with [static-php-cli](https://github.com/crazywhalecc/static-php-cli).

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

## PHP Execution

MiPhant uses a static PHP binary compiled with [static-php-cli](https://github.com/crazywhalecc/static-php-cli). The PHP process communicates with the Node.js HTTPS server via the FastCGI protocol.

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

### FastCGI Protocol

Both platforms use the same FastCGI protocol for communication. The Node.js server sends CGI parameters (request method, headers, query string, script filename, etc.) to the PHP process, which returns HTTP headers and body. The HTTPS server parses the PHP response and forwards it to the Electron renderer.

## MiPhant API

The `miphant` object is available in the renderer process (via preload.js) and provides the following methods:

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
| `$_ENV['MIPHANT_LANG']` | System language (e.g. `'pt'`, `'en'`) |
| `$_ENV['MIPHANT_USERNAME']` | Current system username |
| `$_ENV['MIPHANT_HOMEDIR']` | User home directory |
| `$_ENV['MIPHANT_PLATFORM']` | Platform: `'linux'` or `'win32'` |
| `$_ENV['MIPHANT_ARGV']` | Command-line arguments passed to the app |

## Configuration

The application is configured via `app/config.json`:

```json
{
  "app": {
    "name": "MiPhant",
    "width": 800,
    "height": 600,
    "resizable": true,
    "frame": true,
    "hide": false,
    "icon": "miphant.png",
    "disableAccelerationHardware": true
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
- `name`: Application name
- `width` / `height`: Default window dimensions
- `resizable`: Allow window resizing
- `frame`: Show window frame (title bar)
- `hide`: Start with window hidden
- `icon`: Icon filename (placed in `app/icon/`)
- `disableAccelerationHardware`: Disable GPU hardware acceleration

**server**
- `perm`: Auto-apply execute permission to PHP binary (Linux)
- `router`: Enable PHP built-in router

**dev**
- `menu`: Show the Dev menu (Build, Refresh, Tools)
- `tools`: Auto-open DevTools on window creation

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

MiPhant supports multiple languages. Create a JSON file in `app/langs/` with the language code as filename (e.g., `pt.json`, `en.json`). The system language is detected automatically and the corresponding translation file is loaded. If no matching file is found, `en.json` is used as fallback.

### Translation file format

```json
{
  "Hello World": "Olá Mundo",
  "Unable to find file %s": "Não foi possível encontrar o arquivo %s"
}
```

## Project Structure

```
miphant/
├── main.js                 # Electron main process
├── preload.js              # Preload bridge (miphant API)
├── mifunctions.js          # IPC handlers (dialogs, tray, PDF, etc.)
├── milang.js               # Language detection and translation
├── app/                    # PHP application files
│   ├── config.json         # Application configuration
│   ├── index.php           # Default start page
│   ├── langs/              # Translation files (pt.json, en.json)
│   ├── menus/              # Menu definitions (menu.json)
│   └── *.php               # Example pages
├── server/                 # Node.js server modules
│   ├── http-server.js      # HTTPS server with FastCGI routing
│   ├── php-manager.js      # PHP process management (FPM/CGI)
│   ├── fastcgi.js          # FastCGI protocol implementation
│   ├── certificates.js     # Self-signed certificate generation
│   └── utils.js            # Utility functions
├── php/                    # PHP binaries and configuration
│   ├── php.ini             # PHP configuration file
│   ├── php-fpm             # PHP-FPM binary (Linux)
│   └── php-cgi.exe         # PHP-CGI binary (Windows)
├── staticphp/              # Static PHP build artifacts
│   ├── linux/              # Linux: php-fpm + build metadata
│   └── win32/              # Windows: php-cgi.exe + build metadata
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

## System Requirement

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
