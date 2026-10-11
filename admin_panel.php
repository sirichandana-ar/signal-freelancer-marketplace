<?php
require __DIR__ . '/db.php';
requireAdministrator();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $targetId = (int) ($_POST['target_id'] ?? 0);
    if (!verifyCsrf()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ($action === 'set_account_status') {
        $status = (string) ($_POST['status'] ?? '');
        if ($targetId <= 0 || !in_array($status, ['active', 'suspended'], true)) {
            $error = 'Choose an available account action.';
        } elseif ($targetId === (int) $_SESSION['user_id']) {
            $error = 'You cannot suspend your own administrator account.';
        } else {
            $accountQuery = $conn->prepare('SELECT id, role, account_status FROM users WHERE id = :id LIMIT 1');
            $accountQuery->execute([':id' => $targetId]);
            $account = $accountQuery->fetch();
            if (!$account) {
                $error = 'That account could not be found.';
            } elseif ($account['role'] === 'admin') {
                $error = 'Administrator accounts cannot be suspended from the admin panel.';
            } elseif ($account['account_status'] === $status) {
                $error = 'That account already has the selected status.';
            } else {
                $conn->beginTransaction();
                try {
                    $update = $conn->prepare('UPDATE users SET account_status = :status WHERE id = :id');
                    $update->execute([':status' => $status, ':id' => $targetId]);
                    $audit = $conn->prepare('INSERT INTO admin_events (administrator_id, action, target_type, target_id, details) VALUES (:actor, :action, \'account\', :target, :details)');
                    $audit->execute([
                        ':actor' => (int) $_SESSION['user_id'],
                        ':action' => $status === 'suspended' ? 'account_suspended' : 'account_reactivated',
                        ':target' => $targetId,
                        ':details' => 'Previous status: ' . $account['account_status'] . '; new status: ' . $status,
                    ]);
                    $conn->commit();
                    setFlash($status === 'suspended' ? 'Account suspended. Its owner will be signed out on their next request.' : 'Account reactivated.');
                    redirect('admin_panel.php');
                } catch (Throwable $exception) {
                    $conn->rollBack();
                    throw $exception;
                }
            }
        }
    } elseif ($action === 'set_job_status') {
        $status = (string) ($_POST['status'] ?? '');
        if ($targetId <= 0 || !in_array($status, ['open', 'removed'], true)) {
            $error = 'Choose an available project moderation action.';
        } else {
            $jobQuery = $conn->prepare('SELECT id, title, status FROM jobs WHERE id = :id LIMIT 1');
            $jobQuery->execute([':id' => $targetId]);
            $job = $jobQuery->fetch();
            $validSource = $job && ($status === 'removed'
                ? in_array($job['status'], ['open', 'paused'], true)
                : $job['status'] === 'removed');
            if (!$validSource) {
                $error = 'Only open or paused projects can be removed; only removed projects can be restored.';
            } else {
                $conn->beginTransaction();
                try {
                    $update = $conn->prepare('UPDATE jobs SET status = :status WHERE id = :id');
                    $update->execute([':status' => $status, ':id' => $targetId]);
                    if ($status === 'removed') {
                        $close = $conn->prepare("UPDATE applications SET status = 'declined' WHERE job_id = :id AND status IN ('submitted', 'reviewing', 'shortlisted')");
                        $close->execute([':id' => $targetId]);
                    }
                    $audit = $conn->prepare('INSERT INTO admin_events (administrator_id, action, target_type, target_id, details) VALUES (:actor, :action, \'job\', :target, :details)');
                    $audit->execute([
                        ':actor' => (int) $_SESSION['user_id'],
                        ':action' => $status === 'removed' ? 'project_removed' : 'project_restored',
                        ':target' => $targetId,
                        ':details' => $job['title'] . '; previous status: ' . $job['status'] . '; new status: ' . $status,
                    ]);
                    $conn->commit();
                    setFlash($status === 'removed' ? 'Project removed from discovery; pending proposals were closed.' : 'Project restored to the open field.');
                    redirect('admin_panel.php');
                } catch (Throwable $exception) {
                    $conn->rollBack();
                    throw $exception;
                }
            }
        }
    } else {
        $error = 'That admin action is not available.';
    }
}

$summary = $conn->query("SELECT
    (SELECT COUNT(*) FROM users) AS people,
    (SELECT COUNT(*) FROM users WHERE role = 'client') AS clients,
    (SELECT COUNT(*) FROM users WHERE role = 'freelancer') AS freelancers,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') AS administrators,
    (SELECT COUNT(*) FROM users WHERE account_status = 'suspended') AS suspended,
    (SELECT COUNT(*) FROM jobs) AS projects,
    (SELECT COUNT(*) FROM jobs WHERE status = 'open') AS open_projects,
    (SELECT COUNT(*) FROM applications) AS proposals")->fetch();
$users = $conn->query("SELECT u.id, u.name, u.email, u.role, u.account_status, u.location, u.created_at, COUNT(DISTINCT j.id) AS project_count, COUNT(DISTINCT a.id) AS proposal_count FROM users u LEFT JOIN jobs j ON j.user_id = u.id LEFT JOIN applications a ON a.freelancer_id = u.id GROUP BY u.id, u.name, u.email, u.role, u.account_status, u.location, u.created_at ORDER BY u.created_at DESC LIMIT 80")->fetchAll();
$jobs = $conn->query('SELECT j.id, j.title, j.category, j.budget, j.status, j.created_at, u.name AS client_name, u.account_status AS client_status, COUNT(a.id) AS proposal_count FROM jobs j INNER JOIN users u ON u.id = j.user_id LEFT JOIN applications a ON a.job_id = j.id GROUP BY j.id, j.title, j.category, j.budget, j.status, j.created_at, u.name, u.account_status ORDER BY j.created_at DESC LIMIT 80')->fetchAll();
$events = $conn->query('SELECT e.*, u.name AS administrator_name FROM admin_events e INNER JOIN users u ON u.id = e.administrator_id ORDER BY e.created_at DESC LIMIT 12')->fetchAll();

pageStart('Admin control room', 'dashboard');
?>
<section class="welcome-row">
    <div><span class="section-kicker">SIGNAL OPERATIONS / ADMINISTRATOR</span><h1>The whole field, at a glance.</h1><p>Keep the marketplace healthy. Review activity, support members, and moderate project listings.</p></div>
    <div class="admin-stamp"><span>✳</span><small>CONTROL<br>ROOM</small></div>
</section>
<?php renderFlash(); ?>
<?php if ($error !== ''): ?><div class="notice error"><?php echo safeText($error); ?></div><?php endif; ?>
<section class="stats-grid admin-stats">
    <article class="stat-card"><span>PEOPLE ON SIGNAL</span><strong><?php echo (int) $summary['people']; ?></strong><small><?php echo (int) $summary['clients']; ?> clients · <?php echo (int) $summary['freelancers']; ?> freelancers</small></article>
    <article class="stat-card lime"><span>OPEN PROJECTS</span><strong><?php echo (int) $summary['open_projects']; ?></strong><small><?php echo (int) $summary['projects']; ?> total listings</small></article>
    <article class="stat-card"><span>PROPOSALS SHARED</span><strong><?php echo (int) $summary['proposals']; ?></strong><small><?php echo (int) $summary['suspended']; ?> suspended accounts · <?php echo (int) $summary['administrators']; ?> admins</small></article>
</section>
<div class="admin-grid">
    <section class="panel admin-table-panel">
        <div class="panel-heading"><div><span class="section-kicker">PEOPLE / <?php echo count($users); ?> MOST RECENT</span><h2>Member directory</h2></div></div>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>MEMBER</th><th>ROLE</th><th>ACTIVITY</th><th>STATUS</th><th>ACTION</th></tr></thead><tbody>
        <?php foreach ($users as $member): ?><tr>
            <td><strong><?php echo safeText($member['name']); ?></strong><small><?php echo safeText($member['email'] ?: 'Email not provided'); ?><?php if ($member['location']): ?> · <?php echo safeText($member['location']); ?><?php endif; ?></small></td>
            <td><span class="category-chip"><?php echo safeText($member['role']); ?></span></td>
            <td><small><?php echo (int) $member['project_count']; ?> projects<br><?php echo (int) $member['proposal_count']; ?> proposals</small></td>
            <td><span class="status-label <?php echo $member['account_status'] === 'active' ? 'accepted' : 'declined'; ?>"><?php echo safeText($member['account_status']); ?></span></td>
            <td><?php if ($member['role'] !== 'admin'): ?><form method="POST" class="admin-action-form"><?php echo csrfField(); ?><input type="hidden" name="action" value="set_account_status"><input type="hidden" name="target_id" value="<?php echo (int) $member['id']; ?>"><input type="hidden" name="status" value="<?php echo $member['account_status'] === 'active' ? 'suspended' : 'active'; ?>"><button type="submit" class="button-quiet"><?php echo $member['account_status'] === 'active' ? 'Suspend' : 'Reactivate'; ?></button></form><?php else: ?><small>Protected</small><?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
    <section class="panel admin-table-panel">
        <div class="panel-heading"><div><span class="section-kicker">PROJECTS / <?php echo count($jobs); ?> MOST RECENT</span><h2>Project moderation</h2></div></div>
        <div class="table-scroll"><table class="data-table"><thead><tr><th>PROJECT</th><th>CLIENT</th><th>PROPOSALS</th><th>STATUS</th><th>ACTION</th></tr></thead><tbody>
        <?php foreach ($jobs as $job): ?><tr>
            <td><strong><?php echo safeText($job['title']); ?></strong><small><?php echo safeText($job['category']); ?> · <?php echo safeText($job['budget']); ?></small></td>
            <td><small><?php echo safeText($job['client_name']); ?><?php if ($job['client_status'] === 'suspended'): ?><br><span class="admin-flag">ACCOUNT SUSPENDED</span><?php endif; ?></small></td>
            <td><?php echo (int) $job['proposal_count']; ?></td>
            <td><span class="status-label <?php echo $job['status'] === 'open' ? 'accepted' : 'reviewing'; ?>"><?php echo safeText($job['status']); ?></span></td>
            <td><?php if (in_array($job['status'], ['open', 'paused'], true)): ?><form method="POST" class="admin-action-form"><?php echo csrfField(); ?><input type="hidden" name="action" value="set_job_status"><input type="hidden" name="target_id" value="<?php echo (int) $job['id']; ?>"><input type="hidden" name="status" value="removed"><button type="submit" class="button-quiet">Remove</button></form><?php elseif ($job['status'] === 'removed'): ?><form method="POST" class="admin-action-form"><?php echo csrfField(); ?><input type="hidden" name="action" value="set_job_status"><input type="hidden" name="target_id" value="<?php echo (int) $job['id']; ?>"><input type="hidden" name="status" value="open"><button type="submit" class="button-quiet">Restore</button></form><?php else: ?><small>No action</small><?php endif; ?></td>
        </tr><?php endforeach; ?>
        </tbody></table></div>
    </section>
</div>
<section class="panel audit-panel">
    <div class="panel-heading"><div><span class="section-kicker">TRANSPARENCY / LAST 12 ACTIONS</span><h2>Moderation log</h2></div></div>
    <?php if (!$events): ?><div class="empty-state compact-empty"><span class="empty-icon">✳</span><p>Admin actions are recorded here as they happen.</p></div>
    <?php else: foreach ($events as $event): ?><article class="audit-row"><span class="audit-mark">↗</span><div><strong><?php echo safeText(str_replace('_', ' ', $event['action'])); ?></strong><small><?php echo safeText($event['details']); ?></small></div><span class="audit-by"><?php echo safeText($event['administrator_name']); ?> · <?php echo safeText(date('M j, Y', strtotime($event['created_at']))); ?></span></article><?php endforeach; endif; ?>
</section>
<?php pageEnd(); ?>
