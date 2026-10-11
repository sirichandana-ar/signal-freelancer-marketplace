<?php
require __DIR__ . '/db.php';
requireRole('client');

$search = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT u.id, u.name, u.bio, u.skills, u.location, u.website, COUNT(DISTINCT a.id) AS proposals_sent, SUM(CASE WHEN a.status = 'accepted' THEN 1 ELSE 0 END) AS projects_won FROM users u LEFT JOIN applications a ON a.freelancer_id = u.id WHERE u.role = 'freelancer' AND u.account_status = 'active'";
$params = [];
if ($search !== '') {
    $sql .= ' AND (u.name LIKE :name_query OR u.bio LIKE :bio_query OR u.skills LIKE :skills_query OR u.location LIKE :location_query)';
    $needle = '%' . $search . '%';
    $params = [
        ':name_query' => $needle,
        ':bio_query' => $needle,
        ':skills_query' => $needle,
        ':location_query' => $needle,
    ];
}
$sql .= ' GROUP BY u.id, u.name, u.bio, u.skills, u.location, u.website ORDER BY projects_won DESC, proposals_sent DESC, u.name ASC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$talent = $stmt->fetchAll();

pageStart('Find your people', 'talent');
?>
<section class="page-heading">
    <div><span class="section-kicker">CLIENT STUDIO / TALENT DIRECTORY</span><h1>Meet the people behind the good work.</h1><p>Find an independent collaborator by craft, perspective, or place.</p></div>
</section>
<form method="GET" class="search-panel talent-search">
    <label class="search-field"><span>⌕</span><input type="search" name="q" value="<?php echo safeText($search); ?>" placeholder="Search names, skills, or places"></label>
    <button type="submit" class="button-primary">Find your people <span>↗</span></button>
    <?php if ($search !== ''): ?><a class="clear-link" href="talent.php">Clear</a><?php endif; ?>
</form>
<section class="talent-grid">
    <?php if (!$talent): ?><div class="panel empty-state"><span class="empty-icon">✳</span><h2>No profiles in this orbit yet.</h2><p>Try another skill or check back soon.</p></div>
    <?php else: foreach ($talent as $person): ?>
        <article class="panel talent-card">
            <div class="talent-card-head"><span class="avatar-large"><?php echo safeText(strtoupper(substr($person['name'], 0, 1))); ?></span><div><h2><?php echo safeText($person['name']); ?></h2><p><?php echo safeText($person['location'] ?: 'Independent / remote'); ?></p></div></div>
            <p class="talent-bio"><?php echo safeText($person['bio'] ?: 'Independent freelancer on Signal.'); ?></p>
            <div class="skill-chips"><?php foreach (array_slice(normalizedSkills($person['skills']), 0, 6) as $skill): ?><span class="skill-chip"><?php echo safeText(ucwords($skill)); ?></span><?php endforeach; ?><?php if (!normalizedSkills($person['skills'])): ?><span class="skill-chip">Add skills to discover a better fit</span><?php endif; ?></div>
            <div class="talent-footer"><span><?php echo (int) $person['projects_won']; ?> projects won <span class="row-divider">·</span> <?php echo (int) $person['proposals_sent']; ?> proposals</span><?php if ($person['website']): ?><a class="text-link" href="<?php echo safeText($person['website']); ?>" target="_blank" rel="noopener noreferrer">View portfolio ↗</a><?php endif; ?></div>
        </article>
    <?php endforeach; endif; ?>
</section>
<section class="client-invite"><div><span class="section-kicker">HAVE THE RIGHT PROJECT IN MIND?</span><h2>Great collaborations start with a clear brief.</h2></div><a href="post_job.php" class="button-primary">Write a brief <span>↗</span></a></section>
<?php pageEnd(); ?>
