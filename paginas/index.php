<?php
require_once dirname(__DIR__) . '/include/functions.php';
require_once dirname(__DIR__) . '/include/db.php';

$pdo = null;
$searchTerm = isset($_GET['query']) ? trim($_GET['query']) : '';
$searchResults = [];
$popularNames = [];
$errorMessage = '';
$tableExists = false;
$columnSet = [];
$selectList = '`name`';
$orderColumn = '`name`';
$whereParts = ['`name` LIKE :term'];

$fallbackNames = [];

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
        } elseif (isset($columnSet['popularity'])) {
            $orderColumn = '`popularity`';
        }

        $whereParts = ['`name` LIKE :term'];
        if (isset($columnSet['origin'])) {
            $whereParts[] = '`origin` LIKE :term';
        }
        if (isset($columnSet['meaning'])) {
            $whereParts[] = '`meaning` LIKE :term';
        }

        try {
            $popularStmt = $pdo->query("SELECT $selectList FROM `names` ORDER BY $orderColumn ASC LIMIT 6");
            $popularNames = $popularStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $popularException) {
            $popularNames = $fallbackNames;
        }
    } else {
        $popularNames = $fallbackNames;
    }
} catch (PDOException $exception) {
    $errorMessage = 'De databaseverbinding is mislukt. De zoekfunctie is tijdelijk niet beschikbaar.';
    $popularNames = $fallbackNames;
    $tableExists = false;
}

if ($searchTerm !== '') {
    if ($tableExists && $pdo instanceof PDO) {
        try {
            $searchSql = 'SELECT ' . $selectList . ' FROM `names` WHERE ' . implode(' OR ', $whereParts) . ' ORDER BY ' . $orderColumn . ' ASC LIMIT 20';
            $searchStmt = $pdo->prepare($searchSql);
            $searchStmt->execute(['term' => '%' . $searchTerm . '%']);
            $searchResults = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $searchException) {
            $errorMessage = 'Zoeken lukt niet. Probeer het later opnieuw.';
            $searchResults = [];
        }
    } else {
        $searchResults = array_values(array_filter($fallbackNames, function (array $row) use ($searchTerm) {
            $term = strtolower($searchTerm);
            $haystacks = [
                isset($row['name']) ? $row['name'] : '',
                isset($row['origin']) ? $row['origin'] : '',
                isset($row['meaning']) ? $row['meaning'] : '',
            ];

            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && stripos($haystack, $term) !== false) {
                    return true;
                }
            }

            return false;
        }));
    }
} elseif (empty($popularNames)) {
    $popularNames = $fallbackNames;
}

include dirname(__DIR__) . '/include/header.php';
?>
<main>
    <section id="hero" class="hero">
        <div class="container">
            <h1>Ontdek de perfecte voornaam</h1>
            <p>Zoek naar betekenissen, herkomst en trends. Vind nieuwe inspiratie en stel een favorietenlijst samen voor jouw gezin.</p>
            <form id="search" class="search-form" method="get" action="#results">
                <label class="visually-hidden" for="query">Zoek naar een voornaam</label>
                <div class="search-controls">
                    <input
                        id="query"
                        class="search-input"
                        type="text"
                        name="query"
                        value="<?php echo e($searchTerm); ?>"
                        placeholder="Bijvoorbeeld Mila, Elias of Noor"
                        autocomplete="off"
                    >
                    <button class="search-button" type="submit">Zoek namen</button>
                </div>
            </form>
            <div class="cta-group">
                <a class="cta-button" href="#popular">Bekijk populaire namen</a>
                <a class="cta-button secondary" href="#contact">Vraag persoonlijk advies</a>
            </div>
        </div>
    </section>

    <?php if ($errorMessage !== ''): ?>
        <section class="section section-error">
            <div class="container">
                <p class="status-message"><?php echo e($errorMessage); ?></p>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($searchTerm !== ''): ?>
        <section id="results" class="section">
            <div class="container">
                <header class="section-heading">
                    <h2>Zoekresultaten</h2>
                    <p>
                        <?php if (!empty($searchResults)): ?>
                            We vonden <?php echo count($searchResults); ?> passende naam<?php echo count($searchResults) === 1 ? '' : 'en'; ?>.
                        <?php else: ?>
                            Geen resultaten voor &ldquo;<?php echo e($searchTerm); ?>&rdquo;.
                        <?php endif; ?>
                    </p>
                </header>

                <?php if (!empty($searchResults)): ?>
                    <div class="card-grid">
                        <?php foreach ($searchResults as $entry): ?>
                            <article class="name-card">
                                <h3><?php echo e(isset($entry['name']) ? $entry['name'] : 'Onbekend'); ?></h3>
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
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="status-message">Probeer een andere spelling of filter op herkomst.</p>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>

    <section id="popular" class="section">
        <div class="container">
            <header class="section-heading">
                <h2>Populaire namen van dit moment</h2>
                <p>Een overzicht van veelgekozen voornamen in Nederland als startpunt voor je shortlist.</p>
            </header>
            <div class="card-grid">
                <?php foreach ($popularNames as $entry): ?>
                    <article class="name-card">
                        <h3><?php echo e(isset($entry['name']) ? $entry['name'] : 'Onbekend'); ?></h3>
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
                            <p class="meta"><span class="meta-label">Ranking</span>#<?php echo e((string) $entry['popularity_rank']); ?></p>
                        <?php elseif (!empty($entry['popularity'])): ?>
                            <p class="meta"><span class="meta-label">Populariteit</span><?php echo e((string) $entry['popularity']); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section id="contact" class="section section-contact">
        <div class="container">
            <div class="contact-card">
                <h2>Persoonlijk naamadvies</h2>
                <p>Twijfel je tussen meerdere opties of zoek je een naam met een specifieke betekenis? Laat je e-mailadres achter en ontvang inspiratie op maat.</p>
                <form class="contact-form" method="post" action="mailto:info@example.com">
                    <label class="visually-hidden" for="contact-email">E-mailadres</label>
                    <div class="contact-controls">
                        <input
                            id="contact-email"
                            class="contact-input"
                            type="email"
                            name="email"
                            placeholder="naam@voorbeeld.nl"
                            required
                        >
                        <button class="contact-button" type="submit">Verstuur</button>
                    </div>
                </form>
            </div>
        </div>
    </section>
</main>
<?php include dirname(__DIR__) . '/include/footer.php'; ?>
