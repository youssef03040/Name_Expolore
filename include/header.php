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
                <a href="/">Name Explore</a>
            </div>
            <nav class="site-nav">
                <a href="/">Home</a>
                <a href="/Name_Expolore/index.php">Zoeken</a>
                <a href="/Name_Expolore/index.php#popular">Populaire namen</a>
                <a href="/Name_Expolore/index.php#contact">Contact</a>
            </nav>
        </div>
    </header>