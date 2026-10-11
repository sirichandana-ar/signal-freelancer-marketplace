<?php
require __DIR__ . '/db.php';
requireRole('client');

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = (int) ($_POST['application_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    if (!verifyCsrf()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (!in_array($newStatus, ['reviewing', 'shortlisted', 'accepted', 'declined'], true)) {
        $error = 'That proposal action is not available.';
    } else {
        $owned = $conn->prepare('SELECT a.id, a.job_id, j.status AS job_status FROM applications a INNER JOIN jobs j ON j.id = a.job_id WHERE a.id = :application_id AND j.user_id = :client_id LIMIT 1');
        $owned->execute([':application_id' => $applicationId, ':client_id' => $_SESSION['user_id']]);
        $application = $owned->fetch();
        if (!$application) {
            $error = 'That proposal is not part of your projects.';
        } elseif ($newStatus !== 'declined' && $newStatus !== 'accepted' && $application['job_status'] !== 'open') {
            $error = 'This project is closed, so its proposals can no longer be changed.';
        } elseif ($newStatus === 'accepted' && $application['job_status'] !== 'open') {
            $error = 'This project has already been closed.';
        } else {
            $conn->beginTransaction();
            try {
                $update = $conn->prepare('UPDATE applications SET status = :status WHERE id = :id');
                $update->execute([':status' => $newStatus, ':id' => $applicationId]);
                if ($newStatus === 'accepted') {
                    $close = $conn->prepare("UPDATE jobs SET status = 'filled' WHERE id = :job_id AND user_id = :client_id");
                    $close->execute([':job_id' => $application['job_id'], ':client_id' => $_SESSION['user_id']]);
                    $declineOthers = $conn->prepare("UPDATE applications SET status = 'declined' WHERE job_id = :job_id AND id <> :application_id AND status NOT IN ('accepted', 'declined')");
                    $declineOthers->execute([':job_id' => $application['job_id'], ':application_id' => $applicationId]);
                }
                $conn->commit();
                setFlash($newStatus === 'accepted' ? 'You made an offer. This project is now marked filled.' : 'Proposal moved to ' . $newStatus . '.');
                redirect($jobId > 0 ? 'manage_applications.php?job_id=' . $jobId : 'manage_applications.php');
            } catch (Throwable $error) {
                $conn->rollBack();
                throw $error;
            }
        }
    }
}

$jobsStmt = $conn->prepare('SELECT j.id, j.title, j.status, COUNT(a.id) AS proposal_count FROM jobs j LEFT JOIN applications a ON a.job_id = j.id WHERE j.user_id = :id GROUP BY j.id, j.title, j.status ORDER BY j.created_at DESC');
$jobsStmt->execute([':id' => $_SESSION['user_id']]);
$jobs = $jobsStmt->fetchAll();
$selectedJob = null;
if ($jobId > 0) {
    $selected = $conn->prepare('SELECT * FROM jobs WHERE id = :id AND user_id = :owner LIMIT 1');
    $selected->execute([':id' => $jobId, ':owner' => $_SESSION['user_id']]);
    $selectedJob = $selected->fetch();
    if (!$selectedJob) {
        redirect('manage_applications.php');
    }
}
$sql = 'SELECT a.*, u.name AS freelancer_name, u.email, u.bio, u.skills AS freelancer_skills, u.location, u.website, j.title AS job_title, j.skills AS job_skills, j.category, j.budget, j.status AS job_status FROM applications a INNER JOIN users u ON u.id = a.freelancer_id INNER JOIN jobs j ON j.id = a.job_id WHERE j.user_id = :client_id';
if ($jobId > 0) {
    $sql .= ' AND j.id = :job_id';
}
$sql .= ' ORDER BY CASE a.status WHEN \'submitted\' THEN 0 WHEN \'reviewing\' THEN 1 WHEN \'shortlisted\' THEN 2 ELSE 3 END, a.created_at DESC';
$proposalsStmt = $conn->prepare($sql);
$proposalsStmt->bindValue(':client_id', (int) $_SESSION['user_id'], PDO::PARAM_INT);
if ($jobId > 0) {
    $proposalsStmt->bindValue(':job_id', $jobId, PDO::PARAM_INT);
}
$proposalsStmt->execute();
$proposals = $proposalsStmt->fetchAll();

pageStart('Project rooms', 'dashboard');
?>
<section class="page-heading">
    <div><span class="section-kicker">CLIENT STUDIO / PROPOSALS</span><h1><?php echo $selectedJob ? safeText($selectedJob['title']) : 'Meet the people behind the proposals.'; ?></h1><p><?php echo $selectedJob ? 'A good proposal is the start of a good conversation.' : 'Thoughtful introductions for the work you put into the world.'; ?></p></div>
    <?php if ($selectedJob): ?><a class="button-quiet" href="manage_applications.php">All proposals ↗</a><?php endif; ?>
</section>
<?php renderFlash(); ?>
<?php if ($error !== ''): ?><div class="notice error"><?php echo safeText($error); ?></div><?php endif; ?>
<div class="project-room-layout">
    <aside class="panel project-switcher">
        <div class="panel-heading"><div><span class="section-kicker">YOUR PROJECTS</span><h2>Open a room</h2></div></div>
        <?php if (!$jobs): ?><div class="empty-state compact-empty"><p>Post your first brief to meet collaborators here.</p><a class="text-link" href="post_job.php">Write a brief ↗</a></div>
        <?php else: foreach ($jobs as $job): ?>
            <a class="room-link <?php echo ($selectedJob && (int) $selectedJob['id'] === (int) $job['id']) ? 'selected' : ''; ?>" href="manage_applications.php?job_id=<?php echo (int) $job['id']; ?>"><span><?php echo safeText($job['title']); ?><small><?php echo (int) $job['proposal_count']; ?> proposals · <?php echo safeText($job['status']); ?></small></span><span>↗</span></a>
        <?php endforeach; endif; ?>
    </aside>
    <section class="proposal-feed">
        <?php if (!$proposals): ?><div class="panel empty-state"><span class="empty-icon">✳</span><h3>No proposals just yet.</h3><p>Share your brief and check back as your project finds its people.</p></div>
        <?php else: foreach ($proposals as $proposal): $fit = skillFit($proposal['freelancer_skills'], $proposal['job_skills']); ?>
            <article class="panel proposal-card">
                <div class="proposal-head"><div class="freelancer-identity"><span class="avatar-large small-avatar"><?php echo safeText(strtoupper(substr($proposal['freelancer_name'], 0, 1))); ?></span><div><h2><?php echo safeText($proposal['freelancer_name']); ?></h2><p><?php echo safeText($proposal['location'] ?: 'Independent freelancer'); ?> <span class="row-divider">·</span> <?php echo safeText($proposal['job_title']); ?></p></div></div><span class="status-label <?php echo safeText($proposal['status']); ?>"><?php echo safeText($proposal['status']); ?></span></div>
                <?php if ($fit['score'] !== null): ?>
                    <div class="match-breakdown"><div class="match-meter"><span style="width: <?php echo $fit['score']; ?>%"></span></div><strong><?php echo $fit['score']; ?>% skill signal</strong><p><?php echo count($fit['matched']); ?> of <?php echo count($fit['matched']) + count($fit['missing']); ?> requested skills appear on this profile.</p>
                        <div class="skill-chips"><?php foreach ($fit['matched'] as $skill): ?><span class="skill-chip matched"><?php echo safeText(ucwords($skill)); ?> ✓</span><?php endforeach; ?><?php foreach ($fit['missing'] as $skill): ?><span class="skill-chip"><?php echo safeText(ucwords($skill)); ?></span><?php endforeach; ?></div>
                    </div>
                <?php endif; ?>
                <blockquote class="proposal-message"><?php echo nl2br(safeText($proposal['message'])); ?></blockquote>
                <?php if ($proposal['proposed_budget']): ?><p class="proposal-budget">Proposed budget <strong><?php echo safeText($proposal['proposed_budget']); ?></strong></p><?php endif; ?>
                <div class="proposal-footer"><div class="profile-quicklinks"><?php if ($proposal['email']): ?><a href="mailto:<?php echo safeText($proposal['email']); ?>">Email freelancer ↗</a><?php endif; ?><?php if ($proposal['website']): ?><a href="<?php echo safeText($proposal['website']); ?>" target="_blank" rel="noopener noreferrer">Portfolio ↗</a><?php endif; ?></div>
                    <?php if ($proposal['job_status'] === 'open' && !in_array($proposal['status'], ['accepted', 'declined'], true)): ?>
                        <div class="decision-actions">
                            <?php if ($proposal['status'] === 'submitted'): ?><form method="POST"><?php echo csrfField(); ?><input type="hidden" name="application_id" value="<?php echo (int) $proposal['id']; ?>"><input type="hidden" name="status" value="reviewing"><button class="button-quiet" type="submit">Mark reviewing</button></form><?php endif; ?>
                            <form method="POST"><?php echo csrfField(); ?><input type="hidden" name="application_id" value="<?php echo (int) $proposal['id']; ?>"><input type="hidden" name="status" value="shortlisted"><button class="button-quiet" type="submit">Shortlist</button></form>
                            <form method="POST"><?php echo csrfField(); ?><input type="hidden" name="application_id" value="<?php echo (int) $proposal['id']; ?>"><input type="hidden" name="status" value="declined"><button class="button-quiet" type="submit">Pass</button></form>
                            <form method="POST"><?php echo csrfField(); ?><input type="hidden" name="application_id" value="<?php echo (int) $proposal['id']; ?>"><input type="hidden" name="status" value="accepted"><button class="button-primary inline-button" type="submit">Make an offer ↗</button></form>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; endif; ?>
    </section>
</div>
<?php pageEnd(); ?>
