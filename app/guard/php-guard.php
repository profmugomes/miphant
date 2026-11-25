<?php
// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

// Site: https://www.profmugomes.com.br

/*
 * =============================================================================
 * MiPhant PHP Guard
 * =============================================================================
 *
 * Este arquivo é carregado como `auto_prepend_file` em TODA requisição PHP
 * atendida pelo MiPhant. Ele é a barreira que garante que apenas o servidor
 * Node do MiPhant consiga executar scripts PHP: cada requisição precisa vir
 * assinada com a chave derivada por HKDF no lado Node (HMAC-SHA256 em base64
 * sobre uma mensagem canônica de 8 campos).
 *
 * DESENHO FAIL CLOSED
 * -------------------
 * Qualquer desvio — campo ausente, tipo inesperado, formato inválido, relógio
 * fora da janela, assinatura divergente ou campo canônico ambíguo — resulta em
 * 403 + `exit`. Não existe modo permissivo, constante, variável de ambiente ou
 * flag que desative a verificação em produção. A única isenção é o SAPI de
 * linha de comando (`cli` / `cli-server`), que não é alcançável por HTTP.
 *
 * POR QUE SEM `declare(strict_types=1)`
 * -------------------------------------
 * `declare(strict_types=1)` é uma diretiva de ARQUIVO e, como este arquivo é
 * prependado a cada script do usuário, mantê-la aqui só criaria divergência de
 * expectativas de coerção no código da aplicação, sem ganho de segurança para
 * uma rotina curta de comparação de strings. Por isso usamos `<?php` simples.
 *
 * POR QUE A CHAVE É REMOVIDA DE $_SERVER ANTES DO SCRIPT DO USUÁRIO
 * -----------------------------------------------------------------
 * `phpinfo()`, `var_dump($_SERVER)`, telas de debug, handlers de erro e
 * bibliotecas de terceiros leem `$_SERVER`. Se a chave continuasse lá até o
 * script do usuário rodar, ela apareceria em qualquer página de diagnóstico e
 * ficaria disponível para o próprio código da aplicação. Copiamos os 4 valores
 * para variáveis locais e apagamos as entradas de `$_SERVER` e `$_ENV` antes
 * de qualquer validação — tanto no caminho de sucesso quanto no de falha.
 *
 * NOTA SOBRE ESCOPO
 * -----------------
 * `auto_prepend_file` é incluído no ESCOPO GLOBAL do script principal; toda
 * variável criada aqui ficaria acessível em `$GLOBALS`. Por isso todo o
 * trabalho acontece dentro de uma IIFE (closure estática invocada na hora):
 * as variáveis locais — inclusive a chave crua — morrem no fim da função, e as
 * únicas coisas que sobrevivem são as constantes definidas de propósito.
 * =============================================================================
 */

(static function (): void {
    // -------------------------------------------------------------------------
    // 1) Idempotência: se o guard já rodou nesta requisição, não faz nada.
    //    (protege contra prepend duplicado por configuração equivocada)
    // -------------------------------------------------------------------------
    if (defined('MIPHANT_GUARD_RUN')) {
        return;
    }

    // -------------------------------------------------------------------------
    // 2) Isenção ESTRITA e única: SAPI de linha de comando.
    //    `cli-server` é o servidor embutido (php -S), usado em testes locais.
    //    Não há env/constante/flag de bypass: em produção (fpm-fcgi / cgi-fcgi)
    //    a verificação é sempre obrigatória.
    // -------------------------------------------------------------------------
    $sapi = PHP_SAPI;

    if ($sapi === 'cli' || $sapi === 'cli-server') {
        return;
    }

    // Janela de tolerância do relógio (segundos) entre Node e PHP.
    // A comparação é feita em módulo, então relógio adiantado ou atrasado
    // contam igual.
    if (!defined('MIPHANT_GUARD_CLOCK_SKEW')) {
        define('MIPHANT_GUARD_CLOCK_SKEW', 30);
    }

    // -------------------------------------------------------------------------
    // 3) Chave ESPERADA: vem do ambiente do processo PHP (definida pelo Node
    //    ao iniciar o FPM/CGI), NUNCA da requisicao. E isto que impede um
    //    processo local de assinar a propria requisicao: ele precisaria
    //    conhecer a chave que vive dentro do nosso processo PHP.
    //
    //    Da requisicao vem apenas PROVAS: timestamp, nonce e MAC.
    // -------------------------------------------------------------------------
    $keyHex = getenv('MIPHANT_GUARD_KEY');

    $tsRaw   = is_string($_SERVER['MIPHANT_PHP_TS'] ?? null) ? $_SERVER['MIPHANT_PHP_TS'] : null;
    $nonce   = is_string($_SERVER['MIPHANT_PHP_NONCE'] ?? null) ? $_SERVER['MIPHANT_PHP_NONCE'] : null;
    $authB64 = is_string($_SERVER['MIPHANT_PHP_AUTH'] ?? null) ? $_SERVER['MIPHANT_PHP_AUTH'] : null;

    // -------------------------------------------------------------------------
    // 4) Remoção IMEDIATA dos segredos dos superglobais.
    //    Feita antes de qualquer validação (e portanto antes de qualquer saída
    //    ou `exit`) para que nem `phpinfo()`, nem `$_SERVER`, nem `$_ENV`,
    //    nem um `var_dump` de debug vejam chave, timestamp, nonce ou MAC.
    //    `unset` de índice inexistente é silencioso — nenhum warning.
    // -------------------------------------------------------------------------
    unset(
        $_SERVER['MIPHANT_GUARD_KEY'],
        $_ENV['MIPHANT_GUARD_KEY'],
        $_SERVER['MIPHANT_PHP_TS'],
        $_SERVER['MIPHANT_PHP_NONCE'],
        $_SERVER['MIPHANT_PHP_AUTH'],
        $_ENV['MIPHANT_PHP_TS'],
        $_ENV['MIPHANT_PHP_NONCE'],
        $_ENV['MIPHANT_PHP_AUTH'],
        $_SERVER['MIPHANT_PHP_KEY'],
        $_ENV['MIPHANT_PHP_KEY']
    );

    // -------------------------------------------------------------------------
    // 5) FALHA ÚNICA E SEM DETALHES.
    //    Resposta idêntica para todo tipo de erro: evita que um atacante
    //    descubra QUAL validação falhou (oráculo de diagnóstico). Nada de
    //    mensagens, logs com segredo ou exceções com conteúdo sensível.
    // -------------------------------------------------------------------------
    $forbid = static function (): void {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-store');
        echo 'Forbidden';
        exit;
    };

    // Leitor de campo canônico vindo de $_SERVER:
    //   - ausente  => '' (mesma semântica de `String(valor ?? '')` no Node)
    //   - string   => a própria string
    //   - outro    => null (entrada hostil, rejeitada pelo chamador)
    $field = static function (string $name): ?string {
        if (!array_key_exists($name, $_SERVER)) {
            return '';
        }

        $value = $_SERVER[$name];

        return is_string($value) ? $value : null;
    };

    // -------------------------------------------------------------------------
    // 6) Validações. Ordem: presença/formato -> material criptográfico ->
    //    assinatura. Todas falham para o MESMO 403.
    // -------------------------------------------------------------------------

    // 6.1) REQUEST_METHOD e SCRIPT_NAME precisam existir, ser strings e não
    //      ser vazios: sem eles a mensagem canônica não teria sentido.
    $method     = $_SERVER['REQUEST_METHOD'] ?? null;
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? null;

    if (!is_string($method) || $method === '') {
        $forbid();
    }

    if (!is_string($scriptName) || $scriptName === '') {
        $forbid();
    }

    // 6.2) Chave: exatamente 64 caracteres hex minúsculos (32 bytes).
    //      A regex roda ANTES de hex2bin() justamente para que hex2bin nunca
    //      receba entrada inválida (evita warning "hex2bin(): Input string
    //      must be of even length" e afins).
    if ($keyHex === null || !is_string($keyHex) || preg_match('/^[a-f0-9]{64}$/', $keyHex) !== 1) {
        $forbid();
    }

    $keyRaw = hex2bin($keyHex);

    if (!is_string($keyRaw) || strlen($keyRaw) !== 32) {
        $forbid();
    }

    // 6.3) Timestamp: só dígitos, com tamanho limitado para que (int) não
    //      sature em PHP_INT_MAX e produza uma janela falsa.
    if ($tsRaw === null || strlen($tsRaw) > 12 || ctype_digit($tsRaw) !== true) {
        $forbid();
    }

    $ts = (int) $tsRaw;

    if (abs(time() - $ts) > MIPHANT_GUARD_CLOCK_SKEW) {
        $forbid();
    }

    // 6.4) Nonce: 32 caracteres hex minúsculos (16 bytes aleatórios).
    if ($nonce === null || preg_match('/^[a-f0-9]{32}$/', $nonce) !== 1) {
        $forbid();
    }

    // 6.5) Campos canônicos restantes. Ausentes viram ''; qualquer tipo que não
    //      seja string (array injetado) é rejeitado.
    $requestUri    = $field('REQUEST_URI');
    $queryString   = $field('QUERY_STRING');
    $contentLength = $field('CONTENT_LENGTH');
    $contentType   = $field('CONTENT_TYPE');

    if ($requestUri === null || $queryString === null || $contentLength === null || $contentType === null) {
        $forbid();
    }

    // 6.6) Ambiguidade de MAC: se qualquer campo contiver LF ou NUL, campos
    //      diferentes poderiam gerar a MESMA string canônica. Rejeitamos em vez
    //      de escapar, porque o Node nunca envia esses bytes.
    $canonicalFields = [
        $method,
        $scriptName,
        $requestUri,
        $queryString,
        $contentLength,
        $contentType,
        $tsRaw,
        $nonce,
    ];

    foreach ($canonicalFields as $canonicalField) {
        if (strpos($canonicalField, "\n") !== false || strpos($canonicalField, "\0") !== false) {
            $forbid();
        }
    }

    // 6.7) MAC: precisa existir, ser base64 estrito e decodificar em 32 bytes
    //      (tamanho fixo do HMAC-SHA256).
    if ($authB64 === null || $authB64 === '') {
        $forbid();
    }

    $authRaw = base64_decode($authB64, true);

    if (!is_string($authRaw) || strlen($authRaw) !== 32) {
        $forbid();
    }

    // Mensagem canônica: exatamente os 8 campos na ordem do protocolo,
    // unidos por LF, sem escape adicional.
    $canonical = implode("\n", $canonicalFields);

    // Mesma conta do Node:
    //   crypto.createHmac('sha256', phpKey).update(canonical, 'utf8').digest('base64')
    $expected = base64_encode(hash_hmac('sha256', $canonical, $keyRaw, true));

    // Comparação em tempo constante sobre a representação base64. Se qualquer
    // validação anterior tivesse sido pulada, esta sozinha ainda barra a
    // requisição — é a segunda camada do fail closed.
    if (!hash_equals($expected, $authB64)) {
        $forbid();
    }

    // -------------------------------------------------------------------------
    // 7) SUCESSO: marca a execução e devolve o controle sem emitir UM byte.
    //    Nenhum echo/print/aviso aqui — o script do usuário começa a resposta.
    //    Mesmo dentro da IIFE, apagamos a chave crua: não há motivo para ela
    //    continuar viva em memória depois do HMAC.
    // -------------------------------------------------------------------------
    $keyRaw = '';
    $keyHex = '';

    define('MIPHANT_GUARD_RUN', true);
})();
