# AGENTS.md - Histórico de Desenvolvimento MiPhant

## Visão Geral do Projeto

**MiPhant** é uma aplicação desktop baseada em Electron + Node.js + PHP que permite executar aplicações PHP como aplicativos desktop. O PHP é executado via protocolo FastCGI: PHP-FPM no Linux, PHP-CGI no Windows.

- **Repositório**: [miphant](https://github.com/profmugomes/miphant)
- **Versão atual**: 6.0.0
- **Licença**: PolyForm Perimeter 1.0.1

---

## Funcionalidades Implementadas

### BUG 1: URL malformada em `miphantNewWindow`
**Problema**: URL estava com `//` duplo (ex: `https://localhost//app`).

**Solução**: Refatorar para usar `cleanUrl` sem duplicação.

**Arquivos**: `main.js`, `preload.js`, `mifunctions.js`

---

### BUG 2: Incompatibilidade de argumentos entre preload/mifunctions/main
**Problema**: Ordem dos parâmetros inconsistente entre arquivos.

**Solução**: Padronizar para `url, width, height, resizable, frame, hide, menu` em todos.

**Arquivos**: `preload.js`, `mifunctions.js`, `main.js`

---

### BUG 3: Conflito com `browser-window-created`
**Problema**: Listener `browser-window-created` conflitava com `createMenu()`.

**Solução**: Remover listener contraditório.

**Arquivo**: `main.js`

---

### BUG 4: `setWindowOpenHandler` confundia URLs internas/externas
**Problema**: URLs `localhost` estavam abrindo em janela externa.

**Solução**: Distinguir URLs internas (localhost → nova janela) vs externas (`shell.openExternal`).

**Arquivo**: `main.js`

---

### BUG 5: `appDevTools()` não existia
**Problema**: Chamada para função inexistente.

**Solução**: Corrigir para `openDevTools()` + verificação de null.

**Arquivo**: `mifunctions.js`

---

### BUG 6: Tray usando referência de janela destruída
**Problema**: Tray tentava acessar janela que já foi fechada.

**Solução**: Usar `BrowserWindow.getAllWindows()` para encontrar janela válida.

**Arquivo**: `mifunctions.js`

---

### BUG 7: Race condition em `miphantNewWindow`
**Problema**: Servidor PHP não estava pronto quando janela tentava acessar.

**Solução**: Tornar `miphantNewWindow` async com `await startMiPhantServer()`.

**Arquivo**: `main.js`

---

### MELHORIA: Propriedades de config em `createWindow`
**Problema**: Propriedades usavam nomes em português (`largura`, `altura`).

**Solução**: Corrigir para `width`, `height`, etc.

**Arquivo**: `main.js`

---

### MELHORIA: Timeout no shutdown handler
**Problema**: `before-quit` poderia travar indefinidamente.

**Solução**: Adicionar timeout de 10 segundos no handler.

**Arquivo**: `main.js`

---

### FIX: Variáveis de ambiente `$_ENV` não funcionavam
**Problema**: `$_ENV` retornava vazio no PHP.

**Solução (3 partes)**:
1. Mover `process.env.MIPHANT_*` antes de `startPhp()` em `main.js`
2. Adicionar variáveis ao `getPhpEnvironment()` em `php-manager.js`
3. Adicionar `variables_order = "EGPCS"` no `php/php.ini`

**Arquivos**: `main.js`, `server/php-manager.js`, `php/php.ini`

---

### MELHORIA: README completo
**Solução**: Reescrever documentação com:
- Diagrama de Arquitetura
- Documentação da API MiPhant
- Seção de Execução PHP (FPM + CGI)
- Estrutura do Projeto
- Documentação de config
- Variáveis de ambiente
- Instruções de build
- Lista de extensões PHP

**Arquivo**: `README.md`

---

### MELHORIA: Language fallback
**Problema**: `milang.js` não fazia fallback para idiomas alternativos.

**Solução**: Adicionar cadeia de fallback (`pt-br` → `pt-br.json` → `pt.json` → `en.json`).

**Arquivo**: `milang.js`

---

### MELHORIA: Versão 5.0.0
**Solução**: Atualizar versão em `package.json` e `app/config.json`.

**Arquivos**: `package.json`, `app/config.json`

---

### MELHORIA: Otimização de código
**Problema**: Imports não utilizados, variáveis mortas, funções duplicadas, `require` em posições inconsistentes.

**Solução**: 25 otimizações em 7 arquivos — remoção de imports/vars/funs não usados, movimentação de requires para o topo, limpeza de exports.

**Arquivos**: `main.js`, `preload.js`, `mifunctions.js`, `milang.js`, `server/http-server.js`, `server/php-manager.js`, `server/fastcgi.js`

---

### MELHORIA: Logger centralizado
**Problema**: `console.log` espalhados, sem controle de verbose.

**Solução**: Criar `server/logger.js` com `log()` condicional a `config.dev.tools`, `error()`/`warn()` sempre visíveis.

**Arquivos**: `server/logger.js`, `main.js`, `server/http-server.js`, `server/php-manager.js`, `server/certificates.js`

---

### MELHORIA: PHP-FPM condicional
**Problema**: Diretivas `user`/`group` no PHP-FPM causavam NOTICE quando não rodava como root.

**Solução**: Condicionar `user`/`group` a `process.getuid() === 0`.

**Arquivo**: `server/php-manager.js`

---

### MELHORIA: MiPhantLibs 5.0.0
**Problema**: Biblioteca PHP desatualizada com dependências quebradas.

**Solução**: Corrigir 8 arquivos:
- `composer.json`: Criado com PSR-4 autoloader `MiPhantLibs\`
- `libs/app/config.php`: Caminho `config/config.json` → `config.json`
- `libs/system/server.php`: `domain()` usa `https://`, `documentroot()` usa `dirname(__FILE__, 2)`
- `libs/system/env.php`: `args()` → `argv()`, variável `MIPHANT_ARGS` → `MIPHANT_ARGV`
- `libs/langs/translate.php`: Fallback `lang/en.json` → `../langs/en.json`
- `libs/system/platform.php`: Simplificado com `PHP_OS_FAMILY`, adicionado `osWindows()`
- `libs/app/path.php`: Simplificado com `DIRECTORY_SEPARATOR` + `implode()`
- `libs/app/functions.php`: `newWindow()` parâmetros alinhados com Node.js
- `libs/app/about.php`: Inline styles movidos para CSS compartilhado

**Arquivos**: `composer.json`, `app/libs/app/config.php`, `app/libs/system/server.php`, `app/libs/system/env.php`, `app/libs/langs/translate.php`, `app/libs/system/platform.php`, `app/libs/app/path.php`, `app/libs/app/functions.php`, `app/libs/app/about.php`

---

### MELHORIA: Design System e Redesign Demo
**Problema**: Páginas demo com inline styles e visual fragmentado.

**Solução**:
- Criar `app/style.css` com tema dark (470+ linhas, variáveis CSS, grid, cards, botões, tabelas, badges)
- Redesenhar 17 páginas demo com estrutura consistente
- Reorganizar `menus/menu.json` em 8 categorias (Home, System, Dialogs, Files, Data, Forms, i18n, About)

**Arquivos**: `app/style.css`, `app/index.php`, `app/env.php`, `app/args.php`, `app/message.php`, `app/notification.php`, `app/openfile.php`, `app/openfiles.php`, `app/savefile.php`, `app/selectdirectory.php`, `app/cookies.php`, `app/session.php`, `app/sqlite.php`, `app/formget.php`, `app/formpost.php`, `app/translate.php`, `app/timezone.php`, `app/pdf.php`, `app/extramenu.php`, `app/menus/menu.json`

---

### MELHORIA: URL Amigável (Front Controller Pattern)

**Problema**: Servidor HTTP não suportava frameworks PHP como Laravel, Symfony, CodeIgniter, etc. que dependem de URL rewriting para roteamento interno.

**Solução**: Implementar `server.router` no `app/config.json` que roteia URLs sem arquivo físico correspondente para `index.php` (front controller).

**Mudanças**:
1. `server/http-server.js`: Adicionado `routerEnabled`, `setRouter()`, `resolveFrontController()`, parâmetro `isRouted` em `createCgiParameters()`, fallback para front controller em `handleRequest()`
2. `main.js`: Import e chamada de `setRouter(config.server.router)`
3. `app/config.json`: Campo `server.router` (padrão: `false`)
4. `SKILLS.md`: Documentação completa da funcionalidade

**Arquivos**: `server/http-server.js`, `main.js`, `app/config.json`, `SKILLS.md`

---

### MELHORIA: Headers CGI — Whitelist → Blacklist

**Problema**: Whitelist de headers CGI impedia frameworks como Laravel (Livewire) de funcionar corretamente. Headers como `x-livewire` e `x-csrf-token` eram silenciosamente descartados, causando 404 ao salvar senha e erros 419 CSRF.

**Solução**: Inverter a lógica de whitelist para blacklist. Seguindo o padrão de Apache/Nginx que passam TODOS os headers, agora apenas headers perigosos conhecidos são bloqueados (`proxy`, `x-forwarded-*`, `x-real-ip`).

**Benefícios**:
- Compatibilidade universal com qualquer framework PHP
- Corrige 404 ao alterar senha no Laravel (Livewire)
- Corrige erros 419 CSRF no Laravel
- Sem necessidade de editar o código quando um framework novo precisar de um header específico

**Arquivos**: `server/http-server.js`, `SKILLS.md`

---

### v6.0.0: Migração de Licença para PolyForm Perimeter 1.0.1

**Problema**: Licença MIT permitia uso irrestrito, incluindo concorrência direta.

**Solução**: Migrar para PolyForm Perimeter 1.0.1 — permite uso, modificação e distribuição, mas proíbe competição.

**Mudanças**:
1. Substituído `LICENSE` (MIT) por `LICENSE.md` (PolyForm Perimeter 1.0.1)
2. Atualizados headers de copyright em 21 libs PHP, 5 build scripts e 10 arquivos JS
3. Atualizado `package.json` e `app/config.json`: `"license": "SEE LICENSE IN LICENSE.md"`
4. Atualizado `electron-builder.yml`: `./LICENSE` → `./LICENSE.md`
5. Reescrita seção de licença do `README.md` em português
6. Corrigidos caminhos de LICENSE em `about.php` e `libs/app/about.php` para funcionar em dev e produção

**Arquivos**: `LICENSE.md`, `LICENSE`, `README.md`, `package.json`, `app/config.json`, `electron-builder.yml`, `app/public/about.php`, `app/public/libs/app/about.php` + 21 libs PHP + 5 build scripts + 10 arquivos JS

---

### v6.0.0: Refatoração — fastcgi.js → php-protocol.js + config.js

**Problema**: Nome `fastcgi.js` causava confusão com FastAPI (Python). Configurações hardcoded (HOST, portas, timeouts) espalhadas em múltiplos arquivos.

**Solução**:
1. Renomeado `server/fastcgi.js` → `server/php-protocol.js`
2. Renomeado constantes `FCGI_*` → `PHP_PROTOCOL_*`
3. Renomeado função `executeFastCGI()` → `executePhpProtocol()`
4. Criado `server/config.js` com configurações centralizadas: `HOST`, `DEFAULT_HTTPS_PORT`, `MAX_BODY_SIZE`, `PHP_TIMEOUT`, `PHP_MAX_RESPONSE_SIZE`
5. Atualizados imports em `http-server.js`, `php-manager.js` e `main.js`

**Arquivos**: `server/fastcgi.js` (deletado), `server/php-protocol.js` (novo), `server/config.js` (novo), `server/http-server.js`, `server/php-manager.js`, `main.js`

---

### v6.0.0: Correções de Bugs nas Libs PHP

**Bug 1 — `folder.php::excluirRecursivamente()`**
- **Problema**: Usava `path->join()` que removia barras iniciais de caminhos absolutos, causando falha em `rmdir()` e `unlink()`
- **Solução**: Usar concatenação direta com `DIRECTORY_SEPARATOR` em vez de `path->join()`

**Bug 2 — `datetime.php::monthInFull(0)`**
- **Problema**: `ltrim(0, '0')` retornava string vazia, gerando acesso a índice inexistente no array e retornando `null` em vez de `string`
- **Solução**: Usar acesso direto `$meses[$mes] ?? ''` em vez de `ltrim()`

**Bug 3 — `about.php` caminhos LICENSE**
- **Problema**: `str_replace()` para calcular caminhos de LICENSE quebrava tanto em desenvolvimento quanto em produção
- **Solução**: Usar `dirname(documentroot(), 2)` que funciona em ambos os ambientes

**Arquivos**: `app/public/libs/app/folder.php`, `app/public/libs/app/datetime.php`, `app/public/libs/app/about.php`, `app/public/about.php`

---

### v6.0.0: Suite de Testes MiPhantLibs

**Problema**: Nenhuma verificação automatizada das 21 libs PHP.

**Solução**: Criado `tests/test-libs.php` com 101 testes cobrindo:
- Todas as 21 libs PHP (app/, system/, langs/, security/)
- 10 arquivos JS (syntax check com `node --check`)
- Headers de licença em todos os arquivos
- Framework de teste simples (sem dependências externas)

**Execução**: `/usr/bin/php8.5 tests/test-libs.php`

**Arquivo**: `tests/test-libs.php` (novo)

---

## Arquivos Modificados

| Arquivo | Mudanças |
|---|---|
| `main.js` | Async miphantNewWindow, env ordering, before-quit timeout, config props, setRouter, import HOST de config |
| `preload.js` | Padronização de parâmetros |
| `mifunctions.js` | Correção devTools, tray, parâmetros |
| `milang.js` | Define `process.env.MIPHANT_LANG` |
| `server/php-manager.js` | Adicionado `MIPHANT_*` ao `getPhpEnvironment()`, renomeado `phpFcgiPort`→`phpPort` |
| `server/http-server.js` | Servidor HTTPS com PHP protocol, URL routing (front controller), blacklist de headers CGI |
| `server/php-protocol.js` | Renomeado de `fastcgi.js`, constantes `PHP_PROTOCOL_*` (novo) |
| `server/config.js` | Configurações centralizadas: HOST, portas, timeouts (novo) |
| `php/php.ini` | Adicionado `variables_order = "EGPCS"` |
| `app/config.json` | Atualizado para versão 6.0.0, license PolyForm |
| `package.json` | Atualizado para versão 6.0.0, license PolyForm |
| `LICENSE.md` | Licença PolyForm Perimeter 1.0.1 (novo) |
| `README.md` | Documentação reescrita + seção licença em português |
| `electron-builder.yml` | `./LICENSE` → `./LICENSE.md` |
| `server/logger.js` | Logger centralizado (novo) |
| `composer.json` | PSR-4 autoloader (novo) |
| `app/style.css` | Design system dark (novo) |
| `app/public/libs/*` | MiPhantLibs 6.0.0 — headers PolyForm + fixes folder/datetime/about |
| `app/*.php` | 17 páginas demo redesenhadas |
| `app/menus/menu.json` | Reorganizado em 8 categorias |
| `tests/test-libs.php` | Suite de testes com 101 testes (novo) |

---

## Arquitetura do Sistema

```
┌─────────────────────────────────────────────────────────┐
│                    MiPhant Desktop                       │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────────┐    ┌──────────────┐                   │
│  │   Electron   │    │  Node.js     │                   │
│  │   (Renderer) │◄──►│  (Main)      │                   │
│  └──────────────┘    └──────┬───────┘                   │
│                             │                           │
│                    ┌────────▼────────┐                  │
│                    │   HTTPS Server  │                  │
│                    │   (port 8443)   │                  │
│                    └────────┬────────┘                  │
│                             │                           │
│                    ┌────────▼────────┐                  │
│                    │   FastCGI       │                  │
│                    │   Protocol      │                  │
│                    └────────┬────────┘                  │
│                             │                           │
│            ┌────────────────┼────────────────┐         │
│            │                │                │         │
│   ┌────────▼───────┐ ┌─────▼──────┐ ┌──────▼──────┐ │
│   │   PHP-FPM      │ │  PHP-CGI   │ │   Static    │ │
│   │   (Linux)      │ │  (Windows) │ │   Files     │ │
│   │   Pool workers │ │  Single    │ │             │ │
│   └────────────────┘ └────────────┘ └─────────────┘ │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## PHP no MiPhant

### Execução

| Plataforma | Binário | Modo | Concorrência |
|---|---|---|---|
| **Linux** | `php-fpm` | Process manager com pool dinâmico | ✅ Paralelo (até 16 workers) |
| **Windows** | `php-cgi.exe` | FastCGI (processo único) | ⚠️ Sequencial (1 request por vez) |

### Compilação

PHP é compilado estaticamente via `static-php-cli`:
- Binários em `staticphp/linux/` e `staticphp/win32/`
- Cópias em `php/` para distribuição

### Extensões PHP atualmente compiladas

**Comuns**: bcmath, calendar, ctype, date, dom, fileinfo, filter, ftp, hash, iconv, json, libxml, mbstring, openssl, pcntl, pcre, PDO, pdo_sqlite, Phar, posix, readline, Reflection, session, SimpleXML, sodium, SPL, sqlite3, standard, tokenizer, xml, xmlreader, xmlwriter, zip, zlib

---

## Variáveis de Ambiente MiPhant

| Variável | Descrição |
|---|---|
| `MIPHANT_ARGV` | Argumentos da linha de comando |
| `MIPHANT_USERNAME` | Nome do usuário do sistema |
| `MIPHANT_HOMEDIR` | Diretório home do usuário |
| `MIPHANT_PLATFORM` | Plataforma (linux/win32/darwin) |
| `MIPHANT_LANG` | Idioma do sistema |

---

## Configuração do App (`app/config.json`)

```json
{
  "width": 1200,
  "height": 800,
  "resizable": true,
  "frame": true,
  "hide": false,
  "menu": true,
  "version": "6.0.0"
}
```

| Campo | Tipo | Descrição |
|---|---|---|
| `width` | number | Largura da janela |
| `height` | number | Altura da janela |
| `resizable` | boolean | Permite redimensionar |
| `frame` | boolean | Mostra barra de título |
| `hide` | boolean | Inicia oculta |
| `menu` | boolean | Mostra menu |
| `version` | string | Versão do app |

---

## Build e Distribuição

### Pré-requisitos
- Node.js 18+
- npm

### Comandos
```bash
npm install          # Instalar dependências
npm run dist-linux   # Build para Linux
npm run dist-win     # Build para Windows (via Docker)
./compile.sh         # Build completo (Linux + Windows)
```

### Output
- `dist/` — Pacotes gerados pelo electron-builder

---

## Pendências

1. **GitHub Actions workflow**: Não criado ainda para build automático do PHP
2. **Concorrência Windows**: Análise feita (pool de `php-cgi.exe`), mas não implementada por pedido do usuário

---

## Notas Técnicas

- `$_ENV` requer `variables_order = "EGPCS"` no `php.ini`
- CSP header em PHP é desnecessário para desktop (informado ao usuário)
- PHP-CGI no Windows é suficiente para uso desktop (poucas janelas simultâneas)
- PHP-FPM no Linux suporta alta concorrência nativamente
