// Copyright (C) 2025-2026 Murilo Gomes <profmugomes.com.br>
// SPDX-License-Identifier: MIT

'use strict';

const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

const { exists } = require('./utils');
const { log: loggerLog } = require('./logger');

// ============================================================
// ASN.1 / DER
//
// Implementacao minima necessaria para criar X.509.
// ============================================================

function derLength(length) {
    if (length < 128) {
        return Buffer.from([length]);
    }

    const bytes = [];
    let value = length;

    while (value > 0) {
        bytes.unshift(value & 0xff);
        value >>= 8;
    }

    return Buffer.from([0x80 | bytes.length, ...bytes]);
}

function der(tag, content) {
    if (!Buffer.isBuffer(content)) {
        content = Buffer.from(content);
    }

    return Buffer.concat([
        Buffer.from([tag]),
        derLength(content.length),
        content
    ]);
}

function derSequence(...items) {
    return der(0x30, Buffer.concat(items));
}

function derSet(...items) {
    return der(0x31, Buffer.concat(items));
}

function derInteger(buffer) {
    if (!Buffer.isBuffer(buffer)) {
        buffer = Buffer.from(buffer);
    }

    let value = Buffer.from(buffer);

    while (value.length > 1 && value[0] === 0) {
        value = value.subarray(1);
    }

    if (value[0] & 0x80) {
        value = Buffer.concat([Buffer.from([0]), value]);
    }

    return der(0x02, value);
}

function derIntegerNumber(number) {
    if (number === 0) {
        return derInteger(Buffer.from([0]));
    }

    let value = number;
    const bytes = [];

    while (value > 0) {
        bytes.unshift(value & 0xff);
        value = Math.floor(value / 256);
    }

    return derInteger(Buffer.from(bytes));
}

function derNull() {
    return Buffer.from([0x05, 0x00]);
}

function derOID(oid) {
    const parts = oid.split('.').map(Number);
    const first = (parts[0] * 40) + parts[1];
    const bytes = [first];

    for (let i = 2; i < parts.length; i++) {
        let value = parts[i];
        const encoded = [value & 0x7f];
        value >>= 7;

        while (value > 0) {
            encoded.unshift(0x80 | (value & 0x7f));
            value >>= 7;
        }

        bytes.push(...encoded);
    }

    return der(0x06, Buffer.from(bytes));
}

function derUTF8String(value) {
    return der(0x0c, Buffer.from(value, 'utf8'));
}

function derPrintableString(value) {
    return der(0x13, Buffer.from(value, 'ascii'));
}

function derUTCTime(date) {
    const year = date.getUTCFullYear();
    const yy = String(year % 100).padStart(2, '0');
    const month = String(date.getUTCMonth() + 1).padStart(2, '0');
    const day = String(date.getUTCDate()).padStart(2, '0');
    const hour = String(date.getUTCHours()).padStart(2, '0');
    const minute = String(date.getUTCMinutes()).padStart(2, '0');
    const second = String(date.getUTCSeconds()).padStart(2, '0');

    return der(
        0x17,
        Buffer.from(`${yy}${month}${day}${hour}${minute}${second}Z`, 'ascii')
    );
}

function derBitString(buffer) {
    return der(0x03, Buffer.concat([Buffer.from([0]), buffer]));
}

function derOctetString(buffer) {
    return der(0x04, buffer);
}

function derContext(tagNumber, content, constructed = true) {
    const tag = (constructed ? 0xa0 : 0x80) | tagNumber;
    return der(tag, content);
}

// ============================================================
// X.509
// ============================================================

function createName(commonName) {
    const oidCommonName = derOID('2.5.4.3');
    const attribute = derSequence(oidCommonName, derUTF8String(commonName));
    const relativeName = derSet(attribute);
    return derSequence(relativeName);
}

function createAlgorithmIdentifier() {
    return derSequence(
        derOID('1.2.840.113549.1.1.11'),
        derNull()
    );
}

function createPublicKeyInfo(publicKey) {
    return publicKey.export({ type: 'spki', format: 'der' });
}

function createSubjectAlternativeName() {
    const dnsLocalhost = der(0x82, Buffer.from('localhost', 'ascii'));
    const ipv4 = der(0x87, Buffer.from([127, 0, 0, 1]));
    const ipv6 = der(0x87, Buffer.from([
        0x00, 0x00, 0x00, 0x00,
        0x00, 0x00, 0x00, 0x00,
        0x00, 0x00, 0x00, 0x00,
        0x00, 0x00, 0x00, 0x01
    ]));

    return derSequence(dnsLocalhost, ipv4, ipv6);
}

function createExtensions() {
    const san = createSubjectAlternativeName();

    const subjectAlternativeName = derSequence(
        derOID('2.5.29.17'),
        derOctetString(san)
    );

    const basicConstraints = derSequence(
        derOID('2.5.29.19'),
        derOctetString(derSequence())
    );

    return derSequence(subjectAlternativeName, basicConstraints);
}

// ============================================================
// CRIAR CERTIFICADO AUTOASSINADO
// ============================================================

function createSelfSignedCertificate(certificateDirectory) {
    const keyFile = path.join(certificateDirectory, 'server.key');
    const certFile = path.join(certificateDirectory, 'server.crt');

    // Reutiliza certificado existente
    if (exists(keyFile) && exists(certFile)) {
        loggerLog('[HTTPS] Usando certificado existente.');
        return { key: keyFile, cert: certFile };
    }

    loggerLog('[HTTPS] Gerando chave RSA...');

    const { publicKey, privateKey } = crypto.generateKeyPairSync('rsa', {
        modulusLength: 2048,
        publicExponent: 0x10001,
        publicKeyEncoding: { type: 'spki', format: 'pem' },
        privateKeyEncoding: { type: 'pkcs8', format: 'pem' }
    });

    const privateKeyObject = crypto.createPrivateKey(privateKey);
    const publicKeyObject = crypto.createPublicKey(publicKey);

    // Serial
    let serial = crypto.randomBytes(16);
    serial[0] &= 0x7f;

    if (serial.every(byte => byte === 0)) {
        serial[15] = 1;
    }

    // Datas
    const notBefore = new Date();
    notBefore.setUTCMinutes(notBefore.getUTCMinutes() - 5);

    const notAfter = new Date();
    notAfter.setUTCFullYear(notAfter.getUTCFullYear() + 2);

    // TBSCertificate
    const tbsCertificate = derSequence(
        derContext(0, derIntegerNumber(2)),
        derInteger(serial),
        createAlgorithmIdentifier(),
        createName('localhost'),
        derSequence(derUTCTime(notBefore), derUTCTime(notAfter)),
        createName('localhost'),
        createPublicKeyInfo(publicKeyObject),
        derContext(3, createExtensions())
    );

    // Assinatura
    const signature = crypto.sign('sha256', tbsCertificate, privateKeyObject);

    // Certificate
    const certificate = derSequence(
        tbsCertificate,
        createAlgorithmIdentifier(),
        derBitString(signature)
    );

    // PEM
    const certificateBase64 = certificate
        .toString('base64')
        .match(/.{1,64}/g)
        .join('\n');

    const certificatePem =
        '-----BEGIN CERTIFICATE-----\n' +
        certificateBase64 +
        '\n' +
        '-----END CERTIFICATE-----\n';

    // Grava arquivos
    fs.writeFileSync(keyFile, privateKey, { mode: 0o600 });
    fs.writeFileSync(certFile, certificatePem, { mode: 0o644 });

    loggerLog('[HTTPS] Certificado criado.');

    return { key: keyFile, cert: certFile };
}

// ============================================================
// VALIDAR CERTIFICADO
// ============================================================

function validateCertificate(certFile) {
    try {
        const certificate = new crypto.X509Certificate(
            fs.readFileSync(certFile)
        );

        console.log('[HTTPS] Subject:', certificate.subject);
        console.log('[HTTPS] Issuer:', certificate.issuer);
        console.log('[HTTPS] Valid from:', certificate.validFrom);
        console.log('[HTTPS] Valid to:', certificate.validTo);
        console.log('[HTTPS] Fingerprint:', certificate.fingerprint256);

        return true;
    } catch (error) {
        console.error('[HTTPS] Certificado invalido:', error);
        return false;
    }
}

// ============================================================
// EXPORTS
// ============================================================

module.exports = {
    createSelfSignedCertificate,
    validateCertificate
};
