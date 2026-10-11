<?php
require __DIR__ . '/db.php';
requireRole('client');

$userId = (int) $_SESSION['user_id'];
$statsQuery = $conn->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count FROM jobs WHERE user_id = :id");
$statsQuery->execute([':id' => $userId]);
$stats = $statsQuery->fetch();
$proposalQuery = $conn->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN a.status = 'submitted' THEN 1 ELSE 0 END) AS new_count FROM applications a INNER JOIN jobs j ON j.id = a.job_id WHERE j.user_id = :id");
$proposalQuery->execute([':id' => $userId]);
$proposalStats = $proposalQuery->fetch();
$jobsQuery = $conn->prepare('SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicant_count FROM jobs j WHERE j.user_id = :id ORDER BY j.created_at DESC LIMIT 8');
$jobsQuery->execute([':id' => $userId]);
$jobs = $jobsQuery->fetchAll();
$recentQuery = $conn->prepare('SELECT a.*, j.title AS job_title, u.name AS freelancer_name FROM applications a INNER JOIN jobs j ON j.id = a.job_id INNER JOIN users u ON u.id = a.freelancer_id WHERE j.user_id = :id ORDER BY a.created_at DESC LIMIT 5');
$recentQuery->execute([':id' => $userId]);
$recentProposals = $recentQuery->fetchAll();

pageStart('Client studio', 'dashboard');
?>
<section class="welcome-row">
    <div><span class="section-kicker">CLIENT STUDIO / YOUR WORKSPACE</span><h1>Good to see you, <?php echo safeText($_SESSION['name']); ?>.</h1><p>Bring good people into the work you put into the world.</p></div>
    <div class="avatar-large"><?php echo safeText(strtoupper(substr($_SESSION['name'], 0, 1))); ?></div>
</section>
<?php renderFlash(); ?>
<section class="stats-grid">
    <article class="stat-card"><span>PROJECTS POSTED</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong><small><?php echo (int) ($stats['open_count'] ?? 0); ?> open for proposals</small></article>
    <article class="stat-card lime"><span>PROPOSALS IN YOUR INBOX</span><strong><?php echo (int) ($proposalStats['total'] ?? 0); ?></strong><small><?php echo (int) ($proposalStats['new_count'] ?? 0); ?> new and waiting</small></article>
    <article class="stat-card"><span>YOUR NEXT STEP</span><strong class="stat-text">Meet your people.</strong><small>Review a proposal and keep work moving.</small></article>
</section>
<div class="workspace-grid">
    <section class="panel">
        <div class="panel-heading"><div><span class="section-kicker">WHAT'S IN MOTION</span><h2>Your project board</h2></div><div class="panel-links"><a class="text-link" href="talent.php">Find talent ↗</a><a class="text-link" href="post_job.php">New brief ↗</a></div></div>
        <?php if (!$jobs): ?><div class="empty-state"><span class="empty-icon">✳</span><h3>Start with a spark.</h3><p>A clear project brief is the first step to finding your people.</p><a class="button-primary inline-button" href="post_job.php">Write your first brief ↗</a></div>
        <?php else: foreach ($jobs as $job): ?>
            <article class="project-row">
                <div class="row-top"><span class="category-chip"><?php echo safeText($job['category']); ?></span><span class="status-dot"><?php echo safeText($job['status']); ?></span></div>
                <h3><?php echo safeText($job['title']); ?></h3><p><?php echo safeText($job['budget']); ?> <span class="row-divider">·</span> <?php echo (int) $job['applicant_count']; ?> proposals</p>
                <div class="job-management-actions"><a class="text-link" href="manage_applications.php?job_id=<?php echo (int) $job['id']; ?>">Open project room ↗</a>
                    <?php if (in_array($job['status'], ['open', 'paused'], true)): ?><form method="POST" action="post_job.php"><?php echo csrfField(); ?><input type="hidden" name="action" value="set_job_status"><input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>"><input type="hidden" name="status" value="<?php echo $job['status'] === 'open' ? 'paused' : 'open'; ?>"><button class="button-quiet" type="submit"><?php echo $job['status'] === 'open' ? 'Pause' : 'Reopen'; ?></button></form><?php endif; ?>
                </div>
            </article>
        <?php endforeach; endif; ?>
    </section>
    <section class="panel">
        <div class="panel-heading"><div><span class="section-kicker">A NOTE FROM THE FIELD</span><h2>Latest proposals</h2></div><a class="text-link" href="manage_applications.php">All proposals ↗</a></div>
        <?php if (!$recentProposals): ?><div class="empty-state compact-empty"><span class="empty-icon">✳</span><p>Your next great collaborator will show up here.</p></div>
        <?php else: foreach ($recentProposals as $proposal): ?>
            <article class="proposal-mini"><span class="proposal-avatar"><?php echo safeText(strtoupper(substr($proposal['freelancer_name'], 0, 1))); ?></span><div><strong><?php echo safeText($proposal['freelancer_name']); ?></strong><small><?php echo safeText($proposal['job_title']); ?></small></div><span class="status-label <?php echo safeText($proposal['status']); ?>"><?php echo safeText($proposal['status']); ?></span></article>
        <?php endforeach; endif; ?>
    </section>
</div>
<?php pageEnd(); ?>
