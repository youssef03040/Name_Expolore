<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$documentRoot = isset($_SERVER['DOCUMENT_ROOT'])
    ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
    : '';

$stylesheetPath = realpath(__DIR__ . '/../css/style.css');
$stylesheetHref = '/Name_Expolore/css/style.css';

if ($stylesheetPath !== false && $documentRoot !== '') {
    $normalizedPath = str_replace('\\', '/', $stylesheetPath);
    if (strpos($normalizedPath, $documentRoot) === 0) {
        $stylesheetHref = substr($normalizedPath, strlen($documentRoot));
        if ($stylesheetHref === '') {
            $stylesheetHref = '/';
        }
    }
}

$stylesheetHref = '/' . ltrim(preg_replace('#/+#', '/', $stylesheetHref), '/');
$encodedSegments = array_map('rawurlencode', array_filter(explode('/', trim($stylesheetHref, '/')), 'strlen'));
$stylesheetHref = '/' . implode('/', $encodedSegments);

$projectSegments = [];
$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot !== false && $documentRoot !== '') {
    $normalizedProject = str_replace('\\', '/', $projectRoot);
    if (strpos($normalizedProject, $documentRoot) === 0) {
        $relativeProject = substr($normalizedProject, strlen($documentRoot));
        $projectSegments = array_filter(explode('/', trim($relativeProject, '/')), 'strlen');
    }
}

$pagesSegments = array_merge($projectSegments, ['paginas']);
$encodedPagesSegments = array_map('rawurlencode', $pagesSegments);
$pagesBase = '/' . implode('/', $encodedPagesSegments);

$buildPageUrl = static function (string $path = '') use ($pagesBase): string {
    $fragment = '';
    $hashPos = strpos($path, '#');
    if ($hashPos !== false) {
        $fragment = substr($path, $hashPos + 1);
        $path = substr($path, 0, $hashPos);
    }

    $pathSegments = array_filter(explode('/', trim($path, '/')), 'strlen');
    $encodedPath = array_map('rawurlencode', $pathSegments);

    $fullPath = rtrim($pagesBase, '/');
    if (!empty($encodedPath)) {
        $fullPath .= '/' . implode('/', $encodedPath);
    }

    if ($fullPath === '') {
        $fullPath = '/';
    }

    if ($fragment !== '') {
        $fullPath .= '#' . rawurlencode($fragment);
    }

    return $fullPath;
};

$homeUrl = $buildPageUrl('index.php');
$namesUrl = $buildPageUrl('namen.php');
$popularUrl = $buildPageUrl('index.php#popular');
$contactUrl = $buildPageUrl('index.php#contact');
$adminUrl = $buildPageUrl('admin.php');
$adminLogoutUrl = $adminUrl . (strpos($adminUrl, '?') === false ? '?logout=1' : '&logout=1');
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Name Explore</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($stylesheetHref, ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body>
    <header class="site-header">
        <div class="container">
            <div class="brand">
                <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">Name Explore</a>
            </div>
            <nav class="site-nav">
                <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">Home</a>
                <a href="<?php echo htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8'); ?>">Zoeken</a>
                <a href="<?php echo htmlspecialchars($namesUrl, ENT_QUOTES, 'UTF-8'); ?>">Name Explore</a>
                <a href="<?php echo htmlspecialchars($popularUrl, ENT_QUOTES, 'UTF-8'); ?>">Populaire namen</a>
                <a href="<?php echo htmlspecialchars($contactUrl, ENT_QUOTES, 'UTF-8'); ?>">Contact</a>
                <?php if (is_admin_authenticated()): ?>
                    <a href="<?php echo htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8'); ?>">Dashboard</a>
                    <a href="<?php echo htmlspecialchars($adminLogoutUrl, ENT_QUOTES, 'UTF-8'); ?>">Log uit</a>
                <?php else: ?>
                    <a href="<?php echo htmlspecialchars($adminUrl, ENT_QUOTES, 'UTF-8'); ?>">Admin</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>