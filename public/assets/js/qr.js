/**
 * qr.js — Minimal QR code generator (QR 1–10, up to 120 chars)
 *
 * Pure JavaScript, no dependencies. Generates SVG output.
 * Suitable for otpauth:// URIs (typically 90–150 chars).
 *
 * Usage:
 *   var svg = QR.generate('otpauth://totp/app:user?secret=ABC123');
 *   document.getElementById('qr').innerHTML = svg;
 */
var QR = (function () {
    'use strict';

    // ── GF(256) multiplication table ──
    var GF256 = [];
    var GF_LOG = [];
    var GF_EXP = [];
    (function () {
        var x = 1;
        for (var i = 0; i < 256; i++) {
            GF_EXP[i] = x;
            GF_LOG[x] = i;
            x <<= 1;
            if (x & 256) x ^= 0x11d;
        }
        // Extend exp table for multiplication
        for (i = 256; i < 512; i++) GF_EXP[i] = GF_EXP[i - 256];
    })();

    function gfMul(a, b) {
        if (a === 0 || b === 0) return 0;
        return GF_EXP[(GF_LOG[a] + GF_LOG[b]) % 255];
    }

    // ── Reed-Solomon encoding ──
    function rsGenPoly(nsym) {
        var g = [1];
        for (var i = 0; i < nsym; i++) {
            var ng = new Array(g.length + 1).fill(0);
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
        var res = data.slice(0);
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

    // ── QR data capacity tables (version 1–10, 8-bit bytes, EC level M) ──
    // [totalDataCodewords, alignmentPatternStart, alignmentPatterns, ecCodewordsPerBlock, dataCapacity, ...]
    var VERSIONS = [
        null,
        // ver 1: 20×20, cap=16, apStart=null (none)
        [26, null, [], 1, 16, 1],
        // ver 2: 25×25, cap=44, apStart=6
        [44, [6], [6], 1, 44, 2],
        // ver 3: 29×29, cap=70, apStart=6
        [70, [6], [6, 18], 1, 70, 3],
        // ver 4: 33×33, cap=100, apStart=7
        [100, [7], [6, 22], 1, 100, 4],
        // ver 5: 37×37, cap=132, apStart=7
        [132, [7], [6, 26], 1, 132, 5],
        // ver 6: 41×41, cap=176, apStart=8
        [176, [8], [6, 30], 2, 176, 6],
        // ver 7: 45×45, cap=216, apStart=8
        [216, [8], [6, 34], 2, 216, 7],
        // ver 8: 49×49, cap=260, apStart=9
        [260, [9], [6, 22, 38], 2, 260, 8],
        // ver 9: 53×53, cap=310, apStart=9
        [310, [9], [6, 26, 42], 2, 310, 9],
        // ver 10: 57×57, cap=364, apStart=10
        [364, [10], [6, 30, 46], 2, 364, 10],
    ];

    // ── Determine version needed for data length ──
    function getVersionForLength(length) {
        for (var v = 1; v <= 10; v++) {
            if (length <= VERSIONS[v][5]) return v;
        }
        return null;
    }

    // ── Encode data ──
    function encodeData(charData, version) {
        var cap = VERSIONS[version][5];
        // byte mode: 4 bit header + 8 bits per char
        var headerBits = 4 + charData.length * 8;
        var totalBits = headerBits;
        if (totalBits > cap * 8) return null;

        // Build bit string
        var bits = '0100';
        for (var i = 0; i < charData.length; i++) {
            bits += charData[i].toString(2).padStart(8, '0');
        }
        // Terminator
        var termLen = Math.min(4, cap * 8 - bits.length);
        bits += '0'.repeat(termLen);
        // Pad to byte boundary
        while (bits.length % 8 !== 0) bits += '0';
        // Data codewords
        var codewords = [];
        for (var i = 0; i < cap; i++) {
            codewords.push(parseInt(bits.substr(i * 8, 8), 2));
            if (bits.length <= (i + 1) * 8) {
                // Pad codewords
                codewords.push(i % 2 === 0 ? 0xEC : 0x11);
            }
        }
        return { codewords: codewords.slice(0, cap), bits: bits };
    }

    // ── Place finder patterns ──
    var FINDER = [
        [1,1,1,1,1,1,1,0,0,0,0,0,0,1,0,0,0,1,0,0],
        [1,0,0,0,0,0,0,0,1,0,1,0,1,0,1,0,0,0,1,0],
        [1,0,1,1,1,1,1,0,0,0,1,0,1,0,0,0,0,0,1,0],
        [1,0,1,1,1,1,1,0,0,1,1,0,1,0,1,0,1,0,1,0],
        [1,0,1,1,1,1,1,0,0,0,1,0,1,0,0,0,1,0,0,0],
        [1,0,1,1,1,1,1,0,0,1,1,0,1,0,1,0,1,0,1,0],
        [1,0,1,1,1,1,1,0,0,0,0,0,0,1,0,0,0,0,0,0],
        [0,0,0,0,0,0,0,0,1,0,1,0,1,0,1,0,0,0,0,0],
        [0,1,0,1,1,0,1,0,0,0,1,0,0,0,1,0,1,0,1,0],
        [0,0,0,1,1,0,1,0,1,0,1,0,1,0,1,0,0,0,1,0],
        [1,0,1,1,1,0,1,0,0,0,1,0,0,0,0,0,1,0,1,0],
        [0,1,0,1,1,0,1,0,1,0,1,0,1,0,1,0,1,0,0,0],
        [1,0,0,0,0,0,0,0,0,0,1,0,0,0,1,0,0,0,1,0],
        [1,0,1,0,1,0,1,0,1,0,0,0,0,1,0,0,1,0,1,0],
        [1,0,0,0,0,0,0,0,0,1,1,0,1,0,1,0,0,1,0,0],
        [1,1,1,1,1,1,1,0,0,0,1,0,1,0,0,0,1,0,1,0],
        [0,0,0,0,0,0,0,0,0,1,1,0,0,0,1,0,0,0,1,0],
        [1,1,1,1,1,1,1,0,0,0,1,0,1,0,1,0,1,0,0,0],
        [1,0,1,1,1,1,1,0,0,1,1,0,0,0,0,0,1,0,1,0],
        [1,0,0,0,0,0,0,0,0,1,1,0,1,0,1,0,0,1,0,0],
    ];

    // ── Build matrix ──
    function buildMatrix(version) {
        var size = version * 4 + 17;
        var matrix = [];
        for (var i = 0; i < size; i++) {
            matrix[i] = new Array(size).fill(null);
        }
        return { matrix: matrix, size: size, reserved: [] };
    }

    function placeFinder(m, row, col) {
        for (var r = -1; r <= 7; r++) {
            for (var c = -1; c <= 7; c++) {
                var mr = row + r, mc = col + c;
                if (mr < 0 || mr >= m.size || mc < 0 || mc >= m.size) continue;
                m.matrix[mr][mc] = FINDER[r + 1][c + 1];
                m.reserved[mr] = m.reserved[mr] || [];
                m.reserved[mr][mc] = true;
            }
        }
    }

    function placeAlignment(m, row, col) {
        for (var r = -2; r <= 2; r++) {
            for (var c = -2; c <= 2; c++) {
                m.matrix[row + r][col + c] = (Math.abs(r) === 2 || Math.abs(c) === 2 || r === 0 && c === 0) ? 1 : 0;
                m.reserved[row + r] = m.reserved[row + r] || [];
                m.reserved[row + r][col + c] = true;
            }
        }
    }

    function placeTiming(m) {
        for (var i = 8; i < m.size - 8; i++) {
            if (!m.reserved[6] || m.reserved[6][i] === undefined) {
                m.matrix[6][i] = i % 2 === 0 ? 1 : 0;
                m.reserved[6] = m.reserved[6] || [];
                m.reserved[6][i] = true;
            }
            if (!m.reserved[i] || m.reserved[i][6] === undefined) {
                m.matrix[i][6] = i % 2 === 0 ? 1 : 0;
                m.reserved[i] = m.reserved[i] || [];
                m.reserved[i][6] = true;
            }
        }
    }

    function reserveFormat(m) {
        for (var i = 0; i <= 8; i++) {
            if (!m.reserved[8]) m.reserved[8] = [];
            if (!m.reserved[i]) m.reserved[i] = [];
            if (i < m.size) { m.reserved[8][i] = true; m.reserved[i][8] = true; }
        }
        // Dark module
        m.matrix[m.size - 8][8] = 1;
        m.reserved[m.size - 8] = m.reserved[m.size - 8] || [];
        m.reserved[m.size - 8][8] = true;
    }

    // ── Place data bits ──
    function placeData(m, dataBits) {
        var size = m.size;
        var bitIdx = 0;
        var upward = true;
        for (var col = size - 1; col >= 1; col -= 2) {
            if (col === 6) col = 5; // Skip timing column
            for (var row = 0; row < size; row++) {
                var r = upward ? size - 1 - row : row;
                for (var c = 0; c <= 1; c++) {
                    var cc = col - c;
                    if (cc >= 0 && (!m.reserved[r] || !m.reserved[r][cc])) {
                        var bit = bitIdx < dataBits.length ? parseInt(dataBits[bitIdx], 10) : 0;
                        m.matrix[r][cc] = bit;
                        bitIdx++;
                    }
                }
            }
            upward = !upward;
        }
    }

    // ── Format string ──
    var FORMAT_BITS = [
        0x5412, 0x5125, 0x5E7C, 0x5B4B, 0x45F9, 0x40CE, 0x4F97, 0x4AA0,
        0x77C4, 0x72F3, 0x7DAA, 0x789D, 0x662F, 0x6318, 0x6C41, 0x6976,
        0x1689, 0x13BE, 0x1CE7, 0x19D0, 0x0762, 0x0255, 0x0D0C, 0x083B,
        0x355F, 0x3068, 0x3F31, 0x3A06, 0x24B4, 0x2183, 0x2EDA, 0x2BED,
    ];

    function applyFormat(m, ecLevel) {
        var formatBits = FORMAT_BITS[ecLevel << 3]; // EC level M = 1
        var size = m.size;
        // Place around finder patterns
        for (var i = 0; i < 15; i++) {
            var bit = (formatBits >> i) & 1;
            var r, c;
            if (i < 6) { r = 8; c = i; }
            else if (i < 8) { r = 8; c = i; }
            else if (i < 9) { r = 8; c = i - 1; }
            else { r = 8; c = size - 15 + i - 9; }
            if (!m.reserved[r] || !m.reserved[r][c]) m.matrix[r][c] = bit;

            r = size - 15 + i - 9;
            c = 8;
            if (!m.reserved[r] || !m.reserved[r][c]) m.matrix[r][c] = bit;
        }
        // Vertical
        for (i = 0; i < 8; i++) {
            var br = size - 1 - i, bc = 8;
            var bit2 = (formatBits >> i) & 1;
            if (!m.reserved[br] || !m.reserved[br][bc]) m.matrix[br][bc] = bit2;
        }
        for (i = 0; i < 7; i++) {
            var br2 = 8, bc2 = i;
            var bit3 = (formatBits >> (i + 8)) & 1;
            if (!m.reserved[br2] || !m.reserved[br2][bc2]) m.matrix[br2][bc2] = bit3;
        }
    }

    // ── Generate QR SVG ──
    function generate(text, size) {
        size = size || 200;
        var byteArr = [];
        for (var i = 0; i < text.length; i++) byteArr.push(text.charCodeAt(i));

        var version = getVersionForLength(byteArr.length);
        if (version === null) return '<svg>' + text + '</svg>'; // fallback

        var ecLevel = 1; // M
        var dataResult = encodeData(byteArr, version);
        if (!dataResult) return null;

        var m = buildMatrix(version);

        // Place finder patterns
        placeFinder(m, 0, 0);
        placeFinder(m, 0, m.size - 7);
        placeFinder(m, m.size - 7, 0);

        // Placement patterns
        var ap = VERSIONS[version][1];
        if (ap) {
            var aps = VERSIONS[version][2];
            for (var i = 0; i < aps.length; i++) {
                for (var j = 0; j < aps.length; j++) {
                    if (!(i === 0 && j === 0) && !(i === 0 && j === aps.length - 1) &&
                        !(i === aps.length - 1 && j === 0)) {
                        placeAlignment(m, aps[i], aps[j]);
                    }
                }
            }
        }

        placeTiming(m);
        reserveFormat(m);
        placeData(m, dataResult.bits);
        applyFormat(m, ecLevel);

        // Build SVG
        var mat = m.matrix;
        var modules = m.size;
        var quiet = 4; // quiet zone
        var total = modules + quiet * 2;
        var cellW = size / total;

        var svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" width="' + size + '" height="' + size + '">';
        svg += '<rect width="' + size + '" height="' + size + '" fill="white"/>';
        svg += '<g fill="black">';
        for (var r = 0; r < modules; r++) {
            for (var c = 0; c < modules; c++) {
                if (mat[r][c] === 1) {
                    svg += '<rect x="' + ((c + quiet) * cellW) + '" y="' + ((r + quiet) * cellW) + '" width="' + cellW + '" height="' + cellW + '"/>';
                }
            }
        }
        svg += '</g></svg>';
        return svg;
    }

    return { generate: generate };
})();
