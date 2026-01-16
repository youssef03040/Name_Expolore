<?php
require_once dirname(__DIR__) . '/include/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configPath = dirname(__DIR__) . '/include/admin_config.php';
$credentials = is_file($configPath) ? require $configPath : ['username' => 'admin', 'password_hash' => ''];

$errors = [];
$statusMessage = '';

if (isset($_GET['logout'])) {
    admin_logout();
    $_SESSION['admin_status'] = 'Je bent uitgelogd.';
    header('Location: admin.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $errors[] = 'Vul zowel gebruikersnaam als wachtwoord in.';
    } elseif (!authenticate_admin($username, $password, $credentials['username'], $credentials['password_hash'])) {
        $errors[] = 'Onjuiste combinatie van gebruikersnaam en wachtwoord.';
    } else {
        $_SESSION['admin_status'] = 'Je bent succesvol ingelogd.';
        header('Location: admin.php');
        exit;
    }
}

if (isset($_SESSION['admin_status'])) {
    $statusMessage = $_SESSION['admin_status'];
    unset($_SESSION['admin_status']);
}

$isAuthenticated = is_admin_authenticated();

include dirname(__DIR__) . '/include/header.php';
?>
<main>
    <section class="section">
        <div class="container">
            <header class="section-heading">
                <h1>Adminomgeving</h1>
                <p>Beheer de namenbibliotheek en aanvullende content vanaf deze beveiligde pagina.</p>
            </header>

            <?php if ($statusMessage !== ''): ?>
                <p class="status-message"><?php echo e($statusMessage); ?></p>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="status-message error">
                    <?php foreach ($errors as $error): ?>
                        <p><?php echo e($error); ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($isAuthenticated): ?>
                <section class="dashboard" aria-label="Admin dashboard">
                    <h2>Welkom terug, <?php echo e($credentials['username']); ?>!</h2>
                    <p>Dit is een startpunt voor beheertaken. Breid deze omgeving uit met formulieren om namen toe te voegen, te bewerken of te verwijderen.</p>
                    <div class="cta-group">
                        <a class="cta-button" href="admin.php?logout=1">Log uit</a>
                    </div>
                </section>
            <?php else: ?>
                <form class="auth-form" method="post" action="admin.php" novalidate>
                    <div class="form-group">
                        <label for="username">Gebruikersnaam</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            required
                            autocomplete="username"
                            value="<?php echo isset($_POST['username']) ? e($_POST['username']) : ''; ?>"
                        >
                    </div>
                    <div class="form-group">
                        <label for="password">Wachtwoord</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            required
                            autocomplete="current-password"
                        >
                    </div>
                    <button class="cta-button" type="submit">Inloggen</button>
                    <p class="form-hint">Tip: wijzig de standaardgegevens in include/admin_config.php.</p>
                </form>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include dirname(__DIR__) . '/include/footer.php'; ?>
