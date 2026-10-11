<?php
require __DIR__ . '/db.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
if (isset($_GET['suspended'])) {
    $error = 'This account is unavailable. Contact the marketplace administrator for help.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = trim((string) ($_POST['identity'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if (!verifyCsrf()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif ($identity === '') {
        $error = 'Enter your name or email address.';
    } else {
        $stmt = $conn->prepare('SELECT * FROM users WHERE name = :name OR email = :email LIMIT 1');
        $stmt->execute([':name' => $identity, ':email' => $identity]);
        $user = $stmt->fetch();
        if ($user && $user['account_status'] !== 'active') {
            $error = 'This account is currently suspended. Contact the marketplace administrator for help.';
        } elseif ($user && passwordMatches($password, $user['password'] ?? null)) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'] ?? 'freelancer';
            redirect('dashboard.php');
        } elseif ($error === '') {
            $error = 'We could not sign you in with those details.';
        }
    }
}

pageStart('Sign in', 'login');
?>
<section class="auth-layout">
    <div class="auth-story">
        <span class="eyebrow">Your next good thing starts here</span>
        <h1>Come back to work that feels right.</h1>
        <p>Pick up where you left off: discover briefs, meet collaborators, and move projects forward.</p>
        <div class="auth-quote"><span>↗</span><p>One clear signal can turn a good introduction into great work.</p></div>
    </div>
    <div class="auth-card">
        <span class="section-kicker">WELCOME BACK</span>
        <h2>Sign in to Signal</h2>
        <p class="muted">Your independent-work workspace is ready.</p>
        <?php if ($error !== ''): ?><div class="notice error"><?php echo safeText($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <label>Name or email<input type="text" name="identity" value="<?php echo safeText($_POST['identity'] ?? ''); ?>" placeholder="you@example.com" autocomplete="username" required></label>
            <label>Password<input type="password" name="password" placeholder="Your password" autocomplete="current-password" required></label>
            <button type="submit" class="button-primary">Sign in <span>↗</span></button>
        </form>
        <p class="switch-link">New to Signal? <a href="register.php">Create an account</a></p>
    </div>
</section>
<?php pageEnd(); ?>
