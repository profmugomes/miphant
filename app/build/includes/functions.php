<?php
// Copyright (c) 2025-2026 Murilo Gomes <profmugomes.com.br>. All Rights Reserved.
// Licensed under the PolyForm Perimeter License 1.0.1.
// See LICENSE.md for details.

function excluirRecursivamente(string $diretorio): bool
{
    $arquivos = scandir($diretorio);

    foreach ($arquivos as $arquivo) {
        if ($arquivo !== '.' && $arquivo !== '..') {
            if (is_dir($diretorio . '/' . $arquivo)) {
                excluirRecursivamente($diretorio . '/' . $arquivo . '/');
            } else {
                unlink($diretorio . '/' . $arquivo);
            }
        }
    }

    return rmdir($diretorio . '/');
}

function downloadFile($url, $filename)
{
    $url = $url;
    $saveTo = $filename;

    $fp = fopen($saveTo, 'w+');
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_FAILONERROR, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MiPhantDownloader/1.0');

    if (!curl_exec($ch)) {
        $err = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        exit('Erro: ' . $err);
    }
    curl_close($ch);
    fclose($fp);
}

function extractFile($pathFile)
{
    global $newLine;

    $zip = new ZipArchive;
    if ($zip->open($pathFile . 'miphant.zip') === TRUE) {
        $zip->extractTo($pathFile);
        $zip->close();

        $entries = array_diff(scandir($pathFile), ['.', '..']); // lista arquivos/pastas
        $subfolder = '';
        foreach ($entries as $entry) {
            if (is_dir($pathFile . $entry)) {
                $subfolder = $entry;
                break; // Pega a primeira pasta encontrada
            }
        }

        if ($subfolder) {
            rename($pathFile . $subfolder, $pathFile . getConfig('app', 'id') . '/');
        }
    } else {
        echo translate('Could not open ZIP file.') . $newLine;
    }
}

// Copy Folder
function copyFolder($source, $dest)
{
    if (!is_dir($source)) return false;

    @mkdir($dest, 0755, true);

    $items = scandir($source);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;

        $srcPath = $source . '/' . $item;
        $destPath = $dest . '/' . $item;

        if (is_dir($srcPath)) {
            copyFolder($srcPath, $destPath);
        } else {
            copy($srcPath, $destPath);
        }
    }
    return true;
}

// Zip
function addFolderToZip($folder, $zip, $parentFolder = '')
{
    $handle = opendir($folder);
    while (($file = readdir($handle)) !== false) {
        if ($file === '.' || $file === '..') continue;

        $fullPath = $folder . '/' . $file;
        $localPath = $parentFolder ? $parentFolder . '/' . $file : $file;

        if (is_dir($fullPath)) {
            addFolderToZip($fullPath, $zip, $localPath); // recurs�o
        } else {
            $zip->addFile($fullPath, $localPath);
        }
    }
    closedir($handle);
}

function zipFolder($source, $zipFile)
{
    if (!is_dir($source)) return false;

    $zip = new ZipArchive();
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        return false;
    }

    addFolderToZip($source, $zip);
    $zip->close();

    return file_exists($zipFile);
}

$MIPHANT_CONFIG = json_decode(file_get_contents(dirname(__FILE__, 3) . '/config.json'), true);
function getConfig(string ...$nomes): string|int|bool
{
    global $MIPHANT_CONFIG;

    $sValor = $MIPHANT_CONFIG;

    foreach ($nomes as $value) {
        $sValor = (empty($sValor[$value])) ? '' : $sValor[$value];
    }

    return $sValor;
}
