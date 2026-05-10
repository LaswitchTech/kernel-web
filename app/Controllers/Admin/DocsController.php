<?php

namespace App\Controllers\Admin;

/**
 * Render markdown documentation from /docs at /docs/{path} routes.
 */
class DocsController
{
    /** @var string Base path to the /docs directory */
    private string $docsRoot;

    public function __construct()
    {
        $this->docsRoot = realpath(__DIR__ . '/../../../docs');
    }

    /**
     * Render a documentation page.
     *
     * @param string $path The doc path (e.g., "developer/extensions/catalog")
     */
    public function index(string $path = 'index'): void
    {
        $filePath = $this->resolveDocPath($path);

        if ($filePath === null || !is_file($filePath)) {
            http_response_code(404);
            $mdContent = "# Page Not Found\n\nThe requested documentation page could not be found.\n\n[Return to Docs Index](/docs/index)";
        } else {
            $mdContent = @file_get_contents($filePath);
            if ($mdContent === false) {
                http_response_code(500);
                $mdContent = "# Error\n\nCould not read this document.";
            }
        }

        $html = $this->renderMarkdown($mdContent);
        $toc = $this->extractTOC($mdContent);
        $relativePath = ($filePath !== null && is_file($filePath))
            ? $this->getRelativePath($filePath)
            : '';
        $siblings = ($filePath !== null && is_file($filePath))
            ? $this->listSiblings($filePath)
            : [];

        $appName  = 'Kernel-Web';
        $pageTitle = ($filePath === null || !is_file($filePath))
            ? 'Page Not Found'
            : $this->pageTitle($mdContent);
        $user = $_SESSION['user'] ?? ['username' => 'admin'];
        $breadcrumbs = ($filePath === null || !is_file($filePath))
            ? [['label' => 'Docs', 'url' => '/docs/index'], ['label' => 'Not Found']]
            : $this->breadcrumbs($relativePath);

        // Output buffer for content
        ob_start();
        ?>
<div class="row g-4">
    <!-- Sidebar TOC -->
    <div class="col-lg-3 d-none d-lg-block">
        <nav class="sidebar-docs" id="sidebar-docs">
            <h6 class="text-uppercase fw-semibold small text-muted mb-3">On this page</h6>
            <?php if ($toc): ?>
            <ul class="list-unstyled">
                <?php foreach ($toc as $item): ?>
                <li class="mb-1">
                    <a href="#<?= htmlspecialchars($item['id']) ?>"
                       class="text-decoration-none d-block pe-2"
                       style="margin-left: <?= ($item['level'] - 2) * 12 ?>px; font-size: 0.875rem;">
                        <?= htmlspecialchars($item['title']) ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php else: ?>
            <p class="text-muted small">No sections.</p>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Main content -->
    <div class="col-lg-9">
        <div class="card">
            <div class="card-body p-4" id="doc-content">
                <?= $html ?>
            </div>
        </div>

        <!-- Prev/Next navigation -->
        <?php if ($siblings): ?>
        <nav class="d-flex justify-content-between mt-3">
            <?php if (isset($siblings['prev'])): ?>
            <a href="/docs/<?= htmlspecialchars($siblings['prev']) ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i><?= htmlspecialchars($siblings['prev']) ?>
            </a>
            <?php else: ?>
            <span></span>
            <?php endif; ?>

            <?php if (isset($siblings['next'])): ?>
            <a href="/docs/<?= htmlspecialchars($siblings['next']) ?>" class="btn btn-outline-secondary btn-sm ms-auto">
                <?= htmlspecialchars($siblings['next']) ?><i class="bi bi-arrow-right ms-1"></i>
            </a>
            <?php else: ?>
            <span></span>
            <?php endif; ?>
        </nav>
        <?php endif; ?>

        <!-- Edit on GitHub -->
        <p class="text-muted small mt-3 mb-0">
            <a href="https://github.com/LaswitchTech/kernel-web/edit/dev/docs/<?= htmlspecialchars($relativePath) ?>.md"
               target="_blank" rel="noopener" class="text-decoration-none">
                <i class="bi bi-github me-1"></i>Edit this page on GitHub
            </a>
        </p>
    </div>
</div>
        <?php
        $content = ob_get_clean();

        $viewsPath = __DIR__ . '/../../Views';
        $this->requireLayout($viewsPath, [
            'appName' => $appName,
            'pageTitle' => $pageTitle,
            'user' => $user,
            'breadcrumbs' => $breadcrumbs,
            'content' => $content,
        ]);
    }

    /**
     * Resolve a doc path to a file on disk.
     *
     * Rejects traversal (..), non-.md files, and paths outside docsRoot.
     *
     * @param string $path URL path segment (e.g., "developer/extensions/catalog")
     * @return string|null Resolved file path, or null if invalid
     */
    private function resolveDocPath(string $path): ?string
    {
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            return null;
        }

        $normalized = rtrim($path, '/');
        if ($normalized === '') {
            $normalized = 'index';
        }

        $candidate = $this->docsRoot . '/' . $normalized . '.md';
        $real = realpath($candidate);

        if ($real === false) {
            return null;
        }

        if (str_starts_with($real, $this->docsRoot) === false) {
            return null;
        }

        if (substr($real, -3) !== '.md') {
            return null;
        }

        return $real;
    }

    /**
     * Extract the relative path from docsRoot.
     */
    private function getRelativePath(string $absPath): string
    {
        return substr(str_replace($this->docsRoot . '/', '', $absPath), 0, -3);
    }

    /**
     * List sibling docs in the same directory for prev/next navigation.
     *
     * @return array{prev?: string, next?: string}
     */
    private function listSiblings(string $absPath): array
    {
        $dir = dirname($absPath);
        $files = [];

        if (!is_dir($dir)) {
            return [];
        }

        foreach (scandir($dir) as $file) {
            if ($file === '.' || $file === '..' || substr($file, -3) !== '.md') {
                continue;
            }
            $files[] = substr($file, 0, -3);
        }

        sort($files);

        $result = [];
        $current = array_search(basename($absPath) . '.md', array_map(fn($f) => $f . '.md', $files));
        if ($current !== false) {
            if ($current > 0) {
                $result['prev'] = $files[$current - 1];
            }
            if ($current < count($files) - 1) {
                $result['next'] = $files[$current + 1];
            }
        }

        return $result;
    }

    /**
     * Extract page title from the first H1 heading.
     */
    private function pageTitle(string $content): string
    {
        if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
            return trim($matches[1]);
        }
        return 'Documentation';
    }

    /**
     * Extract table of contents from H2 headings.
     *
     * @return list<array{id: string, title: string, level: int}>
     */
    private function extractTOC(string $content): array
    {
        $toc = [];
        $idCounter = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^##\s+(.+)$/', $line, $matches)) {
                $title = trim($matches[1]);
                $clean = preg_replace('/[*_`]/', '', $title);
                $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $clean));
                $baseSlug = $slug;
                while (isset($idCounter[$slug])) {
                    $idCounter[$baseSlug]++;
                    $slug = $baseSlug . '-' . $idCounter[$baseSlug];
                }
                $idCounter[$slug] = 0;
                $toc[] = ['id' => $slug, 'title' => $title, 'level' => 2];
            }
        }

        return $toc;
    }

    /**
     * Build breadcrumbs from the doc path.
     *
     * @return list<array{label: string, url?: string}>
     */
    private function breadcrumbs(string $relativePath): array
    {
        $parts = explode('/', $relativePath);
        $breadcrumbs = [['label' => 'Docs', 'url' => '/docs/index']];
        $url = '';

        foreach ($parts as $i => $part) {
            $url .= '/' . $part;
            $breadcrumbs[] = [
                'label' => ucwords(str_replace('-', ' ', $part)),
                'url' => '/docs' . $url,
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Render markdown content to HTML.
     */
    private function renderMarkdown(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $blocks = preg_split('/\n{2,}/', $content);
        $htmlBlocks = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }
            $htmlBlocks[] = $this->renderBlock($block);
        }

        return implode("\n", $htmlBlocks);
    }

    /**
     * Render a single markdown block.
     */
    private function renderBlock(string $block): string
    {
        // Fenced code block
        if (preg_match('/^```(\w*)\n?(.*?)^```$/ms', $block, $m)) {
            return '<pre><code class="language-' . htmlspecialchars($m[1]) . '">'
                . htmlspecialchars($m[2]) . '</code></pre>';
        }

        // Setext headings
        if (preg_match('/^(.+)\n=+$/', $block, $m)) {
            return '<h1>' . $this->inline($m[1]) . '</h1>';
        }
        if (preg_match('/^(.+)\n-+$/', $block, $m)) {
            return '<h2>' . $this->inline($m[1]) . '</h2>';
        }

        // ATX headings
        if (preg_match('/^#{1,6}\s+(.+)$/', $block, $m)) {
            $level = strlen(preg_replace('/^(#+).*/', '$1', $block));
            return "<h{$level}>" . $this->inline($m[1]) . "</h{$level}>";
        }

        // Horizontal rule
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $block)) {
            return '<hr>';
        }

        // Blockquote
        if (preg_match('/^>\s?(.*)/', $block)) {
            $lines = explode("\n", $block);
            $quoteLines = array_map(function ($l) {
                return htmlspecialchars(ltrim(preg_replace('/^>\s?/', '', $l), ' '));
            }, $lines);
            return '<blockquote>' . implode("\n", $quoteLines) . '</blockquote>';
        }

        // Unordered list
        if (preg_match('/^[\s]*[-*+]\s/', $block)) {
            $items = [];
            $currentItem = '';
            foreach (explode("\n", $block) as $line) {
                if (preg_match('/^[\s]*[-*+]\s+(.*)/', $line, $m)) {
                    if ($currentItem !== '') {
                        $items[] = $this->inline(trim($currentItem));
                    }
                    $currentItem = $m[1];
                } else {
                    $currentItem .= "\n" . $line;
                }
            }
            if ($currentItem !== '') {
                $items[] = $this->inline(trim($currentItem));
            }
            return '<ul>' . implode("\n", array_map(fn($i) => "<li>{$i}</li>", $items)) . '</ul>';
        }

        // Ordered list
        if (preg_match('/^[\s]*\d+\.\s/', $block)) {
            $items = [];
            $currentItem = '';
            foreach (explode("\n", $block) as $line) {
                if (preg_match('/^[\s]*\d+\.\s+(.*)/', $line, $m)) {
                    if ($currentItem !== '') {
                        $items[] = $this->inline(trim($currentItem));
                    }
                    $currentItem = $m[1];
                } else {
                    $currentItem .= "\n" . $line;
                }
            }
            if ($currentItem !== '') {
                $items[] = $this->inline(trim($currentItem));
            }
            return '<ol>' . implode("\n", array_map(fn($i) => "<li>{$i}</li>", $items)) . '</ol>';
        }

        // Paragraph
        return '<p>' . $this->inline(nl2br($block)) . '</p>';
    }

    /**
     * Render inline markdown (bold, italic, code, links, images).
     */
    private function inline(string $text): string
    {
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="'. htmlspecialchars('$2', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .'" alt="'. htmlspecialchars('$1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .'">', $text);
        $text = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="'. htmlspecialchars('$2', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .'">'. htmlspecialchars('$1', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') .'</a>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);

        return $text;
    }

    /**
     * Include the panel layout with given variables.
     */
    private function requireLayout(string $viewsPath, array $vars): void
    {
        extract($vars);
        require $viewsPath . '/layouts/panel.php';
    }
}
