<?php
require __DIR__ . '/db.php';

$openProjects = (int) $conn->query("SELECT COUNT(*) FROM jobs j INNER JOIN users u ON u.id = j.user_id WHERE j.status = 'open' AND u.account_status = 'active'")->fetchColumn();
$people = (int) $conn->query("SELECT COUNT(*) FROM users WHERE account_status = 'active'")->fetchColumn();
$newest = $conn->query("SELECT j.*, u.name AS client_name FROM jobs j INNER JOIN users u ON u.id = j.user_id WHERE j.status = 'open' AND u.account_status = 'active' ORDER BY j.created_at DESC LIMIT 3")->fetchAll();

pageStart('Independent work, in sync', 'home');
?>
<section class="home-hero">
    <div class="hero-left">
        <span class="eyebrow"><span class="live-dot"></span> A calmer corner of the freelance internet</span>
        <h1>Good work.<br><em>Good people.</em><br>In sync.</h1>
        <p>Signal brings thoughtful briefs and independent talent together—with a little more context and a lot less noise.</p>
        <div class="hero-actions"><a class="button-primary" href="<?php echo isLoggedIn() ? 'view_jobs.php' : 'register.php'; ?>">Find your next signal <span>↗</span></a><a class="button-quiet" href="view_jobs.php">Browse the open field <span>→</span></a></div>
        <div class="community-line"><div class="avatar-stack"><span>A</span><span>M</span><span>J</span><b>+</b></div><p><strong><?php echo $people; ?> people</strong> already making good work happen</p></div>
    </div>
    <div class="hero-art" aria-label="Illustration of a project brief connected to creative collaborators">
        <div class="orbit orbit-one"></div><div class="orbit orbit-two"></div>
        <div class="art-sticker sticker-top"><span>✳</span> GOOD FIT, NOT MORE FEED</div>
        <div class="brief-card"><div class="brief-top"><span class="brief-mark">S</span><span class="brief-status"><i></i> OPEN BRIEF</span></div><span class="section-kicker">BRAND & DESIGN / 4 DAYS AGO</span><h2>A warmer welcome for local makers</h2><p>Looking for a thoughtful brand designer to help neighborhood businesses show up with confidence.</p><div class="brief-rule"></div><div class="brief-foot"><span>$1,200–1,800</span><span>3 skills requested</span></div></div>
        <div class="floating-match"><span class="match-ring">86<small>%</small></span><span><strong>Good signal</strong><small>Figma · Identity · Strategy</small></span></div>
        <div class="art-sticker sticker-bottom">A PROJECT WITH PURPOSE <span>↗</span></div>
        <span class="spark spark-one">✳</span><span class="spark spark-two">✳</span>
    </div>
</section>
<section class="signal-intro">
    <div><span class="section-kicker">LESS SPRAY-AND-PRAY. MORE “THAT'S MY KIND OF WORK.”</span><h2>Because a good match<br>is more than a keyword.</h2></div>
    <p>Signal Fit shows the overlap between the skills a project asks for and the skills you actually list. No black box, no ranking people—just a useful starting point for a better conversation.</p>
</section>
<section class="featured-section">
    <div class="section-title-row"><div><span class="section-kicker">THE OPEN FIELD / <?php echo $openProjects; ?> PROJECTS IN PLAY</span><h2>Fresh from the field</h2></div><a href="view_jobs.php" class="text-link">See all open briefs ↗</a></div>
    <?php if (!$newest): ?><div class="empty-state panel"><span class="empty-icon">✳</span><h3>It starts with a brief.</h3><p>There are no open projects yet. <?php if (!isLoggedIn()): ?><a class="text-link" href="register.php">Be the first to post one ↗</a><?php else: ?>Check back soon for new work.<?php endif; ?></p></div>
    <?php else: ?><div class="featured-grid"><?php foreach ($newest as $job): ?><article class="job-card featured-card"><div class="row-top"><span class="category-chip"><?php echo safeText($job['category']); ?></span><span class="brief-age"><?php echo safeText(date('M j', strtotime($job['created_at']))); ?></span></div><h3><?php echo safeText($job['title']); ?></h3><p><?php echo safeText(substr($job['description'], 0, 145)); ?><?php echo strlen($job['description']) > 145 ? '…' : ''; ?></p><div class="featured-foot"><span><?php echo safeText($job['budget']); ?></span><a class="text-link" href="view_jobs.php">Explore brief ↗</a></div></article><?php endforeach; ?></div><?php endif; ?>
</section>
<section class="closing-band"><div><span class="section-kicker">YOUR NEXT CHAPTER ISN'T A SCROLL AWAY</span><h2>Find your people.<br>Make something matter.</h2></div><a class="button-primary" href="<?php echo isLoggedIn() ? 'dashboard.php' : 'register.php'; ?>"><?php echo isLoggedIn() ? 'Open your workspace' : 'Join Signal'; ?> <span>↗</span></a></section>
<?php pageEnd(); ?>
