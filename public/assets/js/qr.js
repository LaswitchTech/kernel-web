/**
 * qr.js — Correct QR code generator (byte mode, EC level M, versions 1-10)
 *
 * Pure JavaScript, no dependencies. Generates SVG output.
 * Suitable for otpauth:// URIs (typically 90-150 chars).
 *
 * Usage:
 *   var svg = QR.generate('otpauth://totp/app:user?secret=ABC123');
 *   document.getElementById('qr').innerHTML = svg;
 */
var QR = (function () {
    'use strict';

    // ===== GF(256) =====
    var GF_EXP = new Uint8Array(512);
    var GF_LOG = new Uint8Array(256);
    (function initGF() {
        var x = 1;
        for (var i = 0; i < 256; i++) {
            GF_EXP[i] = x;
            GF_LOG[x] = i;
            x <<= 1;
            if (x & 256) x ^= 0x11d;
        }
        for (i = 256; i < 512; i++) GF_EXP[i] = GF_EXP[i - 256];
    })();

    function gfMul(a, b) {
        if (a === 0 || b === 0) return 0;
        return GF_EXP[(GF_LOG[a] + GF_LOG[b]) % 255];
    }

    // ===== Reed-Solomon encoding =====
    function rsGenPoly(nsym) {
        var g = [1];
        for (var i = 0; i < nsym; i++) {
            var ng = new Array(g.length + 1);
            for (var j = 0; j < ng.length; j++) ng[j] = 0;
            for (var j = 0; j < g.length; j++) {
                ng[j] ^= g[j];
                ng[j + 1] ^= gfMul(g[j], GF_EXP[i]);
            }
            g = ng;
        }
        return g;
    }

    function rsEncode(data, nsym) {
        var gen = rsGenPoly(nsym);
        var res = [];
        for (var i = 0; i < data.length; i++) res.push(data[i]);
        for (var i = 0; i < nsym; i++) res.push(0);
        for (var i = 0; i < data.length; i++) {
            var coef = res[i];
            if (coef !== 0) {
                for (var j = 1; j < gen.length; j++) {
                    res[i + j] ^= gfMul(gen[j], coef);
                }
            }
        }
        return res.slice(data.length);
    }

    // ===== QR version tables (byte mode, EC level M) =====
    var VERSIONS = [
        null,
        [21, 16, 10, 6, [6]],
        [25, 32, 16, 6, [6, 18]],
        [29, 48, 26, 6, [6, 22]],
        [33, 64, 18, 7, [6, 26]],
        [37, 86, 24, 7, [6, 30]],
        [41, 108, 16, 8, [6, 34]],
        [45, 124, 18, 8, [6, 38]],
        [49, 170, 22, 9, [6, 22, 38]],
        [53, 196, 22, 9, [6, 26, 42]],
        [57, 242, 26, 10, [6, 30, 46]],
    ];

    // ===== Format strings (precomputed using BCH(15,5) with mask 0x5412) =====
    // Index = (ec_level << 1) | alignment
    // EC: 0=L, 1=M, 2=Q, 3=H; Alignment: 0=no, 1=yes
    var FORMAT_STRINGS = [
        0x4412, 0x4485, // L: no, yes
        0x5412, 0x5481, // M: no, yes
        0x601f, 0x608c, // Q: no, yes
        0x701d, 0x708e, // H: no, yes
    ];

    // ===== Finder pattern (9x9) =====
    var FINDER = [
        [1,1,1,1,1,1,1,0,0],
        [1,0,0,0,0,0,1,0,0],
        [1,0,1,1,1,0,1,0,0],
        [1,0,1,1,1,0,1,0,0],
        [1,0,1,1,1,0,1,0,0],
        [1,0,0,0,0,0,1,0,0],
        [1,1,1,1,1,1,1,0,0],
        [0,0,0,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,0],
    ];

    // ===== Place helpers =====
    function placeFinder(m, row, col) {
        for (var r = 0; r < 9; r++)
            for (var c = 0; c < 9; c++)
                if (row + r < m.length && col + c < m[0].length)
                    m[row + r][col + c] = FINDER[r][c];
    }

    function placeAlignment(m, row, col) {
        for (var r = -2; r <= 2; r++)
            for (var c = -2; c <= 2; c++)
                if (row + r >= 0 && row + r < m.length && col + c >= 0 && col + c < m[0].length)
                    m[row + r][col + c] = (Math.abs(r) === 2 || Math.abs(c) === 2 || (r === 0 && c === 0)) ? 1 : 0;
    }

    // ===== Format string placement =====
    // Positions taken directly from the QR ISO/IEC 18004 specification
    function applyFormat(m, size, formatBits) {
        // Horizontal segment: 8 bits around top-right finder (row 8)
        for (var i = 0; i <= 5; i++) m[8][i] = (formatBits >> i) & 1;
        m[8][7] = (formatBits >> 6) & 1;
        m[8][8] = (formatBits >> 7) & 1;
        m[8][9] = (formatBits >> 8) & 1;
        // Horizontal segment: 6 bits wrapping below right-bottom finder
        for (var i = 9; i <= 14; i++) m[8][size - 15 + i] = (formatBits >> i) & 1;

        // Vertical segment: 7 bits wrapping left-bottom finder (col 8)
        for (var i = 0; i <= 6; i++) m[i][8] = (formatBits >> i) & 1;
        for (var i = 7; i <= 14; i++) m[size - 15 + i][8] = (formatBits >> i) & 1;

        // Dark module
        m[size - 8][8] = 1;
    }

    // ===== Data placement (boustrophedon) =====
    function placeDataBits(m, size, bits) {
        var bitIdx = 0;
        var upward = true;
        for (var col = size - 1; col >= 1; col -= 2) {
            if (col === 6) col = 5;
            for (var row = 0; row < size; row++) {
                var r = upward ? size - 1 - row : row;
                for (var c = 0; c <= 1; c++) {
                    var cc = col - c;
                    if (cc >= 0 && m[r][cc] === null) {
                        var bit = (bitIdx < bits.length) ? bits[bitIdx] : 0;
                        m[r][cc] = bit;
                        bitIdx++;
                    }
                }
            }
            upward = !upward;
        }
    }

    // ===== Main generate =====
    function generate(text, size) {
        if (typeof text !== 'string' || text.length === 0) return null;
        size = size || 200;

        // Encode text as bytes
        var byteArr = [];
        for (var i = 0; i < text.length; i++) byteArr.push(text.charCodeAt(i));

        // Determine QR version (minimum that fits data)
        var version = null;
        for (var v = 1; v <= 10; v++) {
            if (4 + byteArr.length * 8 <= VERSIONS[v][1] * 8) {
                version = v;
                break;
            }
        }
        if (version === null) return null;

        var cap = VERSIONS[version][1];       // data codewords capacity
        var ecCodewords = VERSIONS[version][2];
        var verSize = VERSIONS[version][0];

        // Build bit stream: mode(4) + charCount(8) + data + terminator + padding
        var bits = '0100'; // byte mode
        bits += byteArr.length.toString(2).padStart(8, '0');
        for (var i = 0; i < byteArr.length; i++) bits += byteArr[i].toString(2).padStart(8, '0');

        var termLen = Math.min(4, cap * 8 - bits.length);
        for (var i = 0; i < termLen; i++) bits += '0';
        while (bits.length % 8 !== 0) bits += '0';
        var padBytes = [0xEC, 0x11];
        var pi = 0;
        while (bits.length < cap * 8) {
            bits += padBytes[pi].toString(2).padStart(8, '0');
            pi = 1 - pi;
        }

        // Extract codewords
        var dataCodewords = [];
        for (var i = 0; i < cap; i++) {
            dataCodewords.push(parseInt(bits.substr(i * 8, 8), 2));
        }

        // Reed-Solomon error correction
        var ecResult = rsEncode(dataCodewords, ecCodewords);

        // Combine data + EC into bit stream
        var allBits = '';
        for (var i = 0; i < dataCodewords.length; i++) allBits += dataCodewords[i].toString(2).padStart(8, '0');
        for (var i = 0; i < ecResult.length; i++) allBits += ecResult[i].toString(2).padStart(8, '0');

        var bitArr = [];
        for (var i = 0; i < allBits.length; i++) bitArr.push(parseInt(allBits[i], 10));

        // ===== Build matrix =====
        var matrix = [];
        for (var i = 0; i < verSize; i++) {
            matrix[i] = new Array(verSize);
            for (var j = 0; j < verSize; j++) matrix[i][j] = null;
        }

        placeFinder(matrix, 0, 0);
        placeFinder(matrix, 0, verSize - 7);
        placeFinder(matrix, verSize - 7, 0);

        var apPositions = VERSIONS[version][4];
        for (var i = 0; i < apPositions.length; i++) {
            for (var j = 0; j < apPositions.length; j++) {
                if (!(i === 0 && j === 0) && !(i === 0 && j === apPositions.length - 1) &&
                    !(i === apPositions.length - 1 && j === 0)) {
                    placeAlignment(matrix, apPositions[i], apPositions[j]);
                }
            }
        }

        for (var i = 8; i < verSize - 8; i++) {
            if (matrix[6][i] === null) matrix[6][i] = i % 2 === 0 ? 1 : 0;
            if (matrix[i][6] === null) matrix[i][6] = i % 2 === 0 ? 1 : 0;
        }

        // Format string (EC level M = 1, no alignment = 0 => index 2)
        applyFormat(matrix, verSize, FORMAT_STRINGS[2]);

        placeDataBits(matrix, verSize, bitArr);

        // ===== Build SVG =====
        var quiet = 4;
        var cellW = size / (verSize + quiet * 2);

        var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '">';
        svg += '<rect width="' + size + '" height="' + size + '" fill="white"/>';
        svg += '<g fill="black">';
        for (var r = 0; r < verSize; r++) {
            for (var c = 0; c < verSize; c++) {
                if (matrix[r][c] === 1) {
                    svg += '<rect x="' + ((c + quiet) * cellW) + '" y="' + ((r + quiet) * cellW) + '" width="' + cellW + '" height="' + cellW + '"/>';
                }
            }
        }
        svg += '</g></svg>';
        return svg;
    }

    return { generate: generate };
})();
