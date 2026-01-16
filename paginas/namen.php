<?php
require_once dirname(__DIR__) . '/include/functions.php';
require_once dirname(__DIR__) . '/include/db.php';

$names = [];
$errorMessage = '';
$columnSet = [];
$selectList = '`name`';
$orderColumn = '`name`';
$tableExists = false;
$totalNames = 0;

$fallbackNames = [
    [
        'name' => 'Mila',
        'gender' => 'Meisje',
        'origin' => 'Slavisch',
        'meaning' => 'Geliefd of dierbaar',
    ],
    [
        'name' => 'Noor',
        'gender' => 'Meisje',
        'origin' => 'Arabisch',
        'meaning' => 'Licht ',
    ],
    [
        'name' => 'Elias',
        'gender' => 'Jongen',
        'origin' => 'Hebreeuws',
        'meaning' => 'De Heer is mijn God',
    ],
    [
        'name' => 'Nova',
        'gender' => 'Unisex',
        'origin' => 'Latijn',
        'meaning' => 'Nieuwe ster',
    ],
    [
        'name' => 'Finn',
        'gender' => 'Jongen',
        'origin' => 'Iers',
        'meaning' => 'Stralend of eerlijk',
    ],
    [
        'name' => 'Lina',
        'gender' => 'Meisje',
        'origin' => 'Grieks',
        'meaning' => 'Zacht of teder',
    ],
];

try {
    $pdo = Database::getInstance()->getConnection();

    $tableCheckStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table'
    );
    $tableCheckStmt->execute(['table' => 'names']);
    $tableExists = $tableCheckStmt->fetchColumn() > 0;

    if ($tableExists) {
        $columnsStmt = $pdo->query('SHOW COLUMNS FROM `names`');
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);
        $columnSet = array_fill_keys($columns, true);

        $baseColumns = ['name', 'gender', 'origin', 'meaning', 'popularity_rank', 'popularity', 'usage'];
        $selected = [];

        foreach ($baseColumns as $column) {
            if (isset($columnSet[$column])) {
                $selected[] = "`$column`";
            }
        }

        if (empty($selected)) {
            $selected[] = '`name`';
        }

        $selectList = implode(', ', array_unique($selected));

        if (isset($columnSet['popularity_rank'])) {
            $orderColumn = '`popularity_rank`';
        }

        $namesStmt = $pdo->query('SELECT ' . $selectList . ' FROM `names` ORDER BY ' . $orderColumn . ' ASC LIMIT 120');
        $names = $namesStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $names = $fallbackNames;
    }
} catch (PDOException $exception) {
    $errorMessage = 'We konden de namenlijst niet ophalen. Probeer het later nog eens.';
    $names = $fallbackNames;
}

$totalNames = count($names);

include dirname(__DIR__) . '/include/header.php';
?>
<main>
    <section class="section">
        <div class="container">
            <header class="section-heading">
                <h1>Naamoverzicht</h1>
                <p>Blader door een selectie van populaire en betekenisvolle voornamen ter inspiratie.</p>
            </header>

            <section class="highlight-panel" aria-label="Overzicht statistieken">
                <div class="highlight-metric">
                    <p class="metric-label">Aantal namen</p>
                    <p class="metric-value"><?php echo e((string) $totalNames); ?></p>
                </div>
                <div class="highlight-copy">
                    <h2>Maak een shortlist</h2>
                    <p>Gebruik deze lijst om jouw favorieten te verzamelen. Noteer opvallende namen, vergelijk betekenissen en kies de stijl die bij jullie gezin past.</p>
                </div>
            </section>

            <?php if ($errorMessage !== ''): ?>
                <p class="status-message"><?php echo e($errorMessage); ?></p>
            <?php endif; ?>

            <?php if (!empty($names)): ?>
                <div class="card-grid">
                    <?php foreach ($names as $entry): ?>
                        <article class="name-card">
                            <h2><?php echo e(isset($entry['name']) ? $entry['name'] : 'Onbekend'); ?></h2>
                            <?php if (!empty($entry['gender'])): ?>
                                <p class="meta"><span class="meta-label">Geslacht</span><?php echo e($entry['gender']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($entry['origin'])): ?>
                                <p class="meta"><span class="meta-label">Herkomst</span><?php echo e($entry['origin']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($entry['meaning'])): ?>
                                <p class="meaning"><?php echo e($entry['meaning']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($entry['popularity_rank'])): ?>
                                <p class="meta"><span class="meta-label">Populariteit</span>#<?php echo e((string) $entry['popularity_rank']); ?></p>
                            <?php elseif (!empty($entry['popularity'])): ?>
                                <p class="meta"><span class="meta-label">Populariteit</span><?php echo e((string) $entry['popularity']); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($entry['usage'])): ?>
                                <p class="meta"><span class="meta-label">Gebruik</span><?php echo e($entry['usage']); ?></p>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="status-message">Er zijn momenteel geen namen om te tonen.</p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php include dirname(__DIR__) . '/include/footer.php'; ?>
