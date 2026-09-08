// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

const fs = require('fs');
const path = require('path');
const { format } = require('util');

module.exports = class milang {
    constructor(platform, dirapp) {
        let aLang = '';

        if (platform == 'linux') {
            // Extrair código completo: "pt_BR.UTF-8" → "pt_BR"
            aLang = (process.env.LANG || process.env.LANGUAGE || process.env.LC_ALL || process.env.LC_MESSAGES).split('.')[0];
        } else {
            // Extrair tag completa: "pt-BR" → "pt-BR"
            const { execFileSync } = require('child_process');
            aLang = String(execFileSync('powershell.exe', ['(Get-WinUserLanguageList)[0].LanguageTag'], { encoding: 'utf-8' })).trim();
        }

        // Normalizar para lowercase com hífen: "pt_BR" → "pt-br", "pt-BR" → "pt-br"
        aLang = aLang.toLowerCase().replace('_', '-');

        process.env.MIPHANT_LANG = aLang;

        // Idioma do App com fallback em cadeia
        this.sLangApp = this.loadLang(dirapp, aLang);
    }

    // ============================================================
    // CARREGAR IDIOMA COM FALLBACK
    //
    // Cadeia: "pt-br" → pt-br.json → pt.json → en.json
    // ============================================================

    loadLang(dirapp, aLang) {
        const langsDir = path.join(dirapp, '/app/langs');

        // 1. Tentar idioma completo: "pt-br.json"
        const fullPath = path.join(langsDir, `${aLang}.json`);
        if (fs.existsSync(fullPath)) {
            return JSON.parse(fs.readFileSync(fullPath, 'utf-8'));
        }

        // 2. Tentar idioma base: "pt.json"
        const base = aLang.split('-')[0];
        if (base !== aLang) {
            const basePath = path.join(langsDir, `${base}.json`);
            if (fs.existsSync(basePath)) {
                return JSON.parse(fs.readFileSync(basePath, 'utf-8'));
            }
        }

        // 3. Fallback para inglês: "en.json"
        const enPath = path.join(langsDir, 'en.json');
        if (fs.existsSync(enPath)) {
            return JSON.parse(fs.readFileSync(enPath, 'utf-8'));
        }

        // 4. Nenhum idioma encontrado
        return [];
    }

    traduzir(texto, ...values) {
        return (this.sLangApp[texto]) ? format(this.sLangApp[texto], ...values) : format(texto, ...values);
    }
}