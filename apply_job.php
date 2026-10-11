<?php
require __DIR__ . '/db.php';
requireRole('freelancer');

$jobId = isset($_GET['job_id']) ? (int) $_GET['job_id'] : 0;
$jobQuery = $conn->prepare("SELECT j.*, u.name AS client_name FROM jobs j INNER JOIN users u ON u.id = j.user_id WHERE j.id = :job_id AND j.status = 'open' AND u.account_status = 'active' LIMIT 1");
$jobQuery->execute([':job_id' => $jobId]);
$job = $jobQuery->fetch();
if (!$job) {
    setFlash('That project is no longer open for proposals.', 'info');
    redirect('view_jobs.php');
}

$profileQuery = $conn->prepare('SELECT skills FROM users WHERE id = :id');
$profileQuery->execute([':id' => $_SESSION['user_id']]);
$profileSkills = (string) $profileQuery->fetchColumn();
$fit = skillFit($profileSkills, $job['skills']);
$error = '';
$proposalText = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $proposalText = trim((string) ($_POST['message'] ?? ''));
    $proposedBudget = trim((string) ($_POST['proposed_budget'] ?? ''));
    if (!verifyCsrf()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ($proposalText === '') {
        $error = 'Tell the client why this project feels right for you.';
    } elseif (strlen($proposalText) > 5000 || strlen($proposedBudget) > 100) {
        $error = 'Your proposal or budget is too long.';
    } else {
        $existing = $conn->prepare('SELECT id FROM applications WHERE job_id = :job_id AND freelancer_id = :freelancer_id LIMIT 1');
        $existing->execute([':job_id' => $jobId, ':freelancer_id' => $_SESSION['user_id']]);
        if ($existing->fetch()) {
            $error = 'You have already sent a proposal for this project.';
        } else {
            $stmt = $conn->prepare('INSERT INTO applications (job_id, freelancer_id, message, proposed_budget, status) VALUES (:job_id, :freelancer_id, :message, :proposed_budget, \'submitted\')');
            $stmt->execute([
                ':job_id' => $jobId,
                ':freelancer_id' => $_SESSION['user_id'],
                ':message' => $proposalText,
                ':proposed_budget' => $proposedBudget !== '' ? $proposedBudget : null,
            ]);
            setFlash('Proposal sent. You can follow its progress from your workspace.');
            redirect('dashboard.php');
        }
    }
}

pageStart('Send a proposal', 'jobs');
?>
<section class="page-heading">
    <div><span class="section-kicker"><?php echo safeText($job['category']); ?> / PROJECT BRIEF</span><h1><?php echo safeText($job['title']); ?></h1><p>From <?php echo safeText($job['client_name']); ?> <span class="row-divider">·</span> Budget <?php echo safeText($job['budget']); ?></p></div>
    <a class="button-quiet" href="view_jobs.php">← All projects</a>
</section>
<div class="content-grid apply-layout">
    <article class="panel project-brief"><span class="section-kicker">THE CLIENT'S BRIEF</span><h2>What we're making together</h2><p class="brief-copy"><?php echo nl2br(safeText($job['description'])); ?></p>
        <?php if (normalizedSkills($job['skills'])): ?><h3>Skills in the mix</h3><div class="skill-chips"><?php foreach (normalizedSkills($job['skills']) as $skill): ?><span class="skill-chip <?php echo in_array($skill, $fit['matched'], true) ? 'matched' : ''; ?>"><?php echo safeText(ucwords($skill)); ?></span><?php endforeach; ?></div><?php endif; ?>
        <?php if ($fit['score'] !== null): ?><div class="match-breakdown apply-match"><strong>Your Signal fit: <?php echo $fit['score']; ?>%</strong><p><?php echo count($fit['matched']); ?> of <?php echo count($fit['matched']) + count($fit['missing']); ?> requested skills match your profile. This is a guide, not a hiring decision.</p></div><?php endif; ?>
    </article>
    <section class="panel form-panel">
        <div class="panel-heading"><div><span class="section-kicker">YOUR INTRODUCTION</span><h2>Send a proposal</h2></div><span class="step-number">↗</span></div>
        <?php if ($error !== ''): ?><div class="notice error"><?php echo safeText($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <label>Your note to the client<textarea name="message" rows="8" maxlength="5000" placeholder="What caught your attention? How would you approach this work?" required><?php echo safeText($proposalText); ?></textarea></label>
            <label>Your proposed budget <span class="label-note">OPTIONAL</span><input type="text" name="proposed_budget" value="<?php echo safeText($_POST['proposed_budget'] ?? ''); ?>" placeholder="e.g. $1,500 fixed"></label>
            <button type="submit" class="button-primary">Send proposal <span>↗</span></button>
        </form>
        <p class="form-footnote">Your profile skills and portfolio will be visible to the project owner.</p>
    </section>
</div>
<?php pageEnd(); ?>
