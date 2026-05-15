<?php

namespace App\Services\Extensions;

use App\Models\CatalogExtensionRepository;

/**
 * Compare installed extension versions against catalog versions.
 *
 * Pure service — no side effects. Used by the catalog admin page
 * to surface available updates in the UI.
 */
class ExtensionUpdateChecker
{
    /**
     * Manifest type names keyed by catalog type.
     */
    private const MANIFEST_FILES = [
        'plugin' => 'plugin.json',
        'theme'  => 'theme.json',
        'layout' => 'layout.json',
    ];

    private const LIB_SUBDIRS = [
        'plugin' => 'plugins',
        'theme'  => 'themes',
        'layout' => 'layouts',
    ];

    public function __construct(
        private CatalogExtensionRepository $catalogRepo,
        private string $libBase,
    ) {}

    /**
     * Check update status for all installed catalog extensions.
     *
     * @return array<string, ExtensionUpdate> Keyed by slug
     */
    public function checkAll(): array
    {
        $results = [];
        $installed = $this->catalogRepo->findInstalled();

        foreach ($installed as $entry) {
            $result = $this->checkSingle($entry);
            if ($result !== null) {
                $results[$entry['slug']] = $result;
            }
        }

        return $results;
    }

    /**
     * Check update status for a single installed extension.
     */
    public function check(string $slug): ?ExtensionUpdate
    {
        $entry = $this->catalogRepo->findBySlug($slug);
        if ($entry === null || (int) $entry['is_installed'] !== 1) {
            return null;
        }

        return $this->checkSingle($entry);
    }

    /**
     * Read a version string from an extension's on-disk manifest.
     */
    public static function readInstalledVersion(string $libBase, string $type, string $slug): ?string
    {
        $manifestFile = self::MANIFEST_FILES[$type] ?? null;
        if ($manifestFile === null) {
            return null;
        }

        $subDir = self::LIB_SUBDIRS[$type] ?? null;
        if ($subDir === null) {
            return null;
        }

        $path = $libBase . '/' . $subDir . '/' . $slug . '/' . $manifestFile;

        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = @json_decode($content, true);
        if (!is_array($data) || !isset($data['version']) || !is_string($data['version']) || $data['version'] === '') {
            return null;
        }

        // Validate version format (must be semver)
        if (!preg_match('/^\d+\.\d+\.\d+$/', $data['version'])) {
            return null;
        }

        return $data['version'];
    }

    /**
     * Compute the update status for a single catalog entry.
     */
    private function checkSingle(array $entry): ?ExtensionUpdate
    {
        $slug = $entry['slug'];
        $type = $entry['type'];
        $catalogVersion = $entry['version'] ?? '0.0.0';

        // Read installed version from on-disk manifest
        $installedVersion = self::readInstalledVersion($this->libBase, $type, $slug);

        if ($installedVersion === null) {
            return new ExtensionUpdate(
                slug: $slug,
                type: $type,
                installedVersion: null,
                catalogVersion: $catalogVersion,
                status: 'invalid',
                blockers: ['On-disk manifest is missing or invalid.'],
            );
        }

        // Compare versions
        $compareResult = version_compare($installedVersion, $catalogVersion);

        if ($compareResult === 0) {
            return new ExtensionUpdate(
                slug: $slug,
                type: $type,
                installedVersion: $installedVersion,
                catalogVersion: $catalogVersion,
                status: 'up_to_date',
            );
        }

        if ($compareResult > 0) {
            return new ExtensionUpdate(
                slug: $slug,
                type: $type,
                installedVersion: $installedVersion,
                catalogVersion: $catalogVersion,
                status: 'newer_than_catalog',
            );
        }

        // Catalog version is newer — check dependency constraints
        $blockers = $this->checkDependencyConstraints($entry);

        if ($blockers !== []) {
            return new ExtensionUpdate(
                slug: $slug,
                type: $type,
                installedVersion: $installedVersion,
                catalogVersion: $catalogVersion,
                status: 'blocked',
                blockers: $blockers,
            );
        }

        return new ExtensionUpdate(
            slug: $slug,
            type: $type,
            installedVersion: $installedVersion,
            catalogVersion: $catalogVersion,
            status: 'update_available',
        );
    }

    /**
     * Check if the extension's update is blocked by dependency constraints.
     *
     * Check A: Installed extension's dependencies against catalog version.
     * Check B: Catalog version's dependencies against installed catalog entries.
     */
    private function checkDependencyConstraints(array $entry): array
    {
        $resolver = new ExtensionDependencyResolver();
        $blockers = [];

        // --- Check A: installed extension's manifest dependencies ---
        $manifestFile = self::MANIFEST_FILES[$entry['type']] ?? null;
        if ($manifestFile !== null) {
            $path = $this->libBase . '/' . self::LIB_SUBDIRS[$entry['type']] . '/' . $entry['slug'] . '/' . $manifestFile;
            if (is_file($path)) {
                $content = @file_get_contents($path);
                if ($content !== false) {
                    $manifestData = @json_decode($content, true);
                    if (is_array($manifestData) && isset($manifestData['dependencies']) && is_array($manifestData['dependencies'])) {
                        $manifestDeps = $manifestData['dependencies'];
                        $manifestErrors = \App\Services\Extensions\ExtensionDependencyResolver::validateDependencyMap($manifestDeps);
                        if ($manifestErrors !== []) {
                            $blockers[] = 'Invalid dependency format in manifest.';
                        } else {
                            foreach ($manifestDeps as $depKey => $constraint) {
                                $parsed = $resolver::parseKey($depKey);
                                if ($parsed === null) {
                                    $blockers[] = "Invalid dependency key: {$depKey}.";
                                    continue;
                                }

                                // Skip if constraint is empty (any version)
                                if ($constraint === '') {
                                    continue;
                                }

                                // Check if catalog version satisfies the constraint
                                if (!ExtensionDependencyResolver::checkVersionConstraint($entry['version'], $constraint)) {
                                    $blockers[] = "{$depKey} requires {$constraint}, but catalog version is {$entry['version']}.";
                                }
                            }
                        }
                    }
                }
            }
        }

        // --- Check B: catalog entry dependencies against installed catalog entries ---
        $catalogDeps = $resolver->parseDependencies($entry['dependencies'] ?? '[]');
        if ($catalogDeps === null) {
            $blockers[] = 'Invalid dependency format in catalog entry.';
        } elseif ($catalogDeps !== []) {
            $allCatalog = $this->catalogRepo->findAll();
            $depResult = $resolver->checkEnable($catalogDeps, $allCatalog);
            if (!$depResult['allowed']) {
                foreach ($depResult['blockers'] as $blocker) {
                    $blockers[] = $blocker['message'];
                }
            }
        }

        // --- Check C: kernel compatibility from catalog requirements ---
        $requirements = json_decode($entry['requirements'] ?? '[]', true);
        if (is_array($requirements) && isset($requirements['kernel']) && $requirements['kernel'] !== '') {
            $versionProvider = new \App\Core\VersionProvider(dirname(dirname(__DIR__)));
            $kernelVersion = $versionProvider->getKernelVersion();
            if (!ExtensionDependencyResolver::checkKernelCompatibility($kernelVersion, $requirements['kernel'])) {
                $blockers[] = [
                    'type'     => 'kernel',
                    'message'  => "Requires kernel {$requirements['kernel']}. Current kernel is v{$kernelVersion}.",
                    'dependency' => 'kernel',
                ];
            }
        }

        return $blockers;
    }
}
