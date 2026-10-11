<?php
require __DIR__ . '/db.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['client', 'freelancer'], true) ? $_POST['role'] : 'freelancer';

    if (!verifyCsrf()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = 'Your name must be between 2 and 100 characters.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Choose a password with at least 8 characters.';
    } else {
        $check = $conn->prepare('SELECT id FROM users WHERE name = :name OR (:email_check <> \'\' AND email = :email_value) LIMIT 1');
        $check->execute([':name' => $name, ':email_check' => $email, ':email_value' => $email]);
        if ($check->fetch()) {
            $error = 'That name or email is already registered.';
        } else {
            $stmt = $conn->prepare('INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)');
            $stmt->execute([
                ':name' => $name,
                ':email' => $email !== '' ? $email : null,
                ':password' => password_hash($password, PASSWORD_DEFAULT),
                ':role' => $role,
            ]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $conn->lastInsertId();
            $_SESSION['name'] = $name;
            $_SESSION['role'] = $role;
            redirect('dashboard.php');
        }
    }
}

pageStart('Create your account', 'register');
?>
<section class="auth-layout">
    <div class="auth-story">
        <span class="eyebrow">A better way to do independent work</span>
        <h1>Good work starts with a good match.</h1>
        <p>Build a lasting client-freelancer partnership, not just another transaction.</p>
        <div class="auth-quote"><span>“</span><p>Clear briefs. Human-first proposals. A shared signal for work that fits.</p></div>
    </div>
    <div class="auth-card">
        <span class="section-kicker">GET STARTED</span>
        <h2>Create your account</h2>
        <p class="muted">Choose the workspace that fits the way you work.</p>
        <?php if ($error !== ''): ?><div class="notice error"><?php echo safeText($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <label>Your name<input type="text" name="name" value="<?php echo safeText($_POST['name'] ?? ''); ?>" placeholder="Alex Morgan" required maxlength="100"></label>
            <label>Email address <span class="label-note">OPTIONAL</span><input type="email" name="email" value="<?php echo safeText($_POST['email'] ?? ''); ?>" placeholder="you@example.com"></label>
            <label>Password<input type="password" name="password" placeholder="At least 8 characters" minlength="8" required></label>
            <label>I am here as
                <select name="role">
                    <option value="freelancer" <?php echo ($_POST['role'] ?? '') === 'freelancer' ? 'selected' : ''; ?>>A freelancer — I want to find good work</option>
                    <option value="client" <?php echo ($_POST['role'] ?? '') === 'client' ? 'selected' : ''; ?>>A client — I want to hire great people</option>
                </select>
            </label>
            <button type="submit" class="button-primary">Create my workspace <span>↗</span></button>
        </form>
        <p class="switch-link">Already part of Signal? <a href="login.php">Sign in</a></p>
    </div>
</section>
<?php pageEnd(); ?>
