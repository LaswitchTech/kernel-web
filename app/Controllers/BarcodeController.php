<?php

namespace App\Controllers;

use App\Core\Controller;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Picqer\Barcode\BarcodeGeneratorSVG;
use Picqer\Barcode\BarcodeGenerator;

/**
 * Server-side barcode/QR generation API.
 *
 * GET /barcode/{TYPE}/{FORMAT}/{VALUE}
 *   TYPE     — QR, CODE128, CODE39, EAN13, EAN8, UPCA, UPCE, etc.
 *   FORMAT   — SVG
 *   VALUE    — URL-decoded barcode content
 *
 * Query params:
 *   size   — barcode size in pixels (default: 150)
 *   margin — quiet zone margin (default: 2)
 */
class BarcodeController extends Controller
{
    // Whitelisted barcode types mapped to picqer constants.
    private const BARCODE_TYPES = [
        'CODE128'  => BarcodeGenerator::TYPE_CODE_128,
        'CODE128A' => BarcodeGenerator::TYPE_CODE_128_A,
        'CODE128B' => BarcodeGenerator::TYPE_CODE_128_B,
        'CODE128C' => BarcodeGenerator::TYPE_CODE_128_C,
        'CODE39'   => BarcodeGenerator::TYPE_CODE_39,
        'CODE39+'  => BarcodeGenerator::TYPE_CODE_39_CHECKSUM,
        'CODE39E'  => BarcodeGenerator::TYPE_CODE_39E,
        'CODE39E+' => BarcodeGenerator::TYPE_CODE_39E_CHECKSUM,
        'CODE93'   => BarcodeGenerator::TYPE_CODE_93,
        'EAN13'    => BarcodeGenerator::TYPE_EAN_13,
        'EAN8'     => BarcodeGenerator::TYPE_EAN_8,
        'EAN2'     => BarcodeGenerator::TYPE_EAN_2,
        'EAN5'     => BarcodeGenerator::TYPE_EAN_5,
        'UPCA'     => BarcodeGenerator::TYPE_UPC_A,
        'UPCE'     => BarcodeGenerator::TYPE_UPC_E,
        'ITF14'    => BarcodeGenerator::TYPE_ITF_14,
        'MSI'      => BarcodeGenerator::TYPE_MSI,
        'MSI+'     => BarcodeGenerator::TYPE_MSI_CHECKSUM,
        'POSTNET'  => BarcodeGenerator::TYPE_POSTNET,
        'PLANET'   => BarcodeGenerator::TYPE_PLANET,
        'RMS4CC'   => BarcodeGenerator::TYPE_RMS4CC,
        'KIX'      => BarcodeGenerator::TYPE_KIX,
        'IMB'      => BarcodeGenerator::TYPE_IMB,
        'CODABAR'  => BarcodeGenerator::TYPE_CODABAR,
        'CODE11'   => BarcodeGenerator::TYPE_CODE_11,
        'PHARMA'   => BarcodeGenerator::TYPE_PHARMA_CODE,
        'PHARMA2T' => BarcodeGenerator::TYPE_PHARMA_CODE_TWO_TRACKS,
    ];

    // Whitelisted QR types.
    private const QR_TYPES = ['QR', 'MICRO_QR'];

    // Whitelisted output formats.
    private const FORMATS = ['SVG'];

    public function svg(array $params = []): void
    {
        $type    = strtoupper((string) ($params['type'] ?? ''));
        $format  = strtoupper((string) ($params['format'] ?? ''));
        $value   = urldecode((string) ($params['value'] ?? ''));

        // Validate type
        if ($type === '') {
            $this->json(['error' => 'Missing barcode type. Use /barcode/QR/SVG/{value}.'], 400);
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
