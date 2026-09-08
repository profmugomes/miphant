# SKILLS.md - Habilidades para Desenvolvimento MiPhant

## 1. Arquitetura do Sistema

### Visão Geral

MiPhant é uma aplicação desktop que executa PHP como app desktop. O fluxo é:

```
Electron (Renderer) → Node.js (Main) → HTTPS Server → FastCGI → PHP
```

### Camadas

| Camada | Tecnologia | Responsabilidade |
|---|---|---|
| **Renderer** | HTML/CSS/JS + PHP | Interface do usuário, executada no Chromium |
| **Main Process** | Node.js + Electron | Gerenciamento de janelas, menus, IPC |
| **HTTP Server** | Node.js `https` | Servidor HTTPS local com certificado self-signed |
| **FastCGI** | Protocolo TCP | Comunicação com PHP via sockets |
| **PHP Runtime** | PHP-FPM (Linux) / PHP-CGI (Windows) | Execução do código PHP |

### Fluxo de uma Request PHP

1. Renderer faz request HTTPS para `https://localhost:PORT/page.php`
2. `http-server.js` recebe o request
3. Verifica se é arquivo estático ou PHP
4. Para PHP: chama `executePhp()` → `executeFastCGI()`
5. `fastcgi.js` abre socket TCP para o PHP
6. Envia parâmetros CGI + body via protocolo FastCGI
7. Recebe resposta (headers + body)
8. `parsePhpResponse()` extrai status, headers, body
9. Retorna resposta HTTP para o renderer

---

## 2. Arquivos do Projeto

### Arquivos Principais

| Arquivo | Função | Dependências |
|---|---|---|
| `main.js` | Entry point do Electron Main Process | electron, server/* |
| `preload.js` | Bridge entre Renderer e Main (contextBridge) | electron |
| `mifunctions.js` | Handlers IPC (dialog, tray, notifications) | electron |
| `milang.js` | Sistema de internacionalização (i18n) | fs, child_process |

### Arquivos do Servidor

| Arquivo | Função | Responsabilidades |
|---|---|---|
| `server/http-server.js` | Servidor HTTPS | Criar server, rotear requests, servir arquivos estáticos |
| `server/fastcgi.js` | Protocolo FastCGI | Criar/parsear registros, executar requests |
| `server/php-manager.js` | Gerenciamento PHP | Iniciar/parar PHP, criar configs FPM |
| `server/certificates.js` | Certificados TLS | Gerar certificado self-signed |
| `server/utils.js` | Utilitários | `exists()`, `findFreePort()`, `waitForPort()`, `getMimeType()` |

### Arquivos de Configuração

| Arquivo | Formato | Descrição |
|---|---|---|
| `app/config.json` | JSON | Configurações gerais do app |
| `app/menus/*.json` | JSON | Definições de menus |
| `app/langs/*.json` | JSON | Traduções (i18n) |
| `php/php.ini` | INI | Configurações do PHP |
| `electron-builder.yml` | YAML | Configuração de build |

### Estrutura de Diretórios

```
miphant/
├── main.js                    # Entry point Electron
├── preload.js                 # Context bridge
├── mifunctions.js             # IPC handlers
├── milang.js                  # i18n
├── server/
│   ├── http-server.js         # HTTPS server
│   ├── fastcgi.js             # FastCGI protocol
│   ├── php-manager.js         # PHP process manager
│   ├── certificates.js        # TLS certificates
│   └── utils.js               # Utilities
├── php/
│   ├── php-fpm                # Linux PHP binary
│   ├── php-cgi.exe            # Windows PHP binary
│   └── php.ini                # PHP config
├── app/
│   ├── config.json            # App config
│   ├── index.php              # Entry point PHP
│   ├── langs/                 # Translations
│   ├── menus/                 # Menu definitions
│   └── *.php                  # Pages de exemplo
├── staticphp/
│   ├── linux/                 # PHP binaries Linux
│   └── win32/                 # PHP binaries Windows
├── package.json               # Node.js dependencies
└── electron-builder.yml       # Build config
```

---

## 3. APIs do MiPhant

### API JavaScript (Renderer → Main)

A API é exposta via `window.miphant.*` no renderer. Definida em `preload.js`:

```javascript
// Versão
miphant.version('miphant')  // 'electron' | 'node' | 'chromium'

// Diálogos
miphant.alert(title, msg, type, button)
miphant.confirm(title, msg, type, ...buttons)
miphant.selectDirectory()
miphant.openFile(multi)
miphant.saveFile()

// Janelas
miphant.newWindow(url, width, height, resizable, frame, hide, menu)
miphant.openURL(url)
miphant.close()

// Sistema
miphant.translate(text, ...values)
miphant.notification(title, text)
miphant.tray(title, tooltip, icon, menu)
miphant.fileExists(filename)
miphant.exportPDF(filename, options)
miphant.devTools()
```

### Parâmetros de `miphant.newWindow()`

| Parâmetro | Tipo | Padrão | Descrição |
|---|---|---|---|
| `url` | string | `''` | URL relativa (ex: `'page.php'`) |
| `width` | number | `config.app.width` | Largura em pixels |
| `height` | number | `config.app.height` | Altura em pixels |
| `resizable` | boolean | `config.app.resizable` | Permite redimensionar |
| `frame` | boolean | `config.app.frame` | Mostra barra de título |
| `hide` | boolean | `config.app.hide` | Inicia oculta |
| `menu` | string | `'menu'` | Nome do arquivo de menu (sem `.json`) |

### PHP → JavaScript (via executeJavaScript)

O PHP pode chamar funções JS do renderer via `executeJavaScript`:

```php
// No PHP
echo '<script>window.location.assign("page.php");</script>';
```

---

## 4. Protocolo FastCGI

### Estrutura de um Request

```
1. FCGI_BEGIN_REQUEST (requestId, FCGI_RESPONDER, FCGI_KEEP_CONN=0)
2. FCGI_PARAMS (chave=valor, chunked se > 65535 bytes)
3. FCGI_PARAMS vazio (fim dos params)
4. FCGI_STDIN (body, chunked se > 65535 bytes)
5. FCGI_STDIN vazio (fim do body)
```

### Estrutura de uma Resposta

```
1. FCGI_STDOUT (headers PHP + body)
2. FCGI_END_REQUEST (appStatus, protocolStatus)
```

### Parâmetros CGI Enviados

| Parâmetro | Exemplo | Descrição |
|---|---|---|
| `GATEWAY_INTERFACE` | `CGI/1.1` | Versão CGI |
| `SERVER_SOFTWARE` | `MiPhant` | Nome do servidor |
| `REQUEST_METHOD` | `GET` | Método HTTP |
| `REQUEST_URI` | `/page.php` | URI original |
| `SCRIPT_FILENAME` | `/full/path/page.php` | Caminho do arquivo PHP |
| `DOCUMENT_ROOT` | `/path/to/app` | Raiz do documento |
| `QUERY_STRING` | `key=value` | Parâmetros GET |
| `SERVER_PORT` | `8443` | Porta do servidor |
| `HTTPS` | `on` | Indica HTTPS |
| `HTTP_*` | `HTTP_COOKIE=...` | Headers HTTP (whitelist) |

### Headers Whitelist (HTTP_*)

Apenas estes headers são passados ao PHP:
`accept`, `accept-charset`, `accept-encoding`, `accept-language`, `authorization`, `cache-control`, `cookie`, `host`, `if-modified-since`, `if-none-match`, `if-range`, `pragma`, `referer`, `user-agent`

---

## 5. PHP no MiPhant

### Execução por Plataforma

| Plataforma | Binário | Modo | Porta | Concorrência |
|---|---|---|---|---|
| **Linux** | `php-fpm` | Process manager | Dinâmica | ✅ Pool de workers |
| **Windows** | `php-cgi.exe` | FastCGI | Dinâmica | ⚠️ 1 request por vez |

### PHP-FPM (Linux)

Configuração gerada automaticamente em `server/php-manager.js`:

```ini
[global]
pid = /path/php-fpm.pid
error_log = /path/php-fpm.log
daemonize = no

[www]
listen = 127.0.0.1:PORT
listen.backlog = 128
pm = dynamic
pm.max_children = <CPUs * 2, máx 16>
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = <max_children / 2>
pm.max_requests = 500
clear_env = no
catch_workers_output = yes
request_slowlog_timeout = 5s
security.limit_extensions = .php
```

### PHP-CGI (Windows)

Iniciado com:
```
php-cgi.exe -b 127.0.0.1:PORT
```

- Modo FastCGI: processo fica vivo entre requests
- Single-threaded: processa 1 request por vez

### Variáveis de Ambiente PHP

| Variável | Descrição |
|---|---|
| `MIPHANT_ARGV` | Argumentos da linha de comando |
| `MIPHANT_USERNAME` | Nome do usuário do sistema |
| `MIPHANT_HOMEDIR` | Diretório home do usuário |
| `MIPHANT_PLATFORM` | Plataforma (linux/win32/darwin) |
| `MIPHANT_LANG` | Idioma do sistema |

**Importante**: `$_ENV` requer `variables_order = "EGPCS"` no `php.ini`.

### Extensões PHP

**Comuns**: bcmath, calendar, ctype, date, dom, fileinfo, filter, ftp, hash, iconv, json, libxml, mbstring, openssl, pcntl, pcre, PDO, pdo_sqlite, Phar, posix, readline, Reflection, session, SimpleXML, sodium, SPL, sqlite3, standard, tokenizer, xml, xmlreader, xmlwriter, zip, zlib

---

## 6. IPC (Inter-Process Communication)

### Fluxo IPC

```
Renderer (preload.js) → ipcRenderer.invoke() → Main (mifunctions.js) → ipcMain.handle()
```

### Registrando um Novo Handler

Em `mifunctions.js`:

```javascript
ipcMain.handle('appMinhaFuncao', async (_, param1, param2) => {
    // Lógica aqui
    return resultado;
});
```

Em `preload.js`:

```javascript
contextBridge.exposeInMainWorld('miphant', {
    minhaFuncao: (param1, param2) => ipcRenderer.invoke('appMinhaFuncao', param1, param2),
});
```

Em PHP (via JS):

```javascript
window.miphant.minhaFuncao('valor1', 'valor2').then(resultado => {
    // Usar resultado
});
```

---

## 7. Menu

### Estrutura de Menu JSON

```json
{
  "Arquivo": {
    "Novo": { "page": "new.php", "key": "CmdOrCtrl+N", "newwindow": true },
    "separator1": {},
    "Sair": { "script": "miphant.close();" }
  },
  "Ajuda": {
    "Sobre": { "page": "about.php", "key": "F1" }
  }
}
```

### Propriedades

| Propriedade | Tipo | Descrição |
|---|---|---|
| `page` | string | URL da página (relativa) |
| `url` | string | URL externa (abre no navegador) |
| `script` | string | JavaScript para executar |
| `key` | string | Atalho de teclado |
| `newwindow` | boolean | Abre em nova janela |
| `width` | number | Largura (se newwindow=true) |
| `height` | number | Altura (se newwindow=true) |
| `type` | string | Tipo do item (ex: 'checkbox') |

---

## 8. Build e Distribuição

### Comandos

```bash
npm install              # Instalar dependências
npm run start            # Executar em desenvolvimento
npm run dist-linux       # Build para Linux
npm run dist-win         # Build para Windows (via Docker)
./compile.sh             # Build completo (Linux + Windows)
```

### electron-builder.yml

```yaml
win:
  extraResources:
   - from: "./php/php.exe"
     to: "php/php.exe"
linux:
  extraResources:
   - from: "./php/php"
     to: "php/php"
```

### Output

- `dist/` — Pacotes gerados
- `dist/linux-unpacked/` — App Linux descompactado
- `dist/win-unpacked/` — App Windows descompactado

---

## 9. Padrões de Código

### Nomenclatura

| Tipo | Padrão | Exemplo |
|---|---|---|
| Variáveis | camelCase | `phpFcgiPort`, `httpsServer` |
| Funções | camelCase | `startPhp()`, `getNextFcgiPort()` |
| Constantes | UPPER_SNAKE | `FCGI_HOST`, `MAX_BODY_SIZE` |
| Arquivos | kebab-case | `php-manager.js`, `http-server.js` |
| IPC handlers | camelCase com prefixo `app` | `appNewWindow`, `appSair` |

### Tratamento de Erros

```javascript
// Sempre usar try/catch em funções async
async function startPhp() {
    try {
        // Lógica
    } catch (error) {
        console.error('[PHP] Erro:', error);
        throw error; // Re-throw para o caller decidir
    }
}

// IPC handlers retornam valores, não lançam erros
ipcMain.handle('appMinhaFuncao', async (_, param) => {
    try {
        return { success: true, data: resultado };
    } catch (error) {
        return { success: false, error: error.message };
    }
});
```

### Logs

```javascript
console.log('[PHP] Iniciando...');      // Info
console.error('[PHP] Erro:', error);    // Erro
console.warn('[PHP] Aviso...');         // Warning
```

### Async/Await

```javascript
// Sempre usar async/await em vez de callbacks
const port = await findFreePort(FCGI_HOST);
await waitForPort(FCGI_HOST, port, 10000);
await startPhp(resourcesPath, userDataPath);
```

---

## 10. Checklist para Modificações

### Ao adicionar nova funcionalidade IPC:

- [ ] Adicionar handler em `mifunctions.js`
- [ ] Adicionar API em `preload.js`
- [ ] Documentar em `SKILLS.md`
- [ ] Testar no renderer via `window.miphant.*`

### Ao modificar o servidor HTTP:

- [ ] Verificar headers whitelist
- [ ] Verificar path traversal protection
- [ ] Testar com arquivos estáticos
- [ ] Testar com PHP

### Ao modificar o gerenciador PHP:

- [ ] Testar start/stop
- [ ] Verificar cleanup de processos
- [ ] Verificar timeout
- [ ] Testar em Linux E Windows

### Ao modificar menus:

- [ ] Verificar estrutura JSON
- [ ] Testar todos os tipos (page, url, script)
- [ ] Verificar traduções

### Ao fazer build:

- [ ] Verificar `electron-builder.yml`
- [ ] Verificar `extraResources`
- [ ] Testar em ambas as plataformas
- [ ] Verificar tamanho do pacote

---

## 11. Segurança

### Certificados TLS

- Certificado self-signed gerado em `server/certificates.js`
- Armazenado em `userData/server/certificate/`
- Electron confia em `localhost`, `127.0.0.1`, `::1`

### Headers de Segurança

- CORS restrito para `https://localhost:PORT`
- CSP não é necessário para desktop
- Apenas headers da whitelist são passados ao PHP

### Path Traversal

- `resolvePublicPath()` valida que o caminho está dentro de `publicRoot`
- Bloqueia `..` e caracteres nulos

### Variáveis de Ambiente

- Apenas variáveis necessárias são copiadas para o PHP
- Tokens, chaves e variáveis internas do Electron NÃO são expostas

---

## 12. Troubleshooting

### PHP não inicia

1. Verificar se o binário existe: `ls -la php/php-fpm` ou `php/php-cgi.exe`
2. Verificar permissões de execução: `chmod +x php/php-fpm`
3. Verificar logs: `[PHP] stdout` e `[PHP] stderr`
4. Verificar se a porta está livre

### Request retorna 502

1. Verificar se o PHP está rodando: `netstat -tlnp | grep PORT`
2. Verificar logs PHP: `php/php-fpm.log`
3. Verificar `SCRIPT_FILENAME` correto
4. Verificar `variables_order = "EGPCS"` no `php.ini`

### Variáveis de ambiente vazias

1. Verificar `php/php.ini`: `variables_order = "EGPCS"`
2. Verificar `server/php-manager.js`: `getPhpEnvironment()` inclui MIPHANT_*
3. Verificar `main.js`: `process.env.MIPHANT_*` definidos ANTES de `startPhp()`

### Janela não abre

1. Verificar se `startMiPhantServer()` foi chamado
2. Verificar se o certificado foi gerado
3. Verificar se a porta HTTPS está livre
4. Verificar console do Electron (F12)

### Menu não aparece

1. Verificar se o arquivo JSON existe em `app/menus/`
2. Verificar estrutura do JSON
3. Verificar se `config.app.menu = true`
