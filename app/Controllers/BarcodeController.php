<?php

namespace App\Controllers;

use App\Core\Controller;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorSVG;

/**
 * Server-side barcode/QR generation API.
 *
 * Simple values via path:
 *   GET /api/barcode/QR/SVG/FI2I72O7Q5KULICABPJD7QDGHNB3JFNA
 *
 * Complex values (URLs, slashes, special chars) via query string:
 *   GET /api/barcode/QR/SVG?value=https%3A%2F%2Flaswitchtech.com%2F
 *
 * TYPE     — QR, CODE128, CODE39, EAN13, EAN8, UPCA, UPCE, etc.
 * FORMAT   — SVG
 *
 * Query params:
 *   size   — barcode size in pixels (default: 150)
 *   margin — quiet zone margin (default: 2)
 */
class BarcodeController extends Controller
{
    // Whitelisted barcode type names (user-facing) mapped to picqer string type codes.
    // Values are plain strings to avoid requiring Composer autoload at class-parse time.
    private const BARCODE_TYPES = [
        'CODE128'  => 'C128',
        'CODE128A' => 'C128A',
        'CODE128B' => 'C128B',
        'CODE128C' => 'C128C',
        'CODE39'   => 'C39',
        'CODE39+'  => 'C39+',
        'CODE39E'  => 'C39E',
        'CODE39E+' => 'C39E+',
        'CODE93'   => 'C93',
        'EAN13'    => 'EAN13',
        'EAN8'     => 'EAN8',
        'EAN2'     => 'EAN2',
        'EAN5'     => 'EAN5',
        'UPCA'     => 'UPCA',
        'UPCE'     => 'UPCE',
        'ITF14'    => 'ITF14',
        'MSI'      => 'MSI',
        'MSI+'     => 'MSI+',
        'POSTNET'  => 'POSTNET',
        'PLANET'   => 'PLANET',
        'RMS4CC'   => 'RMS4CC',
        'KIX'      => 'KIX',
        'IMB'      => 'IMB',
        'CODABAR'  => 'CODABAR',
        'CODE11'   => 'CODE11',
        'PHARMA'   => 'PHARMA',
        'PHARMA2T' => 'PHARMA2T',
    ];

    // Whitelisted QR types.
    private const QR_TYPES = ['QR', 'MICRO_QR'];

    // Whitelisted output formats.
    private const FORMATS = ['SVG'];

    public function svg(array $params = []): void
    {
        $type    = strtoupper((string) ($params['type'] ?? ''));
        $format  = strtoupper((string) ($params['format'] ?? ''));

        // Value may come from route param or query string (for URL-encoded slashes).
        $value = $params['value'] ?? '';
        if ($value === '') {
            $value = $_GET['value'] ?? '';
        }
        $value = urldecode((string) $value);

        // Validate type
        if ($type === '') {
            $this->json(['error' => 'Missing barcode type. Use /api/barcode/QR/SVG/{value} or /api/barcode/QR/SVG?value={encoded}.'], 400);
            return;
        }

        // Validate format
        if (!in_array($format, self::FORMATS, true)) {
            $this->json(['error' => 'Unsupported format. Use: ' . implode(', ', self::FORMATS) . '.'], 400);
            return;
        }

        // Validate value
        if ($value === '') {
            $this->json(['error' => 'Empty barcode content.'], 400);
            return;
        }

        // Query params
        $size    = (int) ($_GET['size'] ?? 150);
        $margin  = (int) ($_GET['margin'] ?? 2);

        try {
            $svg = $this->generateBarcode($type, $value, $size, $margin);
        } catch (\Throwable $e) {
            $this->json(['error' => 'Barcode generation failed: ' . $e->getMessage()], 400);
            return;
        }

        header('Content-Type: image/svg+xml');
        echo $svg;
    }

    /**
     * Generate a barcode/QR code SVG string.
     */
    private function generateBarcode(string $type, string $value, int $size, int $margin): string
    {
        // Handle QR codes
        if (in_array($type, self::QR_TYPES, true)) {
            return $this->generateQR($value, $size, $margin);
        }

        // Handle standard barcodes
        if (array_key_exists($type, self::BARCODE_TYPES)) {
            return $this->generateStandardBarcode($type, $value, $size, $margin);
        }

        throw new \InvalidArgumentException('Unknown barcode type: ' . $type);
    }

    /**
     * Generate QR code SVG using chillerlan/php-qrcode.
     */
    private function generateQR(string $value, int $size, int $margin): string
    {
        $scale = max(1, (int) round($size / 210)); // approximate scale for QR

        $options = new QROptions([
            'scale'        => $scale,
            'outputType'   => 'svg',
            'addQuietzone' => true,
            'quietzoneSize' => $margin,
            'svgOpacity'   => 1.0,
            'imageBase64'  => false,
        ]);

        $qrCode = new QRCode($options);
        $svg = $qrCode->render($value);

        if (is_string($svg) && strlen($svg) > 0) {
            return $svg;
        }

        throw new \RuntimeException('QR code generation returned empty output');
    }

    /**
     * Generate standard barcode SVG using picqer/php-barcode-generator.
     */
    private function generateStandardBarcode(string $type, string $value, int $size, int $margin): string
    {
        $codeType = self::BARCODE_TYPES[$type];
        $generator = new BarcodeGeneratorSVG();
        $barcode = $generator->getBarcode($value, $codeType, $margin, $size);

        if (is_string($barcode) && strlen($barcode) > 0) {
            return $barcode;
        }

        throw new \RuntimeException('Barcode generation returned empty output');
    }
}
