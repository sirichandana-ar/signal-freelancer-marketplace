<?php
require __DIR__ . '/db.php';
requireAuth();

$userId = (int) $_SESSION['user_id'];
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $bio = trim((string) ($_POST['bio'] ?? ''));
    $skills = trim((string) ($_POST['skills'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $website = trim((string) ($_POST['website'] ?? ''));

    if (!verifyCsrf()) {
        $error = 'Your session expired. Refresh this page and try again.';
    } elseif (strlen($name) < 2 || strlen($name) > 100) {
        $error = 'Your name must be between 2 and 100 characters.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif ($website !== '' && (
        !filter_var($website, FILTER_VALIDATE_URL)
        || !in_array(strtolower((string) parse_url($website, PHP_URL_SCHEME)), ['http', 'https'], true)
    )) {
        $error = 'Enter a full website address, including https://.';
    } else {
        $duplicateName = $conn->prepare('SELECT id FROM users WHERE name = :name AND id <> :id LIMIT 1');
        $duplicateName->execute([':name' => $name, ':id' => $userId]);
        $duplicateEmail = $conn->prepare('SELECT id FROM users WHERE email = :email AND email IS NOT NULL AND id <> :id LIMIT 1');
        $duplicateEmail->execute([':email' => $email, ':id' => $userId]);
        if ($duplicateName->fetch()) {
            $error = 'That name is already in use. Please choose another.';
        } elseif ($email !== '' && $duplicateEmail->fetch()) {
            $error = 'That email address is already linked to another account.';
        } else {
            $update = $conn->prepare('UPDATE users SET name = :name, email = :email, bio = :bio, skills = :skills, location = :location, website = :website WHERE id = :id');
            $update->execute([
                ':name' => $name,
                ':email' => $email !== '' ? $email : null,
                ':bio' => $bio,
                ':skills' => $skills,
                ':location' => $location,
                ':website' => $website,
                ':id' => $userId,
            ]);
            $_SESSION['name'] = $name;
            setFlash('Your profile is up to date.');
            redirect('profile.php');
        }
    }
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $userId]);
$profile = $stmt->fetch();
if (!$profile) {
    $_SESSION = [];
    session_destroy();
    redirect('login.php');
}
$role = (string) ($_SESSION['role'] ?? 'freelancer');

pageStart('Your profile', 'profile');
?>
<section class="page-heading">
    <div><span class="section-kicker">YOUR SIGNAL / PROFILE</span><h1>Make it easy to find your kind of work.</h1><p>A thoughtful profile helps people see how you think, not just what you do.</p></div>
    <span class="role-pill"><?php echo $role === 'client' ? 'CLIENT STUDIO' : 'FREELANCER PROFILE'; ?></span>
</section>
<div class="profile-layout">
    <section class="panel form-panel">
        <?php renderFlash(); ?>
        <?php if ($error !== ''): ?><div class="notice error"><?php echo safeText($error); ?></div><?php endif; ?>
        <form method="POST">
            <?php echo csrfField(); ?>
            <div class="form-row">
                <label>Your name<input type="text" name="name" value="<?php echo safeText($_POST['name'] ?? $profile['name']); ?>" maxlength="100" required></label>
                <label>Email address<input type="email" name="email" value="<?php echo safeText($_POST['email'] ?? $profile['email']); ?>" placeholder="you@example.com"></label>
            </div>
            <label>About you<textarea name="bio" rows="5" maxlength="2000" placeholder="What do you care about making?"><?php echo safeText($_POST['bio'] ?? $profile['bio']); ?></textarea></label>
            <label>Skills <span class="label-note">COMMA SEPARATED · USED FOR SIGNAL FIT</span><input type="text" name="skills" value="<?php echo safeText($_POST['skills'] ?? $profile['skills']); ?>" placeholder="Figma, art direction, user research"></label>
            <div class="form-row">
                <label>Based in<input type="text" name="location" value="<?php echo safeText($_POST['location'] ?? $profile['location']); ?>" maxlength="120" placeholder="City, country"></label>
                <label>Website or portfolio<input type="url" name="website" value="<?php echo safeText($_POST['website'] ?? $profile['website']); ?>" placeholder="https://yourportfolio.com"></label>
            </div>
            <button type="submit" class="button-primary">Save profile <span>↗</span></button>
        </form>
    </section>
    <aside class="signal-card">
        <span class="signal-spark">✳</span><span class="section-kicker">THE SIGNAL DIFFERENCE</span>
        <h2>Not a mystery score.</h2>
        <p>When a freelancer applies, Signal compares the skills in their profile with the skills in the brief. The score is transparent: shared skills divided by skills requested.</p>
        <div class="match-demo"><span class="match-ring">75<small>%</small></span><span><strong>3 of 4 skills match</strong><small>Figma · UX research · Prototyping</small></span></div>
        <p class="signal-footnote">Add your real skills here to make every match more useful.</p>
    </aside>
</div>
<?php pageEnd(); ?>
