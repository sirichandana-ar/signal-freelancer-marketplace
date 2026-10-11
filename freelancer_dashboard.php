<?php
require __DIR__ . '/db.php';
requireRole('freelancer');

$userId = (int) $_SESSION['user_id'];
$profileQuery = $conn->prepare('SELECT skills FROM users WHERE id = :id');
$profileQuery->execute([':id' => $userId]);
$profileSkills = (string) $profileQuery->fetchColumn();
$statsQuery = $conn->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) AS waiting_count, SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) AS accepted_count FROM applications WHERE freelancer_id = :id");
$statsQuery->execute([':id' => $userId]);
$stats = $statsQuery->fetch();
$appsQuery = $conn->prepare('SELECT a.*, j.title AS job_title, j.budget, j.skills AS job_skills, j.category, j.status AS job_status, u.name AS client_name FROM applications a INNER JOIN jobs j ON j.id = a.job_id INNER JOIN users u ON u.id = j.user_id WHERE a.freelancer_id = :id ORDER BY a.created_at DESC LIMIT 8');
$appsQuery->execute([':id' => $userId]);
$applications = $appsQuery->fetchAll();
$jobsQuery = $conn->prepare("SELECT j.*, u.name AS client_name FROM jobs j INNER JOIN users u ON u.id = j.user_id WHERE j.status = 'open' AND u.account_status = 'active' AND NOT EXISTS (SELECT 1 FROM applications a WHERE a.job_id = j.id AND a.freelancer_id = :id) ORDER BY j.created_at DESC LIMIT 4");
$jobsQuery->execute([':id' => $userId]);
$recommendations = $jobsQuery->fetchAll();

pageStart('Freelancer workspace', 'dashboard');
?>
<section class="welcome-row">
    <div><span class="section-kicker">FREELANCER WORKSPACE / YOUR OVERVIEW</span><h1>Good to see you, <?php echo safeText($_SESSION['name']); ?>.</h1><p>Your next good-fit project could be one clear signal away.</p></div>
    <div class="avatar-large"><?php echo safeText(strtoupper(substr($_SESSION['name'], 0, 1))); ?></div>
</section>
<?php renderFlash(); ?>
<section class="stats-grid">
    <article class="stat-card"><span>PROPOSALS SENT</span><strong><?php echo (int) ($stats['total'] ?? 0); ?></strong><small><?php echo (int) ($stats['waiting_count'] ?? 0); ?> awaiting a response</small></article>
    <article class="stat-card lime"><span>PROJECTS WON</span><strong><?php echo (int) ($stats['accepted_count'] ?? 0); ?></strong><small>Your good work is getting noticed</small></article>
    <article class="stat-card"><span>YOUR SKILLS</span><strong class="stat-text"><?php echo count(normalizedSkills($profileSkills)); ?> on your profile</strong><small><a class="text-link" href="profile.php">Tune your signal ↗</a></small></article>
</section>
<div class="workspace-grid">
    <section class="panel">
        <div class="panel-heading"><div><span class="section-kicker">YOUR RECENT OUTREACH</span><h2>Proposal tracker</h2></div><a class="text-link" href="view_jobs.php">Explore work ↗</a></div>
        <?php if (!$applications): ?><div class="empty-state"><span class="empty-icon">↗</span><h3>Your next move is out there.</h3><p>Browse thoughtful briefs and send a proposal that sounds like you.</p><a class="button-primary inline-button" href="view_jobs.php">Explore open projects ↗</a></div>
        <?php else: foreach ($applications as $application): $fit = skillFit($profileSkills, $application['job_skills']); ?>
            <article class="proposal-row"><div class="proposal-row-main"><div class="row-top"><span class="category-chip"><?php echo safeText($application['category']); ?></span><span class="status-label <?php echo safeText($application['status']); ?>"><?php echo safeText($application['status']); ?></span></div><h3><?php echo safeText($application['job_title']); ?></h3><p><?php echo safeText($application['client_name']); ?> <span class="row-divider">·</span> Sent <?php echo safeText(date('M j, Y', strtotime($application['created_at']))); ?></p></div><?php if ($fit['score'] !== null): ?><div class="fit-pill"><?php echo $fit['score']; ?><small>% fit</small></div><?php endif; ?></article>
        <?php endforeach; endif; ?>
    </section>
    <aside class="panel recommendation-panel">
        <div class="panel-heading"><div><span class="section-kicker">A FEW OPEN DOORS</span><h2>Fresh briefs</h2></div></div>
        <?php if (!$recommendations): ?><div class="empty-state compact-empty"><p>You are caught up. Check back for new projects.</p></div>
        <?php else: foreach ($recommendations as $job): $fit = skillFit($profileSkills, $job['skills']); ?>
            <article class="project-row"><div class="row-top"><span class="category-chip"><?php echo safeText($job['category']); ?></span><?php if ($fit['score'] !== null): ?><span class="match-score"><?php echo $fit['score']; ?>% signal</span><?php endif; ?></div><h3><?php echo safeText($job['title']); ?></h3><p><?php echo safeText($job['budget']); ?> <span class="row-divider">·</span> <?php echo safeText($job['client_name']); ?></p><a class="text-link" href="apply_job.php?job_id=<?php echo (int) $job['id']; ?>">Read the brief ↗</a></article>
        <?php endforeach; endif; ?>
    </aside>
</div>
<?php pageEnd(); ?>
