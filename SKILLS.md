# SKILLS.md — MiPhant Developer Guide

> Guia para desenvolvimento de aplicações PHP desktop com MiPhant.
> Todos os arquivos listados estão na pasta `app/` da aplicação.

---

## 1. Estrutura da Aplicação

```
app/
├── config.json              # Configuração do app (janela, versão, etc.)
├── menus/
│   ├── menu.json            # Menu principal
│   └── *.json               # Menus customizados por página
├── langs/
│   ├── en.json              # Inglês (fallback padrão)
│   └── pt.json              # Português
├── libs/                    # MiPhantLibs (biblioteca PHP)
│   ├── app/                 # Classes de aplicação
│   ├── langs/               # Tradução
│   ├── system/              # Sistema
│   └── security/            # Segurança
├── guard/
│   └── php-guard.php        # Guard auto_prepend (interno, não alterar)
├── style.css                # Design system dark
├── index.php                # Página inicial
└── *.php                    # Páginas da aplicação
```

### Arquivos acessíveis

| Pasta | Conteúdo | Acesso |
|---|---|---|
| `app/*.php` | Páginas PHP da aplicação | Leitura/escrita pelo PHP |
| `app/config.json` | Configuração | Leitura pelo PHP |
| `app/menus/*.json` | Definições de menu | Leitura pelo Electron |
| `app/langs/*.json` | Traduções | Leitura pelo PHP |
| `app/libs/` | MiPhantLibs | Leitura pelo PHP |
| `app/guard/php-guard.php` | Guard de autenticação Node→PHP | Somente leitura — interno, não alterar |
| `app/style.css` | Design system | Leitura pelo HTML |

> **Nota**: Arquivos internos do servidor (`server/`, `node_modules/`, etc.) estão empacotados dentro do ASAR e não são acessíveis diretamente pela aplicação PHP.
>
> O `app/guard/php-guard.php` é carregado como `auto_prepend_file` em **toda** requisição. Ele não é uma página e não deve ser editado: sem ele, o PHP responde `403`. Detalhes em [Segurança de Runtime](#10-segurança-de-runtime-psk).

---

## 2. Navegação

### Como funciona

MiPhant usa HTTPS local para servir páginas PHP. O Electron carrega uma URL HTTPS que aponta para o servidor interno. O PHP é executado e retorna HTML que é renderizado no Chromium.

### Navegação entre páginas

**1. Links HTML diretos:**
```html
<a href="outra-pagina.php">Ir para outra página</a>
```

**2. Via JavaScript (miphant API):**
```javascript
// Navega na janela atual
window.location.assign('outra-pagina.php');

// Abre em nova janela
miphant.newWindow('outra-pagina.php', 800, 600, true, true, false, 'menu');

// Redireciona com parâmetros
window.location.assign('pagina.php?id=42&nome=teste');
```

**3. Via PHP (MiPhantLibs):**
```php
use MiPhantLibs\app\functions;

$func = new functions();

// Redireciona (gera <script>window.location.assign(...)</script>)
$func->redirect('outra-pagina.php');

// Redireciona com parâmetros
$func->redirect('pagina.php', ['id' => 42, 'nome' => 'teste']);

// Abre nova janela
$func->newWindow('pagina.php', 1024, 768);

// Abre URL externa no navegador do sistema
$func->openURL('https://seu-dominio');

// Fecha a janela atual
$func->closeWindow();
```

### Links externos

Toda navegação é confinada à origem interna (`https://localhost:<porta>`).
Um link para outro domínio **não** navega dentro da janela: o MiPhant
bloqueia a navegação e abre a URL no navegador do sistema
(`shell.openExternal`). Isso evita que uma página externa herde a bridge
`window.miphant`. Use `miphant.openURL(url)` quando quiser abrir algo fora
do app.

URLs internas continuam funcionando normalmente (`index.php`, `/pagina`,
`?id=42`), inclusive via `window.location.assign`, `miphant.newWindow` e
`$func->redirect`.

### Menu Principal

O menu é definido em `app/menus/menu.json`. Cada página pode ter um menu customizado criando um arquivo JSON com o mesmo nome.

**Exemplo** — `app/menus/menu.json`:
```json
{
    "Home": {
        "Home": {
            "page": "index.php",
            "key": "Ctrl+H"
        }
    },
    "Arquivo": {
        "Abrir": {
            "page": "openfile.php",
            "key": "Ctrl+O"
        },
        "Salvar": {
            "page": "savefile.php",
            "key": "Ctrl+S"
        },
        "separator1": {},
        "Sair": {
            "script": "miphant.close();"
        }
    },
    "Ajuda": {
        "Sobre": {
            "page": "about.php",
            "key": "F1"
        }
    }
}
```

**Propriedades de cada item:**

| Propriedade | Tipo | Descrição |
|---|---|---|
| `page` | string | Página PHP para navegar |
| `url` | string | URL externa (abre no navegador do sistema) |
| `script` | string | JavaScript para executar |
| `key` | string | Atalho de teclado (formato Electron) |
| `newwindow` | boolean | Abre em nova janela (padrão: false) |
| `width` | number | Largura da nova janela |
| `height` | number | Altura da nova janela |
| `resizable` | boolean | Permite redimensionar |
| `frame` | boolean | Mostra barra de título |
| `hide` | boolean | Inicia oculta |

### Menu Customizado por Página

Para criar um menu específico para uma página:

1. Crie o arquivo PHP: `app/minha-pagina.php`
2. Crie o menu JSON: `app/menus/minha-pagina.json`
3. Ao abrir a janela, passe o nome do menu:
   ```javascript
   miphant.newWindow('minha-pagina.php', 800, 600, true, true, false, 'minha-pagina');
   ```

---

## 3. MiPhantLibs — Biblioteca PHP

Biblioteca incluída em `app/libs/`. Não requer Composer — os arquivos são carregados via `require_once`.

### Carregando as libs

```php
require_once __DIR__ . '/libs/app/config.php';
require_once __DIR__ . '/libs/app/functions.php';
require_once __DIR__ . '/libs/app/path.php';
require_once __DIR__ . '/libs/app/file.php';
require_once __DIR__ . '/libs/langs/translate.php';
require_once __DIR__ . '/libs/system/server.php';
require_once __DIR__ . '/libs/system/env.php';
require_once __DIR__ . '/libs/system/platform.php';
```

### Namespace `MiPhantLibs\app`

#### `config`
Lê valores de `app/config.json`.

```php
use MiPhantLibs\app\config;

$cfg = new config();
$width = $cfg->get('app', 'width');        // 800
$version = $cfg->get('app', 'version');    // "7.1.0"
$author = $cfg->get('app', 'author', 'name'); // "Murilo Gomes"
```

#### `functions`
Gera chamadas JavaScript via preload.

```php
use MiPhantLibs\app\functions;

$func = new functions();

// Alert (envolto em <script> automaticamente)
$func->alert('Titulo', 'Mensagem', 'info');

// Confirm com callback
$func->confirm('Titulo', 'Deseja continuar?', 'question',
    function () { echo "Confirmado!"; },
    function () { echo "Cancelado!"; }
);

// Nova janela
$func->newWindow('pagina.php', 800, 600);

// Redireciona
$func->redirect('outra-pagina.php', ['id' => 1]);

// Notificação desktop
$func->notification('Titulo', 'Corpo da notificação');

// System tray
$func->tray('App', 'Tooltip', '', [
    ['label' => 'Home', 'page' => 'index.php'],
    ['label' => 'Sair', 'script' => 'miphant.close();']
]);

// Sem tags <script> (para uso inline)
$func->noTag()->alert('Titulo', 'Msg', 'info');
```

#### `file`
Operações com arquivos.

```php
use MiPhantLibs\app\file;

$file = new file();

$file->create('/tmp/teste.txt');                    // Cria arquivo
$file->save('/tmp/teste.txt', 'conteúdo');          // Salva (substitui)
$file->save('/tmp/teste.txt', 'mais', false);       // Salva (append)
$content = $file->open('/tmp/teste.txt');            // Lê conteúdo
$exists = $file->exists('/tmp/teste.txt');           // Verifica existência
$file->remove('/tmp/teste.txt');                     // Remove arquivo
$hasExt = $file->checkExtension('arquivo.txt', 'txt'); // Verifica extensão
```

#### `folder`
Operações com pastas.

```php
use MiPhantLibs\app\folder;

$folder = new folder();

$folder->create('/tmp/pasta/subpasta');             // Cria pasta recursiva
$folder->remove('/tmp/pasta', true);                 // Remove recursivamente
$exists = $folder->exists('/tmp/pasta');             // Verifica existência
```

#### `path`
Construtor de caminhos cross-platform.

```php
use MiPhantLibs\app\path;

$path = new path();
$full = $path->join('app', 'data', 'file.txt');
// Linux:   "app/data/file.txt"
// Windows: "app\data\file.txt"
```

#### `text`
Manipulação de texto.

```php
use MiPhantLibs\app\text;

$text = new text();

$text->removeAccent('São Paulo');        // "Sao Paulo"
$text->removeSpecialCharacters('a/b*c'); // "abc"
$text->separator('São Paulo');           // "Sao-Paulo"
```

#### `numbers`
Formatação de números.

```php
use MiPhantLibs\app\numbers;

$numbers = new numbers();

$numbers->currencyReal('1234.56');  // "1.234,56"
$numbers->format('5');              // "05"
```

#### `datetime`
Manipulação de datas.

```php
use MiPhantLibs\app\datetime;

$dt = new datetime();

$dt->date();                                    // "15/09/2025"
$dt->now();                                     // "15/09/2025 14:30:00"
$dt->change('15/09/2025', 'd/m/Y', 'Y-m-d');   // "2025-09-15"
$dt->dayOfTheWeek('2025-09-15');                 // "Monday"
$dt->monthInFull(9);                             // "September"
```

#### `arrays`
Manipulação de arrays.

```php
use MiPhantLibs\app\arrays;

$arr = new arrays();

$arr->check('hello world', 'hello');  // true
$arr->check('hello world', 'xyz');    // false

$data = ['user' => ['name' => 'John']];
$arr->getCustom($data, 'user', 'name'); // "John"
```

#### `about`
Página sobre com informações do sistema.

```php
use MiPhantLibs\app\about;

$about = new about();
$about->show(); // Exibe informações do app + licenças
```

### Namespace `MiPhantLibs\langs`

#### `translate`
Tradução i18n com cadeia de fallback.

```php
use MiPhantLibs\langs\translate;

$translate = new translate();

echo $translate->get('Continue');                        // "Continuar"
echo $translate->get('Unable to find file %s', 'x.php'); // "Não foi possível encontrar o arquivo x.php"
```

**Cadeia de fallback**: `pt-br` → `pt.json` → `en.json`

### Namespace `MiPhantLibs\system`

#### `env`
Acessa variáveis de ambiente do MiPhant.

```php
use MiPhantLibs\system\env;

$env = new env();

$env->get('MIPHANT_CUSTOM_VAR');  // Variável personalizada
$env->username();                  // Nome do usuário
$env->lang();                      // Idioma (ex: 'pt-br')
$env->platform();                  // Plataforma (ex: 'linux')
$env->homeDir();                   // Diretório home
$env->argv();                      // Argumentos da linha de comando
```

**Variáveis disponíveis via `$_ENV`:**

| Variável | Descrição |
|---|---|
| `$_ENV['MIPHANT_LANG']` | Idioma do sistema |
| `$_ENV['MIPHANT_USERNAME']` | Nome do usuário |
| `$_ENV['MIPHANT_HOMEDIR']` | Diretório home |
| `$_ENV['MIPHANT_PLATFORM']` | Plataforma (linux/win32) |
| `$_ENV['MIPHANT_ARGV']` | Argumentos CLI |

#### `server`
Informações do servidor.

```php
use MiPhantLibs\system\server;

$server = new server();

$server->domain();        // "https://localhost:8443"
$server->uri();           // URI atual (ex: '/page.php')
$server->documentroot();  // Raiz do app (ex: '/path/to/app/public')
```

#### `platform`
Detecção de plataforma.

```php
use MiPhantLibs\system\platform;

$platform = new platform();

$platform->osLinux();    // true no Linux
$platform->osWindows();  // true no Windows
```

### Namespace `MiPhantLibs\security`

#### `items`
Sanitização de entrada.

```php
use MiPhantLibs\security\items;

$items = new items();

$items->clean('<script>alert(1)</script>hello'); // "hello"
$items->clean(null);                             // ""
$items->clean('  texto  ');                      // "texto"
```

#### `get` / `post`
Leitura segura de parâmetros GET/POST.

```php
use MiPhantLibs\security\get;
use MiPhantLibs\security\post;

$get = new get();
$post = new post();

$value = $get->get('id');           // Lê $_GET['id']
$exists = $get->exists('id');       // Verifica se existe
$isGet = $get->request();           // true se método GET

$value = $post->get('nome');        // Lê $_POST['nome']
$exists = $post->exists('nome');    // Verifica se existe
$isPost = $post->request();         // true se método POST
```

---

## 4. Preload API — JavaScript

O objeto `miphant` está disponível no renderer (JavaScript do navegador) via `preload.js`.

### Aplicação

| Método | Descrição |
|---|---|
| `miphant.version(type)` | Versão: `'miphant'`, `'electron'`, `'node'`, `'chromium'` |
| `miphant.close()` | Fecha a aplicação |

### Diálogos

| Método | Descrição |
|---|---|
| `miphant.alert(title, msg, type, button)` | Caixa de alerta |
| `miphant.confirm(title, msg, type, ...buttons)` | Caixa de confirmação |
| `miphant.openFile(multi)` | Diálogo de abrir arquivo |
| `miphant.saveFile()` | Diáquivo de salvar arquivo |
| `miphant.selectDirectory()` | Diálogo de selecionar pasta |

### Janelas

| Método | Descrição |
|---|---|
| `miphant.newWindow(url, width, height, resizable, frame, hide, menu)` | Abre nova janela |
| `miphant.openURL(url)` | Abre URL no navegador do sistema |

### Sistema

| Método | Descrição |
|---|---|
| `miphant.notification(title, text)` | Notificação desktop |
| `miphant.tray(title, tooltip, icon, menus)` | Configura system tray |
| `miphant.devTools()` | Abre DevTools |
| `miphant.fileExists(filename)` | Verifica se arquivo existe |
| `miphant.exportPDF(filename, options)` | Exporta página como PDF |

### Tradução

| Método | Descrição |
|---|---|
| `miphant.translate(text, ...values)` | Traduz usando arquivos de idioma |

### Exemplos

```javascript
// Alert
miphant.alert('Info', 'Mensagem', 'info', 'OK');

// Confirm
const result = await miphant.confirm(
    'Confirmar', 'Deseja continuar?', 'question', 'Sim', 'Não'
);
if (result === 0) { /* Sim */ } else { /* Não */ }

// Nova janela
miphant.newWindow('pagina.php', 1024, 768, true, true, false, 'menu');

// Notificação
miphant.notification('Titulo', 'Corpo da mensagem');

// System tray
miphant.tray('App', 'Tooltip', '', JSON.stringify({
    "Home": { page: "index.php" },
    "Fechar": { script: "window.close();" }
}));

// Versão
const version = await miphant.version('miphant');
```

---

## 5. Design System

MiPhant inclui um design system dark em `app/style.css`.

### Usando

```html
<link rel="stylesheet" href="/style.css">
```

### Componentes

| Classe | Descrição |
|---|---|
| `.card` | Container com fundo escuro |
| `.btn` | Botão base |
| `.btn-primary` | Botão de ação principal |
| `.btn-outline` | Botão contornado |
| `.btn-success` | Botão de sucesso |
| `.badge` | Label pequena |
| `.badge-info` | Badge informativo |
| `.grid` | Grid responsivo |
| `.grid-4` | Grid com 4 colunas |
| `.stat` | Bloco de estatística |
| `.stat-value` | Valor da estatística |
| `.stat-label` | Label da estatística |
| `.text-muted` | Texto com cor suave |
| `.text-success` | Texto de sucesso |
| `.text-danger` | Texto de erro |
| `.collapsible` | Botão colapsável |
| `.mt-1`, `.mt-2`, `.mt-3` | Margem superior |
| `.mb-1`, `.mb-2`, `.mb-3` | Margem inferior |

---

## 6. Configuração

O app é configurado via `app/config.json`:

```json
{
    "app": {
        "id": "miphant",
        "name": "MiPhant",
        "version": "7.1.0",
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
        "router": false
    },
    "dev": {
        "menu": false,
        "tools": false
    }
}
```

### Opções

**app**
- `id`: Identificador da aplicação
- `name`: Nome exibido
- `version`: Versão
- `width` / `height`: Dimensões da janela
- `resizable`: Permite redimensionar
- `frame`: Mostra barra de título
- `hide`: Inicia com janela oculta
- `icon`: Arquivo de ícone
- `disableAccelerationHardware`: Desabilita aceleração GPU
- `author`: Informações do autor (name, email, url)
- `homepage`: URL do projeto
- `license`: Tipo de licença
- `copyright`: Aviso de copyright

**server**
- `router`: Habilita roteamento PHP (front controller pattern)

**dev**
- `menu`: Mostra menu de desenvolvimento
- `tools`: Abre DevTools automaticamente e habilita páginas de diagnóstico (`/phpinfo`)

> **Segurança não é configurável.** Não existe chave, `enabled` ou modo em
> `app/config.json`: a PSK de runtime, o pinning de certificado, a validação
> de IPC e o guard PHP estão sempre ativos. Ver seção 10.

---

## 7. Idiomas

### Estrutura

Crie arquivos JSON em `app/langs/` com o código do idioma:

```
app/langs/
├── en.json    # Inglês (fallback padrão)
├── pt.json    # Português
└── pt-br.json # Português do Brasil
```

### Formato

```json
{
    "Continue": "Continuar",
    "Cancel": "Cancelar",
    "Unable to find file %s": "Não foi possível encontrar o arquivo %s"
}
```

### Cadeia de fallback

Quando um idioma é detectado (ex: `pt-br`), o sistema tenta:

1. `pt-br.json` — correspondência exata
2. `pt.json` — idioma base
3. `en.json` — fallback inglês

### Usando

**PHP:**
```php
$translate = new translate();
echo $translate->get('Continue'); // "Continuar"
```

**JavaScript:**
```javascript
const text = await miphant.translate('Continue');
```

**HTML (via PHP):**
```php
$lang = $_ENV['MIPHANT_LANG'] ?? 'en';
?>
<html lang="<?php echo $lang; ?>">
```

---

## 8. Checklist para Novas Páginas

### Criando uma nova página

- [ ] Criar arquivo PHP em `app/minha-pagina.php`
- [ ] Adicionar `<link rel="stylesheet" href="/style.css">`
- [ ] Definir `<html lang="<?php echo $lang; ?>">`
- [ ] Adicionar link de volta: `<a href="index.php" class="btn btn-outline">← Back</a>`
- [ ] Adicionar ao menu em `app/menus/menu.json`

### Usando MiPhantLibs

- [ ] Incluir arquivos necessários com `require_once`
- [ ] Usar `use` para importar classes
- [ ] Tratar erros com try/catch quando necessário

### Usando Preload API

- [ ] Usar `miphant.*` no JavaScript
- [ ] Usar `async/await` para chamadas que retornam Promise
- [ ] Verificar valores de retorno antes de usar

### Usando Design System

- [ ] Usar classes CSS existentes
- [ ] Seguir padrão dark theme
- [ ] Manter consistência visual

### Segurança

- [ ] Não usar domínios externos como exemplo; links externos abrem no navegador do sistema
- [ ] Não tentar "desligar" a PSK nem editar `app/guard/php-guard.php`
- [ ] Páginas de diagnóstico (`phpinfo` e afins) só quando `dev.tools` estiver ativo

---

## 9. Streaming em Tempo Real

O servidor HTTP do MiPhant encaminha a resposta PHP ao renderer
**incrementalmente** (headers + corpo conforme o script produz),
em vez de esperar o fim do script.

### No PHP

```php
ob_implicit_flush(true);              // despeja cada escrita imediatamente
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache');

for ($i = 1; $i <= 10; $i++) {
    echo "linha {$i}\n";
    flush();                          // obrigatório (ver abaixo)
    sleep(1);
}
```

**Importante:** `flush()` (ou `ob_implicit_flush(true)`) é **obrigatório**.
Com `implicit_flush=Off` e `output_buffering=0` (padrão do MiPhant),
o worker PHP retém a saída até o fim do script se você não chamar `flush()`.

No JavaScript, use `fetch` com leitura incremental:

```js
const response = await fetch('/stream.php');
const reader = response.body.getReader();
const decoder = new TextDecoder();

while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    process(decoder.decode(value, { stream: true }));
}
```

Ou Server-Sent Events com `EventSource` (exemplos completos em
`app/public/streaming.php`, com `stream.php` e `sse.php`).

> `fetch` e `EventSource` usam a mesma origem interna e recebem o header de
> autenticação automaticamente — nada muda no código da página (ver seção 10).

### Limites (config em `server/config.js`)

| Config | Valor | Significado |
|---|---|---|
| `PHP_STREAM_IDLE_TIMEOUT` | 30s | Sem dados por 30s ⇒ stream morto. **Antes dos headers** a resposta é `502`; **depois dos headers** o socket é destruído (o cliente vê truncamento, não um fim "limpo") |
| `PHP_STREAM_MAX_DURATION` | 310s | Teto absoluto de uma resposta (alinhado a `max_execution_time=300`). SSE/streams acima de 5min10 **são encerrados** — não existe stream persistente no MiPhant |
| `PHP_MAX_RESPONSE_SIZE` | 50MB | Teto de bytes por resposta, inclusive em modo stream |
| `PHP_TIMEOUT` | 30s | Somente no caminho legado (`executePhpProtocol` sem `onStdout`) — em produção não é usado |

**Content-Length:** em modo stream o total é desconhecido até o fim,
então o header `Content-Length` vindo do PHP é **descartado** e o Node
usa `Transfer-Encoding: chunked` (válido em HTTP/1.1). Arquivos
estáticos continuam com `Content-Length` normalmente.

### Limitações por plataforma

- **Linux (php-fpm)**: streams concorrentes limitados pelo pool
  (`pm.max_children`, 2–16 workers).
- **Windows (php-cgi.exe)**: processo **sequencial** — um stream aberto
  bloqueia as próximas requisições PHP até ele terminar. Use streams
  curtos no Windows.

---

## 10. Segurança de Runtime (PSK)

O servidor HTTPS do MiPhant escuta em loopback e, sem proteção, qualquer
processo local poderia falar com ele. Por isso **cada execução gera uma chave
pré-compartilhada (PSK) de 256 bits**, só em memória, descartada ao fechar o
app. Nada é gravado em disco, `process.env`, cookies ou `config.json`.

### O que isso muda para quem escreve páginas

**Nada no código.** O Chromium injeta o header `X-MiPhant-Auth`
automaticamente em toda requisição da janela oficial, então continuam
funcionando sem alteração:

- navegação e subrecursos (`<link>`, `<script>`, `<img>`, CSS);
- `fetch`, `XMLHttpRequest`, `EventSource` (SSE) e streams;
- `HEAD` e preflight `OPTIONS`;
- `miphant.*` (todos os handlers IPC validam a origem da página).

O que muda no comportamento:

- **Outros programas** que tentem abrir `https://localhost:<porta>` recebem
  `404` vazio (sem corpo, sem dica de rota).
- **Links externos** são bloqueados na janela e abertos no navegador do
  sistema (ver seção 2).
- **`/phpinfo`** responde `404` fora do modo `dev.tools`.

### Camadas

| Camada | Quem autentica | Mecanismo |
|---|---|---|
| Janela → servidor HTTPS | Chromium | Header `X-MiPhant-Auth` injetado por `session.webRequest.onBeforeSendHeaders`; comparado em tempo constante no gate |
| Servidor Node → PHP | `app/guard/php-guard.php` | HMAC-SHA256 sobre 8 campos + timestamp + nonce, validado via `auto_prepend_file` |

O gate HTTP também valida `Host` exato (`localhost:<porta>` ou
`127.0.0.1:<porta>`), `Origin` quando presente, rejeita
`Sec-Fetch-Site: cross-site`/`same-site` e derruba conexões após excesso de
falhas (requisições autenticadas nunca são limitadas).

### Node → PHP (guard)

- A chave esperada vem do **ambiente do processo PHP**
  (`MIPHANT_GUARD_KEY`), nunca da requisição. Um processo local que fale
  direto com o PHP não consegue assinar a própria requisição.
- O guard remove `MIPHANT_GUARD_KEY` e `MIPHANT_PHP_*` de `$_SERVER`/`$_ENV`
  **antes** do script do usuário rodar — `phpinfo()`, `var_dump($_SERVER)` e
  páginas de debug não veem nada.
- Qualquer falha resulta em `403 Forbidden` sem detalhes (fail closed).
  A única isenção é o SAPI CLI (`cli`, `cli-server`), inalcançável por HTTP.

### Transporte PHP por plataforma

| Plataforma | Transporte | Proteção extra |
|---|---|---|
| Linux | Unix socket `<userData>/server/php/php-fpm.sock` com `listen.mode = 0600` | Permissão de filesystem + guard |
| Windows | TCP `127.0.0.1:<porta efêmera>` | Guard HMAC (php-cgi não tem socket Unix) |

Se o caminho do socket for longo demais (Flatpak/Snap) ou o bind falhar, o
MiPhant cai para TCP e registra aviso — o guard continua ativo.

### Outras proteções

- **Certificate pinning**: `setCertificateVerifyProc` aceita apenas o
  fingerprint SHA-256 do certificado do MiPhant para `localhost`/`127.0.0.1`.
- **Sem corrida de porta**: `listen(0, '127.0.0.1')` e leitura da porta real
  em `server.address()`.
- **IPC**: todos os `ipcMain.handle` verificam `event.senderFrame.url` contra a
  origem interna.
- **Navegação**: `will-navigate` bloqueia destinos externos.

### Limites (o que a PSK não cobre)

| Protege contra | Não protege contra |
|---|---|
| Outros apps/navegadores acessando a porta local | Processo do mesmo usuário lendo memória (`ptrace`, `/proc/<pid>/mem`) |
| Varredura de portas seguida de requisições | DevTools aberto (painel Network mostra o header) |
| CSRF / DNS rebinding vindos de páginas web | Root/malware, swap ou hibernação |
| Acesso direto ao FastCGI/PHP | DoS local esgotando conexões |
| Reutilização da chave após fechar o app | |
| MITM local com CA própria | |

### Regras para desenvolvimento

- **Não** edite `app/guard/php-guard.php` nem as constantes de segurança em
  `server/config.js` para "facilitar" testes.
- **Não** adicione opção em `app/config.json` para desligar a proteção: a
  política é fixa de propósito.
- **Não** exponha a chave no renderer, em logs, em query string ou em cookie.
- Para depurar uma requisição, use `MIPHANT_DEBUG=1` (logs do main) e
  `dev.tools` (DevTools/phpinfo) — nunca imprima a chave.

---

## 11. Licença

Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>.

SPDX-License-Identifier: MIT

**Resumo**: permissão para usar, copiar, modificar, publicar e distribuir,
inclusive comercialmente, desde que o aviso de copyright e a licença sejam
mantidos. O software é fornecido "como está", sem garantias.
