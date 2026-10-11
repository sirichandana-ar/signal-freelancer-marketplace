<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/db.php';

$adminEmail = strtolower(trim((string) getenv('SIGNAL_ADMIN_EMAIL')));
$adminPassword = (string) getenv('SIGNAL_ADMIN_PASSWORD');
if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Set SIGNAL_ADMIN_EMAIL to a valid email address before running this script.\n");
    exit(1);
}
if (strlen($adminPassword) < 16) {
    fwrite(STDERR, "Set SIGNAL_ADMIN_PASSWORD to a unique password with at least 16 characters.\n");
    exit(1);
}

$demoPassword = 'SignalDemo2026!';
$clients = [
    ['key' => 'studio', 'name' => 'Maya Chen', 'email' => 'maya.chen@signal-demo.test', 'bio' => 'Creative director at Common Ground Studio. We help independent makers show up with clarity.', 'location' => 'Portland, OR', 'skills' => 'Brand strategy, Creative direction'],
    ['key' => 'goodfood', 'name' => 'Andre Williams', 'email' => 'andre.williams@signal-demo.test', 'bio' => 'Building better food systems with a small team and a big appetite for thoughtful design.', 'location' => 'Brooklyn, NY', 'skills' => 'Food systems, Product strategy'],
    ['key' => 'atlas', 'name' => 'Priya Kapoor', 'email' => 'priya.kapoor@signal-demo.test', 'bio' => 'Founder working on tools that make access to the outdoors feel open to everybody.', 'location' => 'Denver, CO', 'skills' => 'Product, Community, Accessibility'],
];
$freelancers = [
    ['key' => 'lena', 'name' => 'Lena Ortiz', 'email' => 'lena.ortiz@signal-demo.test', 'location' => 'Austin, TX', 'bio' => 'Brand designer helping purpose-led teams build identities with warmth and clarity.', 'skills' => 'Figma, Brand identity, Art direction, Adobe Illustrator', 'website' => 'https://example.com/lena'],
    ['key' => 'noah', 'name' => 'Noah Williams', 'email' => 'noah.williams@signal-demo.test', 'location' => 'Toronto, Canada', 'bio' => 'Product engineer turning ambitious ideas into accessible, dependable software.', 'skills' => 'PHP, JavaScript, React, Accessibility, API development', 'website' => 'https://example.com/noah'],
    ['key' => 'amara', 'name' => 'Amara Okafor', 'email' => 'amara.okafor@signal-demo.test', 'location' => 'London, UK', 'bio' => 'Writer and strategist finding the human story behind complex products.', 'skills' => 'UX writing, Content strategy, Research, Editing', 'website' => 'https://example.com/amara'],
    ['key' => 'leo', 'name' => 'Leo Martin', 'email' => 'leo.martin@signal-demo.test', 'location' => 'Berlin, Germany', 'bio' => 'Independent motion designer making small screens feel a little more alive.', 'skills' => 'Motion design, After Effects, Storyboarding, Illustration', 'website' => 'https://example.com/leo'],
    ['key' => 'sofia', 'name' => 'Sofia Reyes', 'email' => 'sofia.reyes@signal-demo.test', 'location' => 'Mexico City, Mexico', 'bio' => 'UX researcher who makes space for the voices too often left out of the room.', 'skills' => 'UX research, Interviewing, Accessibility, Figma', 'website' => 'https://example.com/sofia'],
    ['key' => 'james', 'name' => 'James Park', 'email' => 'james.park@signal-demo.test', 'location' => 'Seattle, WA', 'bio' => 'Full-stack developer and open-source maintainer who likes a clear brief.', 'skills' => 'PHP, MySQL, JavaScript, Security, Testing', 'website' => 'https://example.com/james'],
    ['key' => 'ella', 'name' => 'Ella Brooks', 'email' => 'ella.brooks@signal-demo.test', 'location' => 'Melbourne, Australia', 'bio' => 'Illustrator and art director making expressive visual worlds for good causes.', 'skills' => 'Illustration, Art direction, Editorial design, Procreate', 'website' => 'https://example.com/ella'],
    ['key' => 'david', 'name' => 'David Kim', 'email' => 'david.kim@signal-demo.test', 'location' => 'Seoul, South Korea', 'bio' => 'Growth marketer pairing careful experiments with useful, honest storytelling.', 'skills' => 'SEO, Analytics, Email marketing, Copywriting', 'website' => 'https://example.com/david'],
];
$jobs = [
    ['key' => 'market', 'client' => 'studio', 'title' => 'A warmer welcome for local makers', 'description' => 'We are bringing a collection of independent neighborhood shops together under one roof. We need a flexible identity that feels like the people behind the counter: generous, a little unexpected, and unmistakably local. We would love to collaborate on a small brand system and a set of launch assets.', 'budget' => '$1,200–1,800', 'category' => 'Brand & design', 'skills' => 'Figma, Brand identity, Art direction', 'status' => 'open', 'freelancers' => ['lena' => 'submitted', 'ella' => 'reviewing', 'sofia' => 'shortlisted']],
    ['key' => 'pantry', 'client' => 'goodfood', 'title' => 'A thoughtful pantry, from farm to doorstep', 'description' => 'Help us turn our regional food-box pilot into a simple online experience. We are looking for a fast, accessible storefront with clear product stories, honest sourcing details, and a checkout that feels effortless on a phone.', 'budget' => '$3,000–4,500', 'category' => 'Development', 'skills' => 'PHP, JavaScript, Accessibility, API development', 'status' => 'open', 'freelancers' => ['noah' => 'submitted', 'james' => 'shortlisted', 'amara' => 'reviewing']],
    ['key' => 'trail', 'client' => 'atlas', 'title' => 'Make the outdoors feel like it belongs to everyone', 'description' => 'We are creating a guide to accessible day hikes made with local communities. We need product discovery and a friendly prototype to help us test how trail details, access notes, transport and group stories can work together.', 'budget' => '$1,800 fixed', 'category' => 'Product & strategy', 'skills' => 'UX research, Accessibility, Figma', 'status' => 'open', 'freelancers' => ['sofia' => 'submitted', 'lena' => 'submitted', 'noah' => 'reviewing']],
    ['key' => 'fieldnotes', 'client' => 'studio', 'title' => 'Words that sound like the people who make the thing', 'description' => 'Our makers need short product descriptions, a welcome email, and a voice guide that helps their stories land without turning into marketing-speak. We are after curiosity, craft, and clear writing.', 'budget' => '$800–1,100', 'category' => 'Writing & content', 'skills' => 'UX writing, Content strategy, Editing', 'status' => 'open', 'freelancers' => ['amara' => 'submitted', 'david' => 'reviewing']],
    ['key' => 'motion', 'client' => 'atlas', 'title' => 'A 30-second invitation to take the long way', 'description' => 'Create a short, loopable motion piece for our inclusive trails launch. The tone is calm and welcoming; the work needs to feel good with or without sound and translate cleanly to social formats.', 'budget' => '$1,000', 'category' => 'Video & motion', 'skills' => 'Motion design, After Effects, Storyboarding', 'status' => 'open', 'freelancers' => ['leo' => 'submitted', 'ella' => 'submitted']],
    ['key' => 'analytics', 'client' => 'goodfood', 'title' => 'Small, kind experiments for a growing food club', 'description' => 'We want to understand which messages help new members feel welcome and which get them to their first order. Set up a lightweight measurement plan, propose experiments and help us learn without chasing vanity metrics.', 'budget' => '$900–1,400', 'category' => 'Marketing', 'skills' => 'Analytics, Email marketing, Copywriting', 'status' => 'open', 'freelancers' => ['david' => 'submitted', 'amara' => 'shortlisted']],
    ['key' => 'identity', 'client' => 'atlas', 'title' => 'A visual system for the next chapter', 'description' => 'Our accessibility community is growing and our old look no longer fits. Partner with the team to shape a flexible identity, a small set of templates, and clear guidance we can keep using ourselves.', 'budget' => '$2,000–2,800', 'category' => 'Brand & design', 'skills' => 'Brand identity, Figma, Accessibility', 'status' => 'open', 'freelancers' => ['ella' => 'submitted', 'lena' => 'shortlisted', 'sofia' => 'reviewing']],
    ['key' => 'prototype', 'client' => 'studio', 'title' => 'Build the maker directory we sketched on a napkin', 'description' => 'A small, fast directory that helps people find a neighborhood maker by craft, location, and availability. We have a content model and rough designs; now we need a tested build with a lightweight admin flow.', 'budget' => '$3,500', 'category' => 'Development', 'skills' => 'PHP, MySQL, JavaScript, Testing', 'status' => 'open', 'freelancers' => ['james' => 'submitted', 'noah' => 'reviewing']],
    ['key' => 'foodguide', 'client' => 'goodfood', 'title' => 'A field guide to the food we grow together', 'description' => 'Create a beautifully structured guide to the farms and producers in our growing network. The project needs an editorial framework that makes location, seasonality, and farmer stories feel connected.', 'budget' => '$1,400', 'category' => 'Writing & content', 'skills' => 'Editorial design, Illustration, Content strategy', 'status' => 'filled', 'freelancers' => ['ella' => 'declined', 'amara' => 'accepted']],
];
$messages = [
    'I read your brief and love the care you have put into the problem. My approach would start with a short discovery session, followed by a clear first concept that your team can react to.',
    'This feels like a thoughtful match for the work I do. I would bring a collaborative process, regular check-ins, and a few early prototypes so we can make good decisions together.',
    'The combination of real-world impact and careful craft caught my attention. I would love to learn more about your audience and propose a practical first milestone.',
];

$conn->beginTransaction();
try {
    $adminQuery = $conn->prepare('SELECT id, role FROM users WHERE email = :email LIMIT 1');
    $adminQuery->execute([':email' => $adminEmail]);
    $existingAdmin = $adminQuery->fetch();
    if ($existingAdmin && $existingAdmin['role'] !== 'admin') {
        throw new RuntimeException('The requested admin email already belongs to a non-admin account. Choose a different SIGNAL_ADMIN_EMAIL.');
    }
    if (!$existingAdmin) {
        $createAdmin = $conn->prepare('INSERT INTO users (name, email, password, role, bio, location, account_status) VALUES (:name, :email, :password, \'admin\', :bio, :location, \'active\')');
        $createAdmin->execute([
            ':name' => 'Signal Administrator',
            ':email' => $adminEmail,
            ':password' => password_hash($adminPassword, PASSWORD_DEFAULT),
            ':bio' => 'Marketplace administrator.',
            ':location' => 'Signal HQ',
        ]);
        $adminCreated = true;
    } else {
        $adminCreated = false;
    }

    $userIds = [];
    $findUser = $conn->prepare('SELECT id, role FROM users WHERE email = :email LIMIT 1');
    $createUser = $conn->prepare('INSERT INTO users (name, email, password, role, bio, skills, location, website, account_status) VALUES (:name, :email, :password, :role, :bio, :skills, :location, :website, \'active\')');
    foreach (array_merge($clients, $freelancers) as $person) {
        $findUser->execute([':email' => $person['email']]);
        $existingUser = $findUser->fetch();
        $role = isset($person['website']) ? 'freelancer' : 'client';
        if ($existingUser) {
            if ($existingUser['role'] !== $role) {
                throw new RuntimeException('A demo account email exists with an incompatible role: ' . $person['email']);
            }
            $userIds[$person['key']] = (int) $existingUser['id'];
            continue;
        }
        $createUser->execute([
            ':name' => $person['name'],
            ':email' => $person['email'],
            ':password' => password_hash($demoPassword, PASSWORD_DEFAULT),
            ':role' => $role,
            ':bio' => $person['bio'],
            ':skills' => $person['skills'],
            ':location' => $person['location'],
            ':website' => $person['website'] ?? null,
        ]);
        $userIds[$person['key']] = (int) $conn->lastInsertId();
    }

    foreach (array_merge($clients, $freelancers) as $person) {
        if (!isset($userIds[$person['key']])) {
            $findUser->execute([':email' => $person['email']]);
            $userIds[$person['key']] = (int) $findUser->fetchColumn();
        }
    }

    $jobIds = [];
    $findJob = $conn->prepare('SELECT id FROM jobs WHERE user_id = :owner AND title = :title LIMIT 1');
    $createJob = $conn->prepare('INSERT INTO jobs (user_id, title, description, budget, category, skills, status) VALUES (:owner, :title, :description, :budget, :category, :skills, :status)');
    foreach ($jobs as $job) {
        $ownerId = $userIds[$job['client']];
        $findJob->execute([':owner' => $ownerId, ':title' => $job['title']]);
        $existingJobId = $findJob->fetchColumn();
        if ($existingJobId) {
            $jobIds[$job['key']] = (int) $existingJobId;
            continue;
        }
        $createJob->execute([
            ':owner' => $ownerId,
            ':title' => $job['title'],
            ':description' => $job['description'],
            ':budget' => $job['budget'],
            ':category' => $job['category'],
            ':skills' => $job['skills'],
            ':status' => $job['status'],
        ]);
        $jobIds[$job['key']] = (int) $conn->lastInsertId();
    }

    $findApplication = $conn->prepare('SELECT id FROM applications WHERE job_id = :job_id AND freelancer_id = :freelancer_id LIMIT 1');
    $createApplication = $conn->prepare('INSERT INTO applications (job_id, freelancer_id, message, proposed_budget, status) VALUES (:job_id, :freelancer_id, :message, :budget, :status)');
    $messageIndex = 0;
    foreach ($jobs as $job) {
        $jobId = $jobIds[$job['key']];
        foreach ($job['freelancers'] as $freelancerKey => $status) {
            $freelancerId = $userIds[$freelancerKey];
            $findApplication->execute([':job_id' => $jobId, ':freelancer_id' => $freelancerId]);
            if ($findApplication->fetchColumn()) {
                continue;
            }
            $createApplication->execute([
                ':job_id' => $jobId,
                ':freelancer_id' => $freelancerId,
                ':message' => $messages[$messageIndex % count($messages)],
                ':budget' => $status === 'accepted' ? '$1,400 fixed' : null,
                ':status' => $status,
            ]);
            $messageIndex++;
        }
    }
    $conn->commit();
} catch (Throwable $exception) {
    $conn->rollBack();
    fwrite(STDERR, 'Demo data was not seeded: ' . $exception->getMessage() . "\n");
    exit(1);
}

echo 'Demo seed complete. Added or reused ' . count($clients) . ' clients, ' . count($freelancers)
    . ' freelancers, ' . count($jobs) . ' projects, and their sample proposals.' . PHP_EOL;
if ($adminCreated) {
    echo 'Administrator account created: ' . $adminEmail . PHP_EOL;
    echo 'Administrator password: the SIGNAL_ADMIN_PASSWORD value used for this run (not saved in the project).' . PHP_EOL;
} else {
    echo 'Existing administrator account reused: ' . $adminEmail . PHP_EOL;
}
echo 'Demo client and freelancer password: ' . $demoPassword . PHP_EOL;
