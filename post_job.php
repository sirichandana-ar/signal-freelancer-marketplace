<?php
require __DIR__ . '/db.php';
requireRole('client');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'set_job_status') {
    $jobId = (int) ($_POST['job_id'] ?? 0);
    $newStatus = (string) ($_POST['status'] ?? '');
    if (!verifyCsrf()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (!in_array($newStatus, ['open', 'paused'], true)) {
        $error = 'That project status is not available.';
    } else {
        $update = $conn->prepare("UPDATE jobs SET status = :status WHERE id = :id AND user_id = :user_id AND status IN ('open', 'paused')");
        $update->execute([':status' => $newStatus, ':id' => $jobId, ':user_id' => $_SESSION['user_id']]);
        if ($update->rowCount() === 0) {
            $error = 'Only your open or paused projects can be changed.';
        } else {
            setFlash($newStatus === 'open' ? 'Your project is open for new proposals again.' : 'Your project is paused. New proposals are temporarily closed.');
            redirect('post_job.php');
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $budget = trim((string) ($_POST['budget'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $skills = trim((string) ($_POST['skills'] ?? ''));
    if (!verifyCsrf()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ($title === '' || $description === '' || $budget === '' || $category === '') {
        $error = 'Add a title, project brief, budget, and category to publish your post.';
    } elseif (strlen($title) > 150 || strlen($category) > 100 || strlen($budget) > 100) {
        $error = 'One or more fields are too long. Please shorten them and try again.';
    } else {
        $stmt = $conn->prepare('INSERT INTO jobs (user_id, title, description, budget, category, skills, status) VALUES (:user_id, :title, :description, :budget, :category, :skills, \'open\')');
        $stmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':title' => $title,
            ':description' => $description,
            ':budget' => $budget,
            ':category' => $category,
            ':skills' => $skills,
        ]);
        setFlash('Your project is live. Great collaborators can now find it.');
        redirect('post_job.php');
    }
}

$stmt = $conn->prepare('SELECT j.*, (SELECT COUNT(*) FROM applications a WHERE a.job_id = j.id) AS applicant_count FROM jobs j WHERE j.user_id = :user_id ORDER BY j.created_at DESC');
$stmt->execute([':user_id' => $_SESSION['user_id']]);
$jobs = $stmt->fetchAll();

pageStart('Publish a project', 'post');
?>
<section class="page-heading">
    <div><span class="section-kicker">CLIENT STUDIO / NEW PROJECT</span><h1>Write a brief people can feel.</h1><p>Specific, human briefs bring the right people into your orbit.</p></div>
    <a href="manage_applications.php" class="button-quiet">Review proposals <span>↗</span></a>
</section>
<div class="content-grid publish-grid">
    <section class="panel form-panel">
        <div class="panel-heading"><div><span class="section-kicker">THE BRIEF</span><h2>Project details</h2></div><span class="step-number">01</span></div>
        <?php renderFlash(); ?>
        <?php if ($error !== ''): ?><div class="notice error"><?php echo safeText($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <label>Project title<input type="text" name="title" value="<?php echo safeText($_POST['title'] ?? ''); ?>" placeholder="e.g. A brand identity with a little more soul" maxlength="150" required></label>
            <label>What are you making together?<textarea name="description" rows="7" placeholder="Share the context, goals, deliverables, and what a successful outcome looks like." required><?php echo safeText($_POST['description'] ?? ''); ?></textarea></label>
            <div class="form-row">
                <label>Budget or range<input type="text" name="budget" value="<?php echo safeText($_POST['budget'] ?? ''); ?>" placeholder="e.g. $1,200–1,800" required></label>
                <label>Project type<select name="category" required>
                    <option value="">Choose a lane</option>
                    <?php foreach (['Brand & design', 'Development', 'Writing & content', 'Marketing', 'Product & strategy', 'Video & motion', 'Other'] as $category): ?>
                        <option value="<?php echo safeText($category); ?>" <?php echo ($_POST['category'] ?? '') === $category ? 'selected' : ''; ?>><?php echo safeText($category); ?></option>
                    <?php endforeach; ?>
                </select></label>
            </div>
            <label>Skills to bring to the table <span class="label-note">COMMA SEPARATED</span><input type="text" name="skills" value="<?php echo safeText($_POST['skills'] ?? ''); ?>" placeholder="Figma, UX research, prototyping"></label>
            <button type="submit" class="button-primary">Publish project <span>↗</span></button>
        </form>
    </section>
    <aside class="panel side-panel">
        <div class="panel-heading"><div><span class="section-kicker">YOUR PROJECT BOARD</span><h2>Project board</h2></div><span class="counter"><?php echo count($jobs); ?></span></div>
        <?php if (!$jobs): ?>
            <div class="empty-state"><span class="empty-icon">✳</span><h3>A blank canvas.</h3><p>Your first published project will show up here.</p></div>
        <?php else: ?>
            <?php foreach ($jobs as $job): ?>
                <article class="project-row">
                    <div class="row-top"><span class="category-chip"><?php echo safeText($job['category']); ?></span><span class="status-dot"><?php echo safeText($job['status']); ?></span></div>
                    <h3><?php echo safeText($job['title']); ?></h3>
                    <p><?php echo safeText($job['budget']); ?> <span class="row-divider">·</span> <?php echo (int) $job['applicant_count']; ?> proposals</p>
                    <div class="job-management-actions">
                        <a class="text-link" href="manage_applications.php?job_id=<?php echo (int) $job['id']; ?>">Open project room <span>↗</span></a>
                        <?php if (in_array($job['status'], ['open', 'paused'], true)): ?>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="set_job_status">
                                <input type="hidden" name="job_id" value="<?php echo (int) $job['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo $job['status'] === 'open' ? 'paused' : 'open'; ?>">
                                <button type="submit" class="button-quiet"><?php echo $job['status'] === 'open' ? 'Pause proposals' : 'Reopen project'; ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </aside>
</div>
<?php pageEnd(); ?>
