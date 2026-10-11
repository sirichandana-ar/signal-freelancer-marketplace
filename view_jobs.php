<?php
require __DIR__ . '/db.php';

$search = trim((string) ($_GET['q'] ?? ''));
$category = trim((string) ($_GET['category'] ?? ''));
$where = ["j.status = 'open'", "u.account_status = 'active'"];
$params = [];
if ($search !== '') {
    $where[] = '(j.title LIKE :title_query OR j.description LIKE :description_query OR j.skills LIKE :skills_query OR u.name LIKE :client_query)';
    $needle = '%' . $search . '%';
    $params[':title_query'] = $needle;
    $params[':description_query'] = $needle;
    $params[':skills_query'] = $needle;
    $params[':client_query'] = $needle;
}
if ($category !== '') {
    $where[] = 'j.category = :category';
    $params[':category'] = $category;
}
$sql = 'SELECT j.*, u.name AS client_name FROM jobs j INNER JOIN users u ON u.id = j.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY j.created_at DESC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll();
$categories = $conn->query("SELECT DISTINCT category FROM jobs WHERE status = 'open' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

$profileSkills = '';
$appliedIds = [];
if (isLoggedIn() && ($_SESSION['role'] ?? '') === 'freelancer') {
    $profileStmt = $conn->prepare('SELECT skills FROM users WHERE id = :id');
    $profileStmt->execute([':id' => $_SESSION['user_id']]);
    $profileSkills = (string) $profileStmt->fetchColumn();
    $appliedStmt = $conn->prepare('SELECT job_id FROM applications WHERE freelancer_id = :id');
    $appliedStmt->execute([':id' => $_SESSION['user_id']]);
    $appliedIds = array_map('intval', $appliedStmt->fetchAll(PDO::FETCH_COLUMN));
}

pageStart('Discover open projects', 'jobs');
?>
<section class="page-heading jobs-heading">
    <div><span class="section-kicker">THE OPEN FIELD / <?php echo count($jobs); ?> PROJECTS</span><h1>Work worth doing.</h1><p>Good briefs leave room for good people. Find one that speaks your language.</p></div>
</section>
<form method="GET" class="search-panel">
    <label class="search-field"><span>⌕</span><input type="search" name="q" value="<?php echo safeText($search); ?>" placeholder="Search projects, skills, or people"></label>
    <select name="category"><option value="">Every discipline</option><?php foreach ($categories as $option): ?><option value="<?php echo safeText($option); ?>" <?php echo $category === $option ? 'selected' : ''; ?>><?php echo safeText($option); ?></option><?php endforeach; ?></select>
    <button type="submit" class="button-primary">Find a fit <span>↗</span></button>
    <?php if ($search !== '' || $category !== ''): ?><a class="clear-link" href="view_jobs.php">Clear</a><?php endif; ?>
</form>
<?php if ($search !== '' || $category !== ''): ?><p class="results-context">Showing <?php echo count($jobs); ?> open project<?php echo count($jobs) === 1 ? '' : 's'; ?><?php if ($search !== ''): ?> matching “<?php echo safeText($search); ?>”<?php endif; ?>.</p><?php endif; ?>
<section class="job-listing-grid">
    <?php if (!$jobs): ?><div class="panel empty-state"><span class="empty-icon">⌕</span><h2>Nothing in this orbit yet.</h2><p>Try a different search or come back soon for a fresh brief.</p><a class="text-link" href="view_jobs.php">See every open project ↗</a></div>
    <?php else: foreach ($jobs as $job): $fit = isLoggedIn() && ($_SESSION['role'] ?? '') === 'freelancer' ? skillFit($profileSkills, $job['skills']) : ['score' => null, 'matched' => [], 'missing' => []]; ?>
        <article class="job-card listing-card">
            <div class="row-top"><span class="category-chip"><?php echo safeText($job['category']); ?></span><?php if ($fit['score'] !== null): ?><span class="match-score"><?php echo $fit['score']; ?>% signal</span><?php endif; ?></div>
            <div class="job-card-title"><h2><?php echo safeText($job['title']); ?></h2><span class="budget-tag"><?php echo safeText($job['budget']); ?></span></div>
            <p class="client-name">A brief from <?php echo safeText($job['client_name']); ?> <span class="row-divider">·</span> <?php echo safeText(date('M j', strtotime($job['created_at']))); ?></p>
            <p class="listing-description"><?php echo nl2br(safeText($job['description'])); ?></p>
            <?php if (normalizedSkills($job['skills'])): ?><div class="skill-chips"><?php foreach (normalizedSkills($job['skills']) as $skill): ?><span class="skill-chip <?php echo in_array($skill, $fit['matched'], true) ? 'matched' : ''; ?>"><?php echo safeText(ucwords($skill)); ?></span><?php endforeach; ?></div><?php endif; ?>
            <?php if ($fit['score'] !== null): ?><p class="fit-explanation"><?php echo count($fit['matched']); ?> of <?php echo count($fit['matched']) + count($fit['missing']); ?> requested skills on your profile<?php if ($fit['missing']): ?> · missing: <?php echo safeText(implode(', ', $fit['missing'])); ?><?php endif; ?></p><?php endif; ?>
            <div class="listing-footer"><?php if (in_array((int) $job['id'], $appliedIds, true)): ?><span class="status-label reviewing">Proposal sent</span>
            <?php elseif (isLoggedIn() && ($_SESSION['role'] ?? '') === 'freelancer'): ?><a class="button-primary inline-button" href="apply_job.php?job_id=<?php echo (int) $job['id']; ?>">Read brief & propose ↗</a>
            <?php elseif (!isLoggedIn()): ?><a class="text-link" href="login.php">Sign in to send a proposal ↗</a><?php endif; ?></div>
        </article>
    <?php endforeach; endif; ?>
</section>
<?php pageEnd(); ?>
