<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$error = '';
$notice = (string) ($_SESSION['notice'] ?? '');
unset($_SESSION['notice']);
$action = $_GET['action'] ?? (is_logged_in() ? 'dashboard' : 'login');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf_token();
        $postAction = (string) ($_POST['action'] ?? '');

        if ($postAction === 'setup') {
            if (account_exists()) {
                throw new RuntimeException('HR setup is already complete.');
            }

            $username = normalize_username((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $recoveryCode = (string) ($_POST['recovery_code'] ?? '');
            validate_username($username);
            validate_password($password);
            validate_password($recoveryCode, 'Recovery code');

            save_account([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'recovery_hash' => password_hash($recoveryCode, PASSWORD_DEFAULT),
            ]);
            $_SESSION['notice'] = 'HR account created. Sign in with your new credentials.';
            redirect('System.php');
        }

        if ($postAction === 'login') {
            $username = normalize_username((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $account = get_account();

            if ($account === null || $username !== $account['username'] || !password_verify($password, $account['password_hash'])) {
                throw new RuntimeException('The username or password is incorrect.');
            }

            session_regenerate_id(true);
            $_SESSION['authenticated'] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            redirect('dashboard.php');
        }

        if ($postAction === 'recover') {
            $account = get_account();
            $username = normalize_username((string) ($_POST['username'] ?? ''));
            $recoveryCode = (string) ($_POST['recovery_code'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            validate_password($newPassword, 'New password');

            if (
                $account === null
                || $username !== $account['username']
                || !password_verify($recoveryCode, $account['recovery_hash'])
            ) {
                throw new RuntimeException('The username or recovery code is incorrect.');
            }

            $account['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
            save_account($account);
            $_SESSION['notice'] = 'Password reset complete. You can now sign in with your username and new password.';
            redirect('System.php');
        }

        if ($postAction === 'logout') {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            redirect('System.php');
        }

        require_authentication();

        if ($postAction === 'save_policy') {
            $policyInput = $_POST['policy'] ?? null;
            if (!is_array($policyInput)) {
                throw new RuntimeException('The submitted ranking policy is invalid.');
            }
            save_ranking_policy($policyInput);
            $_SESSION['notice'] = 'Scoring points, rank ranges, and hourly rates updated.';
            redirect('scoring-rubric.php');
        }

        if ($postAction === 'reset_policy') {
            save_ranking_policy(default_ranking_policy());
            $_SESSION['notice'] = 'Scoring points, rank ranges, and hourly rates restored to their original values.';
            redirect('scoring-rubric.php');
        }

        if ($postAction === 'add_faculty') {
            $credentials = validate_faculty_input($_POST);
            $faculty = get_faculty();
            $faculty[] = array_merge(['id' => bin2hex(random_bytes(8))], $credentials);
            save_faculty($faculty);
            $_SESSION['notice'] = 'Faculty credentials added and scored.';
            redirect('faculty-ranking.php');
        }

        if ($postAction === 'edit_faculty') {
            $id = (string) ($_POST['id'] ?? '');
            $credentials = validate_faculty_input($_POST);
            $faculty = get_faculty();
            $found = false;

            foreach ($faculty as &$record) {
                if ($record['id'] === $id) {
                    $record = array_merge($record, $credentials);
                    $found = true;
                    break;
                }
            }
            unset($record);

            if (!$found) {
                throw new RuntimeException('That faculty record was not found.');
            }

            save_faculty($faculty);
            $_SESSION['notice'] = 'Faculty record updated and rescored.';
            redirect('faculty-ranking.php');
        }

        if ($postAction === 'delete_faculty') {
            $id = (string) ($_POST['id'] ?? '');
            $faculty = get_faculty();
            $remaining = array_values(array_filter(
                $faculty,
                static fn (array $record): bool => $record['id'] !== $id
            ));

            if (count($remaining) === count($faculty)) {
                throw new RuntimeException('That faculty record was not found.');
            }

            save_faculty($remaining);
            $_SESSION['notice'] = 'Faculty record deleted.';
            redirect('faculty-ranking.php');
        }

        throw new RuntimeException('That request is not supported.');
    }

    if ($action === 'logout') {
        require_authentication();
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        redirect('System.php');
    }

    if (is_logged_in()) {
        $action = 'dashboard';
    } elseif (!account_exists()) {
        $action = 'setup';
    } elseif ($action !== 'recover') {
        $action = 'login';
    }
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}

$csrfToken = csrf_token();
$allowedPages = ['overview', 'add', 'ranking', 'rubric', 'profile'];
$page = (string) ($_GET['page'] ?? 'overview');
if (!in_array($page, $allowedPages, true)) {
    $page = 'overview';
}
$pageTitles = [
    'overview' => ['Overview', 'Welcome to CSTA HR', 'Review faculty credentials, rank levels, and hourly rates.'],
    'add' => ['Add Credentials', 'Enter faculty credentials', 'Add a faculty profile and calculate its points.'],
    'ranking' => ['Faculty Ranking', 'Faculty ranking and hourly rate', 'Compare faculty scores, rank eligibility, and rates.'],
    'rubric' => ['Scoring Rubric', 'Scoring rubric and rank matrix', 'Review the scoring instrument, prerequisites, and hourly rates.'],
    'profile' => ['Faculty Profile', 'Faculty profile', 'View faculty credentials, score breakdown, and rank.'],
];
$facultyRecords = [];
$rankingPolicy = default_ranking_policy();
if ($action === 'dashboard' && is_logged_in()) {
    try {
        $rankingPolicy = get_ranking_policy();
        $facultyRecords = rank_faculty(get_faculty());
    } catch (RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}
$visibleExperienceMaximum = min(
    $rankingPolicy['experience']['cap'],
    $rankingPolicy['experience']['teaching_cap']
        + $rankingPolicy['experience']['administrative_cap']
        + $rankingPolicy['experience']['works_cap']
        + $rankingPolicy['experience']['conference_cap']
);
$searchInput = $_GET['search'] ?? '';
$searchQuery = is_string($searchInput) ? substr(trim($searchInput), 0, 100) : '';
$degreeInput = $_GET['degree'] ?? '';
$degreeFilter = is_string($degreeInput) ? $degreeInput : '';
if ($degreeFilter !== 'legacy' && !array_key_exists($degreeFilter, $rankingPolicy['academic']['degree_points'])) {
    $degreeFilter = '';
}
$meritInput = $_GET['merit'] ?? '';
$meritFilter = is_string($meritInput) ? $meritInput : '';
$rankGroups = [
    'instructor' => ['label' => 'Instructor', 'first' => 0, 'last' => 2],
    'assistant' => ['label' => 'Assistant Professor', 'first' => 3, 'last' => 7],
    'associate' => ['label' => 'Associate Professor', 'first' => 8, 'last' => 12],
    'professor' => ['label' => 'Professor', 'first' => 13, 'last' => 17],
];
$policyFieldGroups = [
    'academic' => [
        'title' => 'I. Academic preparation',
        'fields' => [
            'degree_points' => [
                'bachelor-4' => '4-year bachelor degree',
                'bachelor-5' => '5-year bachelor degree',
                'masters' => 'Aligned master degree',
                'physician-lawyer' => 'Physician / lawyer degree',
                'doctorate' => 'Doctorate degree',
            ],
            'additional_bachelors' => 'Additional bachelor degree points',
            'additional_masters' => 'Additional master degree points',
            'additional_doctorates' => 'Additional doctorate points',
            'additional_cap' => 'Additional-degree points cap',
            'short_course_points' => 'Short-course certificate points each',
            'short_course_cap' => 'Short-course points cap',
            'honors' => [
                'none' => 'No honors',
                'cum-laude' => 'Cum Laude',
                'magna-cum-laude' => 'Magna Cum Laude',
                'summa-cum-laude' => 'Summa Cum Laude',
            ],
            'exam_subprofessional' => 'Sub-professional exam points',
            'exam_professional' => 'Professional / licensure exam points',
            'cap' => 'Section I maximum points',
        ],
    ],
    'experience' => [
        'title' => 'II. Experience and academic work',
        'fields' => [
            'teaching_csta_per_year' => 'Teaching points per CSTA year',
            'teaching_other_years_per_point' => 'Other-HEI teaching years per period',
            'teaching_other_points_per_period' => 'Other-HEI teaching points per period',
            'teaching_cap' => 'Teaching points cap',
            'administrative_csta_per_year' => 'Administrative points per CSTA year',
            'administrative_other_years_per_point' => 'Other administrative years per period',
            'administrative_other_points_per_period' => 'Other administrative points per period',
            'administrative_cap' => 'Administrative points cap',
            'book_points' => 'Published book / material points each',
            'research_points' => 'Research / creative-work points each',
            'project_points' => 'Implemented-project points each',
            'works_cap' => 'Combined publication / project points cap',
            'speaker_points' => 'Speaker / resource-person points each',
            'committee_points' => 'Committee chair / reactor points each',
            'participant_points' => 'Participant / delegate points each',
            'conference_cap' => 'Conference points cap',
            'cap' => 'Section II maximum points',
        ],
    ],
    'competence' => [
        'title' => 'III. Competence',
        'fields' => [
            'dean_high_threshold' => 'Dean score for highest band begins at',
            'dean_pass_threshold' => 'Minimum Dean score for promotion',
            'dean_high_points' => 'Highest-band Dean points',
            'dean_pass_points' => 'Passing-band Dean points',
            'dean_fail_points' => 'Below-threshold Dean points',
            'student_satisfactory_points' => 'Satisfactory student-rating points',
            'student_very_satisfactory_points' => 'Very satisfactory student-rating points',
            'student_excellent_points' => 'Excellent student-rating points',
            'national_award_points' => 'National / international award points each',
            'institutional_award_points' => 'Institutional award points each',
            'award_cap' => 'Award points cap',
            'international_grant_points' => 'International grant points each',
            'local_grant_points' => 'Local grant points each',
            'grant_cap' => 'Grant points cap',
            'affiliation_president_points' => 'Professional association president points each',
            'affiliation_position_points' => 'Professional association officer points each',
            'affiliation_member_points' => 'Professional association member points each',
            'affiliation_cap' => 'Affiliation points cap',
            'professional_cap' => 'Awards / grants / affiliation combined cap',
            'cap' => 'Section III maximum points',
        ],
    ],
    'extension' => [
        'title' => 'IV. Extension and community service',
        'fields' => [
            'participation_day_points' => 'Participation points per day',
            'participation_cap' => 'Participation points cap',
            'advisor_points' => 'Adviser / chair points per year or title',
            'advisor_cap' => 'Adviser / chair points cap',
            'member_points' => 'Member / editor points per year or title',
            'member_cap' => 'Member / editor points cap',
            'cap' => 'Section IV maximum points',
        ],
    ],
];
$allowedMeritFilters = array_merge(['unranked'], array_keys($rankGroups));
if (!in_array($meritFilter, $allowedMeritFilters, true)) {
    $meritFilter = '';
}
$rankFilterRanges = [];
foreach ($rankGroups as $key => $group) {
    $rankFilterRanges[$key] = [$group['first'], $group['last']];
}
$rankById = [];
$facultyById = [];
$legacyRecordCount = 0;
foreach ($facultyRecords as $index => $record) {
    $rankById[$record['id']] = $index + 1;
    $facultyById[$record['id']] = $record;
    if ($record['legacy_record']) {
        $legacyRecordCount++;
    }
}
$profileIdInput = $_GET['id'] ?? '';
$profileId = is_string($profileIdInput) ? $profileIdInput : '';
$profileRecord = $profileId !== '' ? ($facultyById[$profileId] ?? null) : null;
if ($page === 'profile' && $profileRecord !== null) {
    $pageTitles['profile'][1] = $profileRecord['name'];
}
$visibleFacultyRecords = array_values(array_filter(
    $facultyRecords,
    static function (array $record) use ($searchQuery, $degreeFilter, $meritFilter, $rankFilterRanges): bool {
        if ($searchQuery !== '' && stripos($record['name'], $searchQuery) === false) {
            return false;
        }
        if ($degreeFilter === 'legacy' && !$record['legacy_record']) {
            return false;
        }
        if ($degreeFilter !== '' && $degreeFilter !== 'legacy' && $record['highest_degree'] !== $degreeFilter) {
            return false;
        }
        if ($meritFilter === 'unranked') {
            return $record['target_rank'] === null;
        }
        if (isset($rankFilterRanges[$meritFilter])) {
            [$firstRank, $lastRank] = $rankFilterRanges[$meritFilter];
            return $record['target_rank'] !== null
                && $record['target_rank'] >= $firstRank
                && $record['target_rank'] <= $lastRank;
        }
        return true;
    }
));
$itemsPerPage = 10;
$totalFilteredRecords = count($visibleFacultyRecords);
$totalPages = max(1, (int) ceil($totalFilteredRecords / $itemsPerPage));
$pageInput = $_GET['p'] ?? 1;
$currentPage = is_scalar($pageInput) && filter_var($pageInput, FILTER_VALIDATE_INT) !== false
    ? max(1, (int) $pageInput)
    : 1;
$currentPage = min($currentPage, $totalPages);
$pagedFacultyRecords = array_slice($visibleFacultyRecords, ($currentPage - 1) * $itemsPerPage, $itemsPerPage);
$paginationQuery = array_filter([
    'search' => $searchQuery,
    'degree' => $degreeFilter,
    'merit' => $meritFilter,
], static fn (string $value): bool => $value !== '');
$rankingReturnQuery = $paginationQuery;
if ($currentPage > 1) {
    $rankingReturnQuery['p'] = $currentPage;
}
$rankingReturnUrl = 'faculty-ranking.php' . ($rankingReturnQuery !== [] ? '?' . http_build_query($rankingReturnQuery) : '');
$paginationUrl = static function (int $targetPage) use ($paginationQuery): string {
    return 'faculty-ranking.php?' . http_build_query(array_merge($paginationQuery, ['p' => $targetPage]));
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSTA</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="<?= is_logged_in() ? 'dashboard-page page-' . h($page) : 'auth-page' ?>">
<header class="topbar">
    <div>
        <p class="eyebrow">HUMAN RESOURCES</p>
        <h1>CSTA</h1>
    </div>
    <?php if (is_logged_in()): ?>
        <form method="post" class="logout-form">
            <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
            <input type="hidden" name="action" value="logout">
            <button class="button button-outline" type="submit">Sign out</button>
        </form>
    <?php endif; ?>
</header>

<main class="container<?= is_logged_in() ? ' dashboard-container' : '' ?>">
    <?php if ($notice !== ''): ?>
        <div class="message success" role="status"><?= h($notice) ?></div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="message error" role="alert"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($action === 'setup'): ?>
        <section class="card auth-card signup-card">
            <img class="login-logo" src="csta-logo.png" alt="Colegio de Sta. Teresa de Avila seal">
            <p class="eyebrow">FIRST-TIME SETUP</p>
            <h2>Create the HR account</h2>
            <p class="muted">Set the HR username, password, and a separate recovery code. Keep the recovery code somewhere private; it is required to reset a forgotten password or recover the username.</p>
            <form method="post" class="stack">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="setup">
                <label>HR username
                    <input name="username" required minlength="3" maxlength="30" pattern="[-A-Za-z0-9._]+" autocomplete="username">
                </label>
                <label>Password (at least 5 characters)
                    <input type="password" name="password" required minlength="5" autocomplete="new-password">
                </label>
                <label>Recovery code (at least 5 characters)
                    <input type="password" name="recovery_code" required minlength="5" autocomplete="new-password">
                </label>
                <button class="button" type="submit">Create HR account</button>
            </form>
        </section>
    <?php elseif ($action === 'recover'): ?>
        <section class="card auth-card">
            <p class="eyebrow">ACCOUNT RECOVERY</p>
            <h2>Reset your password</h2>
            <p class="muted">Enter your HR username and the recovery code created during first-time setup.</p>
            <form method="post" class="stack">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="recover">
                <label>HR username
                    <input name="username" required minlength="3" maxlength="30" pattern="[-A-Za-z0-9._]+" autocomplete="username">
                </label>
                <label>Recovery code
                    <input type="password" name="recovery_code" required autocomplete="off">
                </label>
                <label>New password (at least 12 characters)
                    <input type="password" name="new_password" required minlength="12" autocomplete="new-password">
                </label>
                <button class="button" type="submit">Reset password</button>
            </form>
            <p class="form-footer"><a href="System.php">Back to sign in</a></p>
        </section>
    <?php elseif (!is_logged_in()): ?>
        <section class="card auth-card login-card">
            <img class="login-logo" src="csta-logo.png" alt="Colegio de Sta. Teresa de Avila seal">
            <p class="eyebrow">CSTA HUMAN RESOURCES</p>
            <h2>Sign in</h2>
            <form method="post" class="stack">
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="action" value="login">
                <label>Username
                    <input name="username" required autocomplete="username">
                </label>
                <label>Password
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <button class="button" type="submit">Sign in</button>
            </form>
            <p class="form-footer"><a href="System.php?action=recover">Forgot username or password?</a></p>
        </section>
    <?php else: ?>
        <?php
        $facultyCount = count($facultyRecords);
        $highestScore = $facultyCount > 0 ? $facultyRecords[0]['score'] : 0;
        $averageScore = $facultyCount > 0
            ? (int) round(array_sum(array_column($facultyRecords, 'score')) / $facultyCount)
            : 0;
        $meritEligible = count(array_filter(
            $facultyRecords,
            static fn (array $record): bool => $record['rank'] !== null && !$record['promotion_blocked']
        ));
        $scoreBands = [];
        $bandColors = ['blue', 'pink', 'gold', 'gold'];
        foreach (array_values($rankGroups) as $index => $group) {
            $firstRank = $rankingPolicy['ranks'][$group['first']];
            $lastRank = $rankingPolicy['ranks'][$group['last']];
            $scoreBands[] = [
                'label' => $group['label'],
                'min' => $firstRank['minimum'],
                'max' => $lastRank['maximum'],
                'first_rank' => $group['first'],
                'last_rank' => $group['last'],
                'color' => $bandColors[$index],
            ];
        }
        foreach ($scoreBands as &$band) {
            $band['count'] = count(array_filter(
                $facultyRecords,
                static fn (array $record): bool => $record['target_rank'] !== null
                    && $record['target_rank'] >= $band['first_rank']
                    && $record['target_rank'] <= $band['last_rank']
            ));
        }
        unset($band);
        $maxBandCount = max(1, ...array_column($scoreBands, 'count'));
        $degreeDistribution = [];
        foreach ($rankingPolicy['academic']['degree_points'] as $degree => $degreePointValue) {
            $degreeDistribution[] = [
                'label' => degree_labels()[$degree],
                'count' => count(array_filter(
                    $facultyRecords,
                    static fn (array $record): bool => !$record['legacy_record'] && $record['highest_degree'] === $degree
                )),
            ];
        }
        if ($legacyRecordCount > 0) {
            $degreeDistribution[] = ['label' => 'Not rated (legacy)', 'count' => $legacyRecordCount];
        }
        $maxDegreeCount = max(1, ...array_column($degreeDistribution, 'count'));
        $credentialTotals = [
            ['label' => 'Academic preparation', 'points' => 0, 'max' => $rankingPolicy['academic']['cap'], 'color' => 'credential-degree'],
            ['label' => 'Experience and academic work', 'points' => 0, 'max' => $rankingPolicy['experience']['cap'], 'color' => 'credential-experience'],
            ['label' => 'Competence', 'points' => 0, 'max' => $rankingPolicy['competence']['cap'], 'color' => 'credential-publications'],
            ['label' => 'Extension services', 'points' => 0, 'max' => $rankingPolicy['extension']['cap'], 'color' => 'credential-training'],
        ];
        foreach ($facultyRecords as $record) {
            $credentialTotals[0]['points'] += $record['academic_points'];
            $credentialTotals[1]['points'] += $record['experience_points'];
            $credentialTotals[2]['points'] += $record['competence_points'];
            $credentialTotals[3]['points'] += $record['extension_points'];
        }
        $maxCredentialTotal = max(1, ...array_column($credentialTotals, 'points'));
        ?>
        <div class="app-shell">
            <aside class="sidebar" aria-label="Dashboard navigation">
                <a class="brand" href="dashboard.php"><span class="brand-mark">C</span><span>CSTA <span class="brand-light">HR</span></span></a>
                <p class="sidebar-label">WORKSPACE</p>
                <a class="nav-link<?= $page === 'overview' ? ' active' : '' ?>" href="dashboard.php"><span class="nav-icon">⌂</span>Dashboard</a>
                <a class="nav-link<?= $page === 'add' ? ' active' : '' ?>" href="add-faculty.php"><span class="nav-icon">＋</span>Add credentials</a>
                <a class="nav-link<?= $page === 'ranking' ? ' active' : '' ?>" href="faculty-ranking.php"><span class="nav-icon">▤</span>Faculty ranking</a>
                <a class="nav-link<?= $page === 'rubric' ? ' active' : '' ?>" href="scoring-rubric.php"><span class="nav-icon">◇</span>Scoring rubric</a>
                <div class="sidebar-note">
                    <span class="note-icon">✓</span>
                    <strong>HR workspace</strong>
                    <span>Credentials and faculty ranking records are stored locally.</span>
                </div>
            </aside>
            <main class="dashboard-main">
                <div class="dashboard-heading">
                    <div>
                        <p class="eyebrow"><?= h(strtoupper($pageTitles[$page][0])) ?></p>
                        <h2><?= h($pageTitles[$page][1]) ?></h2>
                        <p class="muted"><?= h($pageTitles[$page][2]) ?></p>
                    </div>
                    <?php if ($page === 'overview'): ?>
                        <span class="live-badge"><span></span> Ranking updated</span>
                    <?php endif; ?>
                </div>

                <?php if ($page === 'overview'): ?>
                <section class="metric-grid" aria-label="Faculty summary">
                    <article class="metric-card">
                        <div class="metric-icon icon-mint">♙</div>
                        <span class="metric-label">Total faculty</span>
                        <strong><?= $facultyCount ?></strong>
                        <span class="metric-foot">Records in the system</span>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon icon-blue">★</div>
                        <span class="metric-label">Average points</span>
                        <strong><?= $averageScore ?><small> / 500</small></strong>
                        <span class="metric-foot">Across all faculty</span>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon icon-pink">↗</div>
                        <span class="metric-label">Rank eligible</span>
                        <strong><?= $meritEligible ?></strong>
                        <span class="metric-foot">Meets score and promotion requirements</span>
                    </article>
                    <article class="metric-card">
                        <div class="metric-icon icon-gold">✦</div>
                        <span class="metric-label">Highest score</span>
                        <strong><?= $highestScore ?><small> / 500</small></strong>
                        <span class="metric-foot">Top faculty score</span>
                    </article>
                </section>

                <section class="insights-grid" aria-label="Faculty score insights">
                    <article class="panel score-panel">
                        <div class="panel-heading">
                            <div>
                                <span class="panel-kicker">SCORE DISTRIBUTION</span>
                                <h3>Faculty by points band</h3>
                            </div>
                            <span class="chart-total"><?= $facultyCount ?> total</span>
                        </div>
                        <div class="score-chart" role="img" aria-label="Faculty counts by rank score band">
                            <?php foreach ($scoreBands as $band): ?>
                                <?php $barHeight = $band['count'] > 0 ? max(12, (int) round($band['count'] / $maxBandCount * 100)) : 3; ?>
                                <div class="chart-column">
                                    <span class="chart-count"><?= $band['count'] ?></span>
                                    <div class="chart-track">
                                        <span class="chart-bar <?= h($band['color']) ?>" style="height: <?= $barHeight ?>%"></span>
                                    </div>
                                    <span class="chart-label"><?= h($band['label']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="chart-legend"><span><i class="legend-mint"></i>Needs review</span><span><i class="legend-blue"></i>Faculty rank bands</span></div>
                    </article>

                    <article class="panel leaders-panel">
                        <div class="panel-heading">
                            <div>
                                <span class="panel-kicker">TOP PERFORMERS</span>
                                <h3>Highest ranked</h3>
                            </div>
                            <a class="small-link" href="faculty-ranking.php">View all</a>
                        </div>
                        <?php if ($facultyRecords === []): ?>
                            <p class="empty-state compact">Add a faculty record to see rankings here.</p>
                        <?php else: ?>
                            <div class="leader-list">
                                <?php foreach (array_slice($facultyRecords, 0, 3) as $index => $record): ?>
                                    <div class="leader">
                                        <span class="leader-rank"><?= $index + 1 ?></span>
                                        <span class="leader-avatar"><?= h(strtoupper(substr($record['name'], 0, 1))) ?></span>
                                        <span class="leader-info"><strong><?= h($record['name']) ?></strong><small><?= $record['legacy_record'] ? 'Requires rescoring' : h(degree_labels()[$record['highest_degree']]) ?></small></span>
                                        <span class="leader-points"><?= $record['score'] ?><small>pts</small></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                </section>

                <section class="analytics-grid" aria-label="Credential analytics">
                    <article class="panel analytics-panel">
                        <div class="panel-heading">
                            <div>
                                <span class="panel-kicker">DEGREE MIX</span>
                                <h3>Faculty by highest degree</h3>
                            </div>
                            <span class="chart-total"><?= $facultyCount ?> faculty</span>
                        </div>
                        <?php if ($facultyCount === 0): ?>
                            <p class="empty-state compact">Add faculty records to see degree distribution.</p>
                        <?php else: ?>
                            <div class="degree-chart">
                                <?php foreach ($degreeDistribution as $degreeRow): ?>
                                    <?php $degreeWidth = (int) round($degreeRow['count'] / $maxDegreeCount * 100); ?>
                                    <div class="degree-row">
                                        <span class="degree-label"><?= h($degreeRow['label']) ?></span>
                                        <span class="degree-track"><span style="width: <?= $degreeWidth ?>%"></span></span>
                                        <strong><?= $degreeRow['count'] ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                    <article class="panel analytics-panel">
                        <div class="panel-heading">
                            <div>
                                <span class="panel-kicker">POINT CONTRIBUTION</span>
                                <h3>Points by credential</h3>
                            </div>
                            <span class="chart-total"><?= array_sum(array_column($credentialTotals, 'points')) ?> total pts</span>
                        </div>
                        <?php if ($facultyCount === 0): ?>
                            <p class="empty-state compact">Points will appear as credentials are added.</p>
                        <?php else: ?>
                            <div class="credential-chart">
                                <?php foreach ($credentialTotals as $credentialTotal): ?>
                                    <?php $credentialWidth = (int) round($credentialTotal['points'] / $maxCredentialTotal * 100); ?>
                                    <div class="credential-row">
                                        <span><?= h($credentialTotal['label']) ?></span>
                                        <span class="credential-track"><span class="<?= h($credentialTotal['color']) ?>" style="width: <?= $credentialWidth ?>%"></span></span>
                                        <strong><?= $credentialTotal['points'] ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <p class="chart-footnote">Combined points across faculty profiles; maximum category values are per faculty member.</p>
                        <?php endif; ?>
                    </article>
                </section>
                <?php if ($legacyRecordCount > 0): ?>
                    <div class="message error legacy-warning" role="alert"><?= $legacyRecordCount ?> existing record(s) use the old sample rubric and need to be updated under the 500-point instrument before they can receive a rank.</div>
                <?php endif; ?>
                <?php endif; ?>

                <?php if ($page === 'add'): ?>
                <section class="panel form-panel" id="faculty-form">
                    <div class="panel-heading">
                        <div>
                            <span class="panel-kicker">NEW PROFILE</span>
                            <h3>Faculty ranking instrument</h3>
                        </div>
                        <span class="max-points">Maximum score: 500 points</span>
                    </div>
                    <p class="form-policy-note">Enter verified credentials from the faculty ranking instrument. The currently visible experience scoring rules can award up to <?= $visibleExperienceMaximum ?> of <?= $rankingPolicy['experience']['cap'] ?> section points; confirm any additional institutional criteria before using them.</p>
                    <form method="post" class="faculty-form instrument-form">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="add_faculty">
                        <?php $formRecord = empty_faculty_record(); require __DIR__ . '/faculty-fields.php'; ?>
                        <button class="button instrument-submit" type="submit"><span aria-hidden="true">＋</span> Add faculty and calculate rank</button>
                    </form>
                </section>
                <?php endif; ?>

                <?php if ($page === 'rubric'): ?>
                <section class="panel rubric rubric-panel" id="rubric">
                    <details open>
                        <summary><span><span class="panel-kicker">OFFICIAL FACULTY RANKING INSTRUMENT</span><strong>500-point scoring rules and salary matrix</strong></span><span class="summary-chevron">⌄</span></summary>
                        <div class="rubric-grid">
                            <p><strong>I. Academic preparation (max <?= $rankingPolicy['academic']['cap'] ?>):</strong> <?php foreach ($rankingPolicy['academic']['degree_points'] as $degreeKey => $degreePointValue): ?><?= h(degree_labels()[$degreeKey]) ?>: <?= $degreePointValue ?>; <?php endforeach; ?>additional degree awards are <?= $rankingPolicy['academic']['additional_bachelors'] ?>/<?= $rankingPolicy['academic']['additional_masters'] ?>/<?= $rankingPolicy['academic']['additional_doctorates'] ?> (shared cap <?= $rankingPolicy['academic']['additional_cap'] ?>); short courses <?= $rankingPolicy['academic']['short_course_points'] ?> each; honors and examination awards are editable below.</p>
                            <p><strong>II. Experience and academic work (max <?= $rankingPolicy['experience']['cap'] ?>):</strong> teaching/admin rates, work values, conference role points, and their caps are shown in the editable policy below.</p>
                            <p><strong>Visible Section II limitation:</strong> the provided criteria total at most 120 points under the original values, though the form lists 150. The provided pages give no numeric value for patented works. Edit only values approved by HR; no missing criteria are assumed.</p>
                            <p><strong>III. Competence (max <?= $rankingPolicy['competence']['cap'] ?>):</strong> Dean point bands are <?= $rankingPolicy['competence']['dean_high_points'] ?> / <?= $rankingPolicy['competence']['dean_pass_points'] ?> / <?= $rankingPolicy['competence']['dean_fail_points'] ?>, with promotion minimum <?= $rankingPolicy['competence']['dean_pass_threshold'] ?>; student ratings and professional items are configurable below.</p>
                            <p><strong>IV. Extension and community service (max <?= $rankingPolicy['extension']['cap'] ?>):</strong> participation, adviser/chair and member/editor point values and caps are configurable below.</p>
                            <p><strong>Promotion gates:</strong> Instructor 2 requires 21 master's units; Instructor 3 requires thesis/capstone enrollment; Assistant Professor 1 requires a master's degree; Associate Professor 1 requires dissertation enrollment; Professor 1 requires a doctorate. Gates apply cumulatively. Dean evaluation below <?= $rankingPolicy['competence']['dean_pass_threshold'] ?> blocks promotion.</p>
                        </div>
                    </details>
                </section>
                <section class="panel policy-editor" id="policy-editor">
                    <div class="panel-heading">
                        <div>
                            <span class="panel-kicker">HR POLICY SETTINGS</span>
                            <h3>Edit scoring points, rank bands, and salary rates</h3>
                        </div>
                        <span class="chart-total">Changes recalculate faculty rankings</span>
                    </div>
                    <p class="form-policy-note">Only HR can save these settings. Point fields accept whole numbers. Rank ranges must be continuous, with no gaps or overlaps. Changes affect displayed scores and rates immediately; keep a record of policy changes for audit purposes.</p>
                    <form method="post" class="policy-form">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="save_policy">
                        <?php foreach ($policyFieldGroups as $groupKey => $group): ?>
                            <fieldset class="credential-group policy-group">
                                <legend><?= h($group['title']) ?></legend>
                                <?php foreach ($group['fields'] as $fieldKey => $fieldLabel): ?>
                                    <?php if (is_array($fieldLabel)): ?>
                                        <?php foreach ($fieldLabel as $optionKey => $optionLabel): ?>
                                            <label><?= h($optionLabel) ?> (points)
                                                <input type="number" name="policy[<?= h($groupKey) ?>][<?= h($fieldKey) ?>][<?= h($optionKey) ?>]" min="0" max="1000" step="1" value="<?= (int) $rankingPolicy[$groupKey][$fieldKey][$optionKey] ?>" required>
                                            </label>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <label><?= h($fieldLabel) ?>
                                            <input type="number" name="policy[<?= h($groupKey) ?>][<?= h($fieldKey) ?>]" min="<?= str_contains($fieldKey, 'years_per_point') ? 1 : 0 ?>" max="1000" step="1" value="<?= (int) $rankingPolicy[$groupKey][$fieldKey] ?>" required>
                                        </label>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php endforeach; ?>
                        <fieldset class="credential-group policy-group rank-policy-group">
                            <legend>Rank score ranges and hourly rates</legend>
                            <div class="table-wrap policy-table-wrap">
                                <table class="policy-table">
                                    <thead><tr><th>Faculty rank</th><th>Minimum points</th><th>Maximum points</th><th>Hourly rate (PHP)</th></tr></thead>
                                    <tbody>
                                    <?php foreach ($rankingPolicy['ranks'] as $rankIndex => $rankPolicy): ?>
                                        <tr>
                                            <td><strong><?= h($rankPolicy['name']) ?></strong></td>
                                            <?php foreach (['minimum', 'maximum', 'hourly_rate'] as $rankField): ?>
                                                <td><input aria-label="<?= h($rankPolicy['name'] . ' ' . str_replace('_', ' ', $rankField)) ?>" type="number" min="0" max="<?= $rankField === 'hourly_rate' ? 100000 : 500 ?>" step="1" name="policy[ranks][<?= $rankIndex ?>][<?= h($rankField) ?>]" value="<?= (int) $rankPolicy[$rankField] ?>" required></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </fieldset>
                        <div class="policy-actions wide-field">
                            <button class="button" type="submit">Save scoring and salary policy</button>
                        </div>
                    </form>
                    <form method="post" class="policy-reset-form" onsubmit="return confirm('Restore all original scoring points, rank ranges, and hourly rates?');">
                        <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                        <input type="hidden" name="action" value="reset_policy">
                        <button class="button button-outline" type="submit">Restore original policy</button>
                    </form>
                </section>
                <section class="panel salary-matrix">
                    <div class="panel-heading"><div><span class="panel-kicker">RANK AND HOURLY RATE</span><h3>Score-based salary matrix</h3></div><span class="chart-total">Hourly rates in Philippine pesos</span></div>
                    <div class="salary-grid">
                        <?php foreach ($rankingPolicy['ranks'] as $rankPolicy): ?>
                            <div class="salary-row"><strong><?= h($rankPolicy['name']) ?></strong><span><?= $rankPolicy['minimum'] ?>–<?= $rankPolicy['maximum'] ?> pts</span><b>₱<?= $rankPolicy['hourly_rate'] ?> / hr</b></div>
                        <?php endforeach; ?>
                    </div>
                    <p class="chart-footnote">The system applies the points matrix only after promotion requirements are satisfied.</p>
                </section>
                <?php endif; ?>

                <?php if ($page === 'profile'): ?>
                    <?php if ($profileRecord === null): ?>
                        <section class="panel profile-panel">
                            <p class="empty-state">That faculty profile could not be found.</p>
                            <a class="button profile-back" href="faculty-ranking.php">Back to faculty ranking</a>
                        </section>
                    <?php else: ?>
                        <section class="panel profile-panel">
                            <div class="profile-actions">
                                <a class="profile-back" href="<?= h($rankingReturnUrl) ?>">← Back to faculty ranking</a>
                                <button class="button button-outline report-button" type="button" onclick="window.print()">Print profile</button>
                            </div>
                            <div class="profile-identity">
                                <span class="profile-avatar"><?= h(strtoupper(substr($profileRecord['name'], 0, 1))) ?></span>
                                <div>
                                    <span class="panel-kicker">FACULTY PROFILE · RANK #<?= $rankById[$profileRecord['id']] ?></span>
                                    <h3><?= h($profileRecord['name']) ?></h3>
                                    <span class="profile-degree"><?= $profileRecord['legacy_record'] ? 'Not rated — rescore required' : h(degree_labels()[$profileRecord['highest_degree']]) ?></span>
                                </div>
                                <div class="profile-score"><strong><?= $profileRecord['score'] ?></strong><span>of 500 points</span></div>
                            </div>
                            <div class="profile-summary-grid">
                                <div><span>Faculty rank</span><strong><?= h($profileRecord['rank_name']) ?></strong></div>
                                <div><span>Hourly rate</span><strong><?= $profileRecord['hourly_rate'] !== null ? '₱' . $profileRecord['hourly_rate'] . ' / hr' : 'Not assigned' ?></strong></div>
                                <div><span>Dean's evaluation</span><strong><?= $profileRecord['dean_evaluation'] ?> / 100</strong></div>
                                <div><span>Promotion status</span><strong><?= $profileRecord['promotion_blocked'] ? 'Blocked' : 'Eligible' ?></strong></div>
                            </div>
                            <?php if ($profileRecord['promotion_blocked']): ?>
                                <div class="message error"><?= h($profileRecord['promotion_reason']) ?> Score-based rank: <?= h($profileRecord['target_rank_name']) ?>.</div>
                            <?php endif; ?>
                            <div class="profile-breakdown">
                                <h4>Points by ranking instrument section</h4>
                                <div class="breakdown-row"><span>I. Academic preparation</span><span><?= $profileRecord['legacy_record'] ? 'Not rated' : h(degree_labels()[$profileRecord['highest_degree']]) ?></span><strong><?= $profileRecord['academic_points'] ?> / <?= $rankingPolicy['academic']['cap'] ?></strong></div>
                                <div class="breakdown-row"><span>II. Professional and academic experience</span><span>Visible instrument rules</span><strong><?= $profileRecord['experience_points'] ?> / <?= $rankingPolicy['experience']['cap'] ?></strong></div>
                                <div class="breakdown-row"><span>III. Competence</span><span>Dean, student and professional</span><strong><?= $profileRecord['competence_points'] ?> / <?= $rankingPolicy['competence']['cap'] ?></strong></div>
                                <div class="breakdown-row"><span>IV. Extension and community service</span><span>Participation and service</span><strong><?= $profileRecord['extension_points'] ?> / <?= $rankingPolicy['extension']['cap'] ?></strong></div>
                                <div class="breakdown-row breakdown-total"><span>Total points</span><span>Score-based level: <?= h($profileRecord['target_rank_name']) ?></span><strong><?= $profileRecord['score'] ?> / 500</strong></div>
                            </div>
                        </section>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if ($page === 'ranking'): ?>
                <section class="panel ranking-panel" id="ranking">
                    <div class="panel-heading">
                        <div>
                            <span class="panel-kicker">FACULTY RECORDS</span>
                            <h3>Ranking and hourly rate</h3>
                        </div>
                        <div class="report-actions">
                            <a class="button button-outline report-button" href="export-faculty.php">Export CSV</a>
                            <button class="button button-outline report-button print-button" type="button" onclick="window.print()">Print report</button>
                        </div>
                    </div>
                    <form method="get" action="faculty-ranking.php" class="ranking-filters">
                        <label>Search faculty
                            <input type="search" name="search" value="<?= h($searchQuery) ?>" maxlength="100" placeholder="Enter a faculty name">
                        </label>
                        <label>Degree
                            <select name="degree">
                                <option value="">All degrees</option>
                                <?php foreach ($rankingPolicy['academic']['degree_points'] as $degree => $points): ?>
                                    <option value="<?= h($degree) ?>" <?= $degreeFilter === $degree ? 'selected' : '' ?>><?= h(degree_labels()[$degree]) ?></option>
                                <?php endforeach; ?>
                                <?php if ($legacyRecordCount > 0): ?>
                                    <option value="legacy" <?= $degreeFilter === 'legacy' ? 'selected' : '' ?>>Not rated (legacy)</option>
                                <?php endif; ?>
                            </select>
                        </label>
                        <label>Faculty rank band
                            <select name="merit">
                                <option value="">All rank bands</option>
                                <?php foreach ($rankGroups as $groupKey => $group): ?>
                                    <?php $firstRank = $rankingPolicy['ranks'][$group['first']]; $lastRank = $rankingPolicy['ranks'][$group['last']]; ?>
                                    <option value="<?= h($groupKey) ?>" <?= $meritFilter === $groupKey ? 'selected' : '' ?>><?= h($group['label']) ?> (<?= $firstRank['minimum'] ?>–<?= $lastRank['maximum'] ?> points)</option>
                                <?php endforeach; ?>
                                <option value="unranked" <?= $meritFilter === 'unranked' ? 'selected' : '' ?>>Outside rank score bands</option>
                            </select>
                        </label>
                        <button class="button filter-button" type="submit">Apply filters</button>
                        <a class="reset-filters" href="faculty-ranking.php">Clear</a>
                    </form>
                    <?php
                    $firstVisibleRecord = $totalFilteredRecords === 0 ? 0 : (($currentPage - 1) * $itemsPerPage) + 1;
                    $lastVisibleRecord = min($currentPage * $itemsPerPage, $totalFilteredRecords);
                    ?>
                    <p class="results-count">Showing <?= $firstVisibleRecord ?>–<?= $lastVisibleRecord ?> of <?= $totalFilteredRecords ?> matching faculty · <?= count($facultyRecords) ?> total · Sorted by points, highest first</p>
                    <?php if ($facultyRecords === []): ?>
                        <p class="empty-state">No faculty records yet. Add the first record above.</p>
                    <?php elseif ($visibleFacultyRecords === []): ?>
                        <p class="empty-state">No faculty records match these filters. Try changing or clearing the filters.</p>
                    <?php else: ?>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Faculty</th>
                                    <th>Faculty rank</th>
                                    <th>Highest degree</th>
                                    <th>Total points</th>
                                    <th>Hourly rate</th>
                                    <th>Status</th>
                                    <th class="actions-heading">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($pagedFacultyRecords as $record): ?>
                                    <tr>
                                        <td><span class="rank">#<?= $rankById[$record['id']] ?></span></td>
                                        <td class="faculty-name"><a href="profile.php?<?= h(http_build_query(array_merge(['id' => $record['id']], $paginationQuery, ['p' => $currentPage]))) ?>"><?= h($record['name']) ?></a></td>
                                        <td><?= h($record['rank_name']) ?></td>
                                        <td><?= $record['legacy_record'] ? 'Not rated' : h(degree_labels()[$record['highest_degree']]) ?></td>
                                        <td><strong><?= $record['score'] ?> / 500</strong></td>
                                        <td><?= $record['hourly_rate'] !== null ? '₱' . $record['hourly_rate'] . ' / hr' : '—' ?></td>
                                        <td><?= $record['promotion_blocked'] ? '<span class="blocked-status" title="' . h($record['promotion_reason']) . '">Needs review</span>' : ($record['rank'] !== null ? '<span class="merit">Eligible</span>' : '<span class="blocked-status">No matching range</span>') ?></td>
                                        <td class="record-actions">
                                            <details class="edit-record">
                                                <summary>Edit</summary>
                                                <form method="post" class="edit-form">
                                                    <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                    <input type="hidden" name="action" value="edit_faculty">
                                                    <input type="hidden" name="id" value="<?= h($record['id']) ?>">
                                                    <?php $formRecord = $record; require __DIR__ . '/faculty-fields.php'; ?>
                                                    <button class="button save-edit" type="submit">Save changes</button>
                                                </form>
                                            </details>
                                            <form method="post" class="delete-form" onsubmit="return confirm('Delete this faculty record?');">
                                                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                                                <input type="hidden" name="action" value="delete_faculty">
                                                <input type="hidden" name="id" value="<?= h($record['id']) ?>">
                                                <button class="text-button" type="submit">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <nav class="pagination" aria-label="Faculty ranking pages">
                                <?php if ($currentPage > 1): ?>
                                    <a href="<?= h($paginationUrl($currentPage - 1)) ?>" aria-label="Previous page">← Previous</a>
                                <?php else: ?>
                                    <span class="disabled" aria-disabled="true">← Previous</span>
                                <?php endif; ?>
                                <span class="pagination-status">Page <?= $currentPage ?> of <?= $totalPages ?></span>
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="<?= h($paginationUrl($currentPage + 1)) ?>" aria-label="Next page">Next →</a>
                                <?php else: ?>
                                    <span class="disabled" aria-disabled="true">Next →</span>
                                <?php endif; ?>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
                <?php endif; ?>
            </main>
        </div>
    <?php endif; ?>
</main>
<footer>CSTA HR · Local file storage · No database</footer>
</body>
</html>
