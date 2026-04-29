<?php
$pagesDir = __DIR__ . '/pages/';
$files = glob($pagesDir . '*.php');
$files[] = __DIR__ . '/index.php';

foreach ($files as $file) {
    $content = file_get_contents($file);
    $modified = false;

    if (strpos($content, '<body data-theme') === false && strpos($content, '<body') !== false) {
        $content = preg_replace('/<body([^>]*)>/', '<body data-theme="<?= function_exists(''temaActual'') ? htmlspecialchars(temaActual()) : ''dark'' ?>"$1>', $content);
        $modified = true;
    }

    if (strpos($content, 'id="btnToggleTheme"') === false && strpos($content, '<div class="topbar-right">') !== false) {
        $content = str_replace('<div class="topbar-right">', '<div class="topbar-right">' . "\n" . '            <button id="btnToggleTheme" class="topbar-btn" title="Cambiar Paleta" onclick="toggleTheme()"><i class="fa-solid fa-palette"></i></button>', $content);
        $modified = true;
    }

    if ($modified) {
        file_put_contents($file, $content);
        echo "Patched " . basename($file) . "
";
    }
}
echo "Done patching pages.
";
