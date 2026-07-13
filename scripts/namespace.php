<?php

declare(strict_types=1);

/**
 * One-time initializer, run after `composer create-project`.
 *
 * Replaces the placeholder name "Skeleton" / "ablogcms/plugin-skeleton" with your plugin name:
 *   - Namespace:   Acms\Plugins\Skeleton\  → Acms\Plugins\{StudlyName}\
 *   - Package:     ablogcms/plugin-skeleton → ablogcms/{kebab-name}
 *   - Identifiers: config keys, Twig namespace, menu key, ServiceProvider $name, etc.
 *
 * Afterwards it removes itself and the composer.json post-create-project-cmd entry.
 * You can also run it manually:  php scripts/namespace.php MyAwesomePlugin
 */

$root = dirname(__DIR__);

$rawName = $argv[1] ?? promptName(basename($root));
$words = splitWords($rawName);

if ($words === []) {
    fwrite(STDERR, "Invalid plugin name (it must contain letters or digits).\n");
    exit(1);
}

$studly = implode('', array_map('ucfirst', $words));            // MyAwesomePlugin
$kebab = implode('-', array_map('strtolower', $words));         // my-awesome-plugin
$snake = implode('_', array_map('strtolower', $words));         // my_awesome_plugin
$upper = strtoupper($snake);                                    // MY_AWESOME_PLUGIN

if ($studly === 'Skeleton') {
    echo "Name is still 'Skeleton'. Nothing to rename.\n";
    exit(0);
}

// Text file extensions to process (vendor / .git are skipped).
$extensions = ['php', 'json', 'xml', 'dist', 'md', 'yml', 'yaml', 'twig', 'html', 'example', 'gitignore'];

// Order matters: the package name is replaced before the bare "skeleton" token.
$replacements = [
    'plugin-skeleton' => $kebab,
    'SKELETON' => $upper,
    'Skeleton' => $studly,
    'skeleton' => $snake,
];

$changed = 0;
foreach (collectFiles($root, $extensions) as $file) {
    if ($file === __FILE__) {
        continue;
    }
    $original = (string) file_get_contents($file);
    $updated = strtr($original, $replacements);
    if ($updated !== $original) {
        file_put_contents($file, $updated);
        $changed++;
    }
}

removeSelfFromComposer($root . '/composer.json');
// Remove this one-time bootstrap. We do NOT remove scripts/ itself: the release helpers
// (Packager.php, package.php, version.php, release.php) live alongside it and must stay.
@unlink(__FILE__);

echo "\n";
echo "Plugin name applied.\n";
echo "  Namespace : Acms\\Plugins\\{$studly}\\\n";
echo "  Package   : ablogcms/{$kebab}\n";
echo "  Files updated: {$changed}\n\n";
echo "Next steps:\n";
echo "  1) cp .env.example .env\n";
echo "  2) docker compose up -d\n";
echo "  3) docker compose exec acms bash -lc 'cd /workspace && composer install && vendor/bin/phpunit'\n";

/**
 * @return list<string>
 */
function splitWords(string $name): array
{
    // Insert a space at camelCase/PascalCase boundaries, then split on non-alphanumerics.
    $name = (string) preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $name);
    $parts = preg_split('/[^A-Za-z0-9]+/', $name) ?: [];

    return array_values(array_filter($parts, static fn($p) => $p !== ''));
}

function promptName(string $default): string
{
    if (!defined('STDIN')) {
        return $default;
    }
    fwrite(STDOUT, "Plugin name (StudlyCase, e.g. MyAwesomePlugin) [{$default}]: ");
    $line = fgets(STDIN);
    $line = $line === false ? '' : trim($line);

    return $line !== '' ? $line : $default;
}

/**
 * @param list<string> $extensions
 * @return list<string>
 */
function collectFiles(string $root, array $extensions): array
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    $files = [];
    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        $path = $item->getPathname();
        if (str_contains($path, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        if (str_contains($path, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
            continue;
        }
        $base = $item->getBasename();
        $ext = $item->getExtension();
        if (in_array($ext, $extensions, true) || in_array($base, ['.gitignore', '.env.example'], true)) {
            $files[] = $path;
        }
    }

    return $files;
}

function removeSelfFromComposer(string $composerPath): void
{
    if (!is_file($composerPath)) {
        return;
    }
    /** @var array<string, mixed> $json */
    $json = json_decode((string) file_get_contents($composerPath), true);
    if (!is_array($json)) {
        return;
    }
    if (isset($json['scripts']) && is_array($json['scripts'])) {
        unset($json['scripts']['post-create-project-cmd']);
        if ($json['scripts'] === []) {
            unset($json['scripts']);
        }
    }
    file_put_contents(
        $composerPath,
        json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
    );
}
