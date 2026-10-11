<?php
session_start();

$dbConfig = [
    'driver' => getenv('DB_DRIVER') ?: 'sqlite',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'freelancer_db',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
];

$allowedDrivers = ['mysql', 'sqlite'];
if (!in_array($dbConfig['driver'], $allowedDrivers, true)) {
    throw new InvalidArgumentException('DB_DRIVER must be mysql or sqlite.');
}

$usingSqlite = false;
$sqlitePath = getenv('DB_SQLITE_PATH') ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'signal_freelancer' . DIRECTORY_SEPARATOR . 'freelancer.db';
if ($dbConfig['driver'] === 'mysql') {
    $conn = new PDO(
        'mysql:host=' . $dbConfig['host'] . ';port=' . $dbConfig['port'] . ';dbname=' . $dbConfig['database'] . ';charset=utf8mb4',
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} else {
    if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('SQLite is selected but the PDO SQLite extension is not enabled.');
    }
    $sqliteDirectory = dirname($sqlitePath);
    if (!is_dir($sqliteDirectory) && !mkdir($sqliteDirectory, 0770, true) && !is_dir($sqliteDirectory)) {
        throw new RuntimeException('Could not create the SQLite database directory: ' . $sqliteDirectory);
    }
    $conn = new PDO('sqlite:' . $sqlitePath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $conn->exec('PRAGMA foreign_keys = ON');
    $usingSqlite = true;
}

$idDefinition = $usingSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
$timeDefinition = $usingSqlite ? 'DATETIME DEFAULT CURRENT_TIMESTAMP' : 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
$conn->exec("CREATE TABLE IF NOT EXISTS users (
    id $idDefinition,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NULL,
    password VARCHAR(255) NULL,
    role VARCHAR(30) NOT NULL DEFAULT 'freelancer',
    account_status VARCHAR(30) NOT NULL DEFAULT 'active',
    bio TEXT NULL,
    skills TEXT NULL,
    location VARCHAR(120) NULL,
    website VARCHAR(255) NULL,
    created_at $timeDefinition
)");
$conn->exec("CREATE TABLE IF NOT EXISTS jobs (
    id $idDefinition,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    budget VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL DEFAULT 'General',
    skills TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open',
    created_at $timeDefinition,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");
$conn->exec("CREATE TABLE IF NOT EXISTS applications (
    id $idDefinition,
    job_id INT NOT NULL,
    freelancer_id INT NOT NULL,
    message TEXT NOT NULL,
    proposed_budget VARCHAR(100) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'submitted',
    created_at $timeDefinition,
    FOREIGN KEY (job_id) REFERENCES jobs(id),
    FOREIGN KEY (freelancer_id) REFERENCES users(id)
)");
$conn->exec("CREATE TABLE IF NOT EXISTS admin_events (
    id $idDefinition,
    administrator_id INT NOT NULL,
    action VARCHAR(80) NOT NULL,
    target_type VARCHAR(40) NOT NULL,
    target_id INT NOT NULL,
    details TEXT NULL,
    created_at $timeDefinition,
    FOREIGN KEY (administrator_id) REFERENCES users(id)
)");

function ensureColumn(PDO $conn, bool $usingSqlite, string $table, string $column, string $definition): void
{
    if ($usingSqlite) {
        $columns = $conn->query('PRAGMA table_info(' . $table . ')')->fetchAll();
        foreach ($columns as $existing) {
            if ($existing['name'] === $column) {
                return;
            }
        }
    } else {
        $stmt = $conn->query('SHOW COLUMNS FROM ' . $table);
        foreach ($stmt->fetchAll() as $existing) {
            if ($existing['Field'] === $column) {
                return;
            }
        }
    }

    $conn->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

$migrations = [
    'users' => [
        'account_status' => "VARCHAR(30) NOT NULL DEFAULT 'active'",
        'bio' => 'TEXT NULL',
        'skills' => 'TEXT NULL',
        'location' => 'VARCHAR(120) NULL',
        'website' => 'VARCHAR(255) NULL',
    ],
    'jobs' => [
        'category' => "VARCHAR(100) NOT NULL DEFAULT 'General'",
        'skills' => 'TEXT NULL',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'open'",
    ],
    'applications' => [
        'proposed_budget' => 'VARCHAR(100) NULL',
        'status' => "VARCHAR(30) NOT NULL DEFAULT 'submitted'",
    ],
];
foreach ($migrations as $table => $columns) {
    foreach ($columns as $column => $definition) {
        ensureColumn($conn, $usingSqlite, $table, $column, $definition);
    }
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit();
}

function safeText(?string $value): string
{
    return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
}

function currentUser(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => (string) ($_SESSION['name'] ?? ''),
        'role' => (string) ($_SESSION['role'] ?? 'freelancer'),
    ];
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireAuth(): void
{
    if (!isLoggedIn()) {
        redirect('login.php');
    }

    global $conn;
    $stmt = $conn->prepare('SELECT id, name, role, account_status FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user || $user['account_status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        redirect('login.php?suspended=1');
    }
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
}

function requireRole(string $role): void
{
    requireAuth();
    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('You do not have permission to open this page.');
    }
}

function requireAdministrator(): void
{
    requireRole('admin');
}

function requireAdmin(): void
{
    requireAdministrator();
}

function dashboardPath(?string $role = null): string
{
    $role = $role ?? (string) ($_SESSION['role'] ?? '');
    if ($role === 'admin') {
        return 'admin_panel.php';
    }
    if ($role === 'client') {
        return 'client_dashboard.php';
    }
    return 'freelancer_dashboard.php';
}

function csrfToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . safeText(csrfToken()) . '">';
}

function verifyCsrf(): bool
{
    return isset($_POST['csrf_token'], $_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token']);
}

function setFlash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function renderFlash(): void
{
    if (!isset($_SESSION['flash'])) {
        return;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    echo '<div class="notice ' . safeText($flash['type']) . '">' . safeText($flash['message']) . '</div>';
}

function passwordMatches(string $plainPassword, ?string $storedPassword): bool
{
    return $storedPassword !== null && $storedPassword !== '' && password_verify($plainPassword, $storedPassword);
}

function normalizedSkills(?string $skills): array
{
    $items = preg_split('/[,;\n]+/', strtolower((string) $skills)) ?: [];
    $items = array_map('trim', $items);
    return array_values(array_unique(array_filter($items, static fn(string $skill): bool => $skill !== '')));
}

function skillFit(?string $freelancerSkills, ?string $jobSkills): array
{
    $freelancer = normalizedSkills($freelancerSkills);
    $required = normalizedSkills($jobSkills);
    if (!$required) {
        return ['score' => null, 'matched' => [], 'missing' => []];
    }

    $matched = array_values(array_intersect($required, $freelancer));
    $missing = array_values(array_diff($required, $freelancer));
    return [
        'score' => (int) round(count($matched) / count($required) * 100),
        'matched' => $matched,
        'missing' => $missing,
    ];
}

function pageStart(string $title, string $active = ''): void
{
    $user = currentUser();
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . safeText($title) . ' · Signal</title><link rel="stylesheet" href="frontend/style.css"></head><body>';
    echo '<div class="app-shell"><header class="topbar"><a class="brand" href="index.php"><span class="brand-mark">S</span><span>signal<span class="brand-dot">.</span><small>INDEPENDENT WORK, IN SYNC</small></span></a>';
    echo '<nav class="nav-links"><a class="' . ($active === 'home' ? 'active' : '') . '" href="index.php">Discover</a><a class="' . ($active === 'jobs' ? 'active' : '') . '" href="view_jobs.php">Find work</a>';
    if ($user) {
        $workspaceLabel = $user['role'] === 'admin' ? 'Admin panel' : 'Workspace';
        echo '<a class="' . ($active === 'dashboard' ? 'active' : '') . '" href="' . safeText(dashboardPath($user['role'])) . '">' . $workspaceLabel . '</a>';
        if ($user['role'] !== 'admin') {
            echo '<a class="' . ($active === 'profile' ? 'active' : '') . '" href="profile.php">My profile</a>';
        }
        if ($user['role'] === 'client') {
            echo '<a class="' . ($active === 'talent' ? 'active' : '') . '" href="talent.php">Find talent</a>';
            echo '<a class="' . ($active === 'post' ? 'active' : '') . '" href="post_job.php">Post a brief</a>';
        }
        echo '<span class="nav-user">' . safeText($user['name']) . '</span><form class="logout-form" method="POST" action="logout.php">' . csrfField() . '<button type="submit" class="nav-signout">Sign out</button></form>';
    } else {
        echo '<a class="' . ($active === 'login' ? 'active' : '') . '" href="login.php">Sign in</a><a class="nav-join' . ($active === 'register' ? ' active' : '') . '" href="register.php">Join Signal <span>↗</span></a>';
    }
    echo '</nav></header><main>';
}

function pageEnd(): void
{
    echo '</main><footer class="site-footer"><span>signal. · Independent work, in sync.</span><span>Make good work happen.</span></footer></div></body></html>';
}
