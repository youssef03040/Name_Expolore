<?php
require_once dirname(__DIR__) . '/include/functions.php';
require_once dirname(__DIR__) . '/include/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$configPath = dirname(__DIR__) . '/include/admin_config.php';
$credentials = is_file($configPath) ? require $configPath : ['username' => 'admin', 'password_hash' => ''];

if (!is_array($credentials) || !isset($credentials['username'], $credentials['password_hash'])) {
    $credentials = ['username' => 'admin', 'password_hash' => ''];
}

$authErrors = [];
$dashboardErrors = [];
$statusMessage = '';

if (isset($_GET['logout'])) {
    admin_logout();
    unset($_SESSION['admin_csrf']);
    $_SESSION['admin_status'] = 'Je bent uitgelogd.';
    header('Location: admin.php');
    exit;
}

$action = isset($_POST['admin_action']) ? (string) $_POST['admin_action'] : null;
$isAuthenticated = is_admin_authenticated();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login' && !$isAuthenticated) {
    $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
    $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

    if ($username === '' || $password === '') {
        $authErrors[] = 'Vul zowel gebruikersnaam als wachtwoord in.';
    } elseif (!authenticate_admin($username, $password, $credentials['username'], $credentials['password_hash'])) {
        $authErrors[] = 'Onjuiste combinatie van gebruikersnaam en wachtwoord.';
    } else {
        $_SESSION['admin_status'] = 'Je bent succesvol ingelogd.';
        header('Location: admin.php');
        exit;
    }
}

if (isset($_SESSION['admin_status'])) {
    $statusMessage = (string) $_SESSION['admin_status'];
    unset($_SESSION['admin_status']);
}

$isAuthenticated = is_admin_authenticated();
$adminToken = isset($_SESSION['admin_csrf']) ? (string) $_SESSION['admin_csrf'] : '';

if ($isAuthenticated) {
    if ($adminToken === '') {
        $adminToken = bin2hex(random_bytes(16));
        $_SESSION['admin_csrf'] = $adminToken;
    }
} else {
    unset($_SESSION['admin_csrf']);
    $adminToken = '';
}

$pdo = null;
$tableExists = false;
$columnSet = [];
$adminStats = ['total_names' => 0];
$recentNames = [];
$displayColumns = [];
$formData = [
    'name' => '',
    'gender' => '',
    'origin' => '',
    'meaning' => '',
    'popularity_rank' => '',
    'popularity' => '',
    'usage' => '',
];

if ($isAuthenticated) {
    try {
        $pdo = Database::getInstance()->getConnection();
    } catch (PDOException $exception) {
        $dashboardErrors[] = 'Databaseverbinding mislukt: ' . $exception->getMessage();
    }

    if ($pdo instanceof PDO) {
        $fetchTableMeta = static function (PDO $pdo): array {
            $exists = false;
            $columns = [];
            $checkStmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
            );
            $checkStmt->execute(['table' => 'names']);
            $exists = $checkStmt->fetchColumn() > 0;

            if ($exists) {
                $columnsStmt = $pdo->query('SHOW COLUMNS FROM `names`');
                $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
                $columns = array_filter($columns, 'strlen');
            }

            return [$exists, array_fill_keys($columns, true)];
        };

        [$tableExists, $columnSet] = $fetchTableMeta($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'login') {
            if (!isset($_POST['admin_token']) || !hash_equals($adminToken, (string) $_POST['admin_token'])) {
                $dashboardErrors[] = 'De sessie is verlopen. Probeer het opnieuw.';
            } else {
                if ($action === 'create_table') {
                    if ($tableExists) {
                        $dashboardErrors[] = 'De tabel bestaat al.';
                    } else {
                        try {
                            $pdo->exec(
                                'CREATE TABLE IF NOT EXISTS `names` (
                                    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                                    `name` VARCHAR(255) NOT NULL,
                                    `gender` VARCHAR(50) DEFAULT NULL,
                                    `origin` VARCHAR(255) DEFAULT NULL,
                                    `meaning` TEXT DEFAULT NULL,
                                    `popularity_rank` INT UNSIGNED DEFAULT NULL,
                                    `popularity` VARCHAR(100) DEFAULT NULL,
                                    `usage` VARCHAR(255) DEFAULT NULL,
                                    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
                            );
                            $_SESSION['admin_status'] = 'De tabel is aangemaakt.';
                            header('Location: admin.php');
                            exit;
                        } catch (PDOException $exception) {
                            $dashboardErrors[] = 'Aanmaken mislukt: ' . $exception->getMessage();
                        }
                    }
                } elseif ($action === 'add_name') {
                    foreach ($formData as $key => $value) {
                        if (isset($_POST[$key])) {
                            if ($key === 'meaning') {
                                $formData[$key] = trim((string) $_POST[$key]);
                            } else {
                                $formData[$key] = trim(preg_replace('/\s+/', ' ', (string) $_POST[$key]));
                            }
                        }
                    }

                    if (!$tableExists) {
                        $dashboardErrors[] = 'Zorg dat de tabel bestaat voordat je namen toevoegt.';
                    } elseif (!isset($columnSet['name'])) {
                        $dashboardErrors[] = 'De tabel mist de kolom name.';
                    }

                    if ($formData['name'] === '') {
                        $dashboardErrors[] = 'Naam is verplicht.';
                    }

                    $rankValue = $formData['popularity_rank'];
                    if ($rankValue !== '' && !ctype_digit($rankValue)) {
                        $dashboardErrors[] = 'Populariteit (rang) moet een positief getal zijn.';
                    }

                    if (empty($dashboardErrors)) {
                        $insertColumns = [];
                        $placeholders = [];
                        $bindValues = [];
                        $fieldMap = [
                            'name' => $formData['name'],
                            'gender' => $formData['gender'],
                            'origin' => $formData['origin'],
                            'meaning' => $formData['meaning'],
                            'popularity_rank' => $rankValue,
                            'popularity' => $formData['popularity'],
                            'usage' => $formData['usage'],
                        ];

                        foreach ($fieldMap as $column => $value) {
                            if (!isset($columnSet[$column])) {
                                continue;
                            }

                            $insertColumns[] = "`$column`";
                            $placeholders[] = ':' . $column;

                            if ($value === '' || $value === null) {
                                $bindValues[':' . $column] = null;
                            } elseif ($column === 'popularity_rank') {
                                $bindValues[':' . $column] = (int) $value;
                            } else {
                                $bindValues[':' . $column] = $value;
                            }
                        }

                        if (!empty($insertColumns)) {
                            $sql = 'INSERT INTO `names` (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $placeholders) . ')';
                            try {
                                $stmt = $pdo->prepare($sql);
                                foreach ($bindValues as $placeholder => $value) {
                                    if ($value === null) {
                                        $stmt->bindValue($placeholder, null, PDO::PARAM_NULL);
                                    } elseif ($placeholder === ':popularity_rank') {
                                        $stmt->bindValue($placeholder, $value, PDO::PARAM_INT);
                                    } else {
                                        $stmt->bindValue($placeholder, $value, PDO::PARAM_STR);
                                    }
                                }
                                $stmt->execute();
                                $_SESSION['admin_status'] = 'De naam is toegevoegd.';
                                header('Location: admin.php');
                                exit;
                            } catch (PDOException $exception) {
                                $dashboardErrors[] = 'Toevoegen mislukt: ' . $exception->getMessage();
                            }
                        } else {
                            $dashboardErrors[] = 'Geen geldige kolommen om op te slaan.';
                        }
                    }
                }
            }
        }

        [$tableExists, $columnSet] = $fetchTableMeta($pdo);

        if ($tableExists) {
            try {
                $countStmt = $pdo->query('SELECT COUNT(*) FROM `names`');
                $adminStats['total_names'] = (int) $countStmt->fetchColumn();
            } catch (PDOException $exception) {
                $dashboardErrors[] = 'Kan totaal niet ophalen: ' . $exception->getMessage();
            }

            $displayColumnLabels = [
                'name' => 'Naam',
                'gender' => 'Geslacht',
                'origin' => 'Herkomst',
                'usage' => 'Gebruik',
                'meaning' => 'Betekenis',
                'popularity_rank' => 'Ranking',
                'popularity' => 'Populariteit',
                'created_at' => 'Toegevoegd op',
            ];

            foreach ($displayColumnLabels as $column => $label) {
                if ($column === 'name') {
                    if (isset($columnSet['name'])) {
                        $displayColumns[$column] = $label;
                    }
                    continue;
                }

                if (isset($columnSet[$column])) {
                    $displayColumns[$column] = $label;
                }
            }

            if (empty($displayColumns) && isset($columnSet['name'])) {
                $displayColumns['name'] = 'Naam';
            } elseif (isset($columnSet['name']) && !array_key_exists('name', $displayColumns)) {
                $displayColumns = ['name' => 'Naam'] + $displayColumns;
            }

            if (!empty($displayColumns)) {
                $selectColumns = array_map(static function ($column) {
                    return "`$column`";
                }, array_keys($displayColumns));

                $orderColumn = '`name`';
                $orderDirection = 'ASC';
                if (isset($columnSet['created_at'])) {
                    $orderColumn = '`created_at`';
                    $orderDirection = 'DESC';
                } elseif (isset($columnSet['id'])) {
                    $orderColumn = '`id`';
                    $orderDirection = 'DESC';
                }

                try {
                    $recentStmt = $pdo->query(
                        'SELECT ' . implode(', ', $selectColumns) . ' FROM `names` ORDER BY ' . $orderColumn . ' ' . $orderDirection . ' LIMIT 10'
                    );
                    $recentNames = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $exception) {
                    $dashboardErrors[] = 'Kan recente namen niet ophalen: ' . $exception->getMessage();
                }
            }
        }
    }
}

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

            <?php if (!$isAuthenticated): ?>
                <?php if (!empty($authErrors)): ?>
                    <div class="status-message error">
                        <?php foreach ($authErrors as $error): ?>
                            <p><?php echo e($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="auth-form" method="post" action="admin.php" novalidate>
                    <input type="hidden" name="admin_action" value="login">
                    <div class="form-group">
                        <label for="username">Gebruikersnaam</label>
                        <input
                            id="username"
                            name="username"
                            type="text"
                            required
                            autocomplete="username"
                            value="<?php echo isset($_POST['username']) ? e((string) $_POST['username']) : ''; ?>"
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
            <?php else: ?>
                <?php if (!empty($dashboardErrors)): ?>
                    <div class="status-message error">
                        <?php foreach ($dashboardErrors as $error): ?>
                            <p><?php echo e($error); ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <section class="dashboard" aria-label="Admin dashboard">
                    <h2>Welkom terug, <?php echo e($credentials['username']); ?>!</h2>

                    <?php if ($tableExists): ?>
                        <div class="highlight-panel">
                            <div class="highlight-metric">
                                <p class="metric-label">Totaal aantal namen</p>
                                <p class="metric-value"><?php echo e((string) $adminStats['total_names']); ?></p>
                            </div>
                            <div class="highlight-copy">
                                <h3>Laatste wijzigingen</h3>
                                <p>Bekijk hieronder de meest recente toevoegingen en voeg nieuwe namen toe om de bibliotheek actueel te houden.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="status-message">Er is nog geen tabel beschikbaar. Maak de namenlijst eerst aan.</p>
                    <?php endif; ?>

                    <div class="admin-panels">
                        <article class="admin-card">
                            <h3>Namenlijst aanmaken</h3>
                            <p>Maak de standaardtabel met veelgebruikte kolommen wanneer deze nog niet bestaat.</p>
                            <form method="post" action="admin.php">
                                <input type="hidden" name="admin_action" value="create_table">
                                <input type="hidden" name="admin_token" value="<?php echo e($adminToken); ?>">
                                <button class="cta-button" type="submit"<?php echo $tableExists ? ' disabled' : ''; ?>>Maak tabel</button>
                            </form>
                        </article>

                        <article class="admin-card">
                            <h3>Nieuwe naam toevoegen</h3>
                            <p>Vul de gegevens in en voeg een naam toe aan de databank.</p>
                            <form method="post" action="admin.php">
                                <input type="hidden" name="admin_action" value="add_name">
                                <input type="hidden" name="admin_token" value="<?php echo e($adminToken); ?>">
                                <div class="form-group">
                                    <label for="name">Naam*</label>
                                    <input id="name" name="name" type="text" required value="<?php echo e($formData['name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="gender">Geslacht</label>
                                    <input id="gender" name="gender" type="text" value="<?php echo e($formData['gender']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="origin">Herkomst</label>
                                    <input id="origin" name="origin" type="text" value="<?php echo e($formData['origin']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="usage">Gebruik</label>
                                    <input id="usage" name="usage" type="text" value="<?php echo e($formData['usage']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="meaning">Betekenis</label>
                                    <textarea id="meaning" name="meaning" rows="3"><?php echo e($formData['meaning']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="popularity_rank">Populariteit (rang)</label>
                                    <input id="popularity_rank" name="popularity_rank" type="number" min="1" step="1" value="<?php echo e($formData['popularity_rank']); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="popularity">Populariteit (beschrijving)</label>
                                    <input id="popularity" name="popularity" type="text" value="<?php echo e($formData['popularity']); ?>">
                                </div>
                                <button class="cta-button" type="submit"<?php echo $tableExists ? '' : ' disabled'; ?>>Opslaan</button>
                                <?php if (!$tableExists): ?>
                                    <p class="form-hint">Maak eerst de tabel aan om namen op te slaan.</p>
                                <?php endif; ?>
                            </form>
                        </article>
                    </div>

                    <?php if ($tableExists && !empty($recentNames)): ?>
                        <section class="admin-table" aria-label="Recente namen">
                            <h3>Recente toevoegingen</h3>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <?php foreach ($displayColumns as $label): ?>
                                                <th scope="col"><?php echo e($label); ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentNames as $row): ?>
                                            <tr>
                                                <?php foreach (array_keys($displayColumns) as $column): ?>
                                                    <td>
                                                        <?php
                                                        $value = isset($row[$column]) ? $row[$column] : null;
                                                        if ($value === null || $value === '') {
                                                            echo '-';
                                                        } elseif ($column === 'meaning') {
                                                            echo nl2br(e($value));
                                                        } elseif ($column === 'created_at') {
                                                            try {
                                                                $date = new DateTime($value);
                                                                echo e($date->format('d-m-Y H:i'));
                                                            } catch (Exception $exception) {
                                                                echo e($value);
                                                            }
                                                        } elseif ($column === 'popularity_rank') {
                                                            echo '#' . e((string) $value);
                                                        } else {
                                                            echo e($value);
                                                        }
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    <?php elseif ($tableExists): ?>
                        <p class="status-message">Er zijn nog geen namen toegevoegd.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include dirname(__DIR__) . '/include/footer.php'; ?>
