<?php
declare(strict_types=1);

$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

const STORAGE_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
const ACCOUNT_FILE = STORAGE_DIR . DIRECTORY_SEPARATOR . 'hr-account.php';
const FACULTY_FILE = STORAGE_DIR . DIRECTORY_SEPARATOR . 'faculty.php';
const POLICY_FILE = STORAGE_DIR . DIRECTORY_SEPARATOR . 'ranking-policy.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalize_username(string $username): string
{
    return strtolower(trim($username));
}

function validate_username(string $username): void
{
    if (!preg_match('/^[a-z0-9._-]{3,30}$/', $username)) {
        throw new RuntimeException('Username must be 3 to 30 characters and use letters, numbers, dots, underscores, or hyphens.');
    }
}

function validate_password(string $password, string $label = 'Password'): void
{
    if (strlen($password) < 12) {
        throw new RuntimeException($label . ' must be at least 12 characters long.');
    }
}

function ensure_storage_directory(): void
{
    if (!is_dir(STORAGE_DIR) && !mkdir(STORAGE_DIR, 0700, true) && !is_dir(STORAGE_DIR)) {
        throw new RuntimeException('Could not create the storage folder. Check folder permissions.');
    }
}

function read_php_data(string $path, array $default): array
{
    if (!is_file($path)) {
        return $default;
    }

    $data = require $path;
    if (!is_array($data)) {
        throw new RuntimeException('A storage file is invalid. Check the PHP error log or restore the file.');
    }

    return $data;
}

function write_php_data(string $path, array $data): void
{
    ensure_storage_directory();
    $temporaryPath = $path . '.' . bin2hex(random_bytes(6)) . '.tmp.php';
    $contents = "<?php\nreturn " . var_export($data, true) . ";\n";
    $bytesWritten = file_put_contents($temporaryPath, $contents, LOCK_EX);

    if ($bytesWritten === false) {
        throw new RuntimeException('Could not save data. Check storage folder permissions.');
    }
    if (!rename($temporaryPath, $path)) {
        unlink($temporaryPath);
        throw new RuntimeException('Could not finalize the saved data. Check storage folder permissions.');
    }
}

function account_exists(): bool
{
    return is_file(ACCOUNT_FILE);
}

function get_account(): ?array
{
    if (!account_exists()) {
        return null;
    }

    $account = read_php_data(ACCOUNT_FILE, []);
    foreach (['username', 'password_hash', 'recovery_hash'] as $key) {
        if (!isset($account[$key]) || !is_string($account[$key])) {
            throw new RuntimeException('The HR account file is invalid. Restore it from a backup or contact your system administrator.');
        }
    }

    return $account;
}

function save_account(array $account): void
{
    write_php_data(ACCOUNT_FILE, $account);
}

function get_faculty(): array
{
    $records = read_php_data(FACULTY_FILE, []);
    foreach ($records as $record) {
        if (
            !is_array($record)
            || !isset($record['id'], $record['name'])
            || !is_string($record['id'])
            || !is_string($record['name'])
            || (!isset($record['highest_degree']) && !isset($record['degree']))
        ) {
            throw new RuntimeException('The faculty data file is invalid. Restore it from a backup or contact your system administrator.');
        }
    }

    return $records;
}

function save_faculty(array $records): void
{
    write_php_data(FACULTY_FILE, array_values($records));
}

function degree_points(): array
{
    return get_ranking_policy()['academic']['degree_points'];
}

function default_ranking_policy(): array
{
    return [
        'academic' => [
            'degree_points' => [
                'bachelor-4' => 20,
                'bachelor-5' => 70,
                'masters' => 80,
                'physician-lawyer' => 110,
                'doctorate' => 150,
            ],
            'additional_bachelors' => 20,
            'additional_masters' => 30,
            'additional_doctorates' => 50,
            'additional_cap' => 50,
            'short_course_points' => 2,
            'short_course_cap' => 10,
            'honors' => ['none' => 0, 'cum-laude' => 5, 'magna-cum-laude' => 7, 'summa-cum-laude' => 10],
            'exam_subprofessional' => 20,
            'exam_professional' => 30,
            'cap' => 200,
        ],
        'experience' => [
            'teaching_csta_per_year' => 1,
            'teaching_other_years_per_point' => 2,
            'teaching_other_points_per_period' => 1,
            'teaching_cap' => 30,
            'administrative_csta_per_year' => 1,
            'administrative_other_years_per_point' => 2,
            'administrative_other_points_per_period' => 1,
            'administrative_cap' => 30,
            'book_points' => 10,
            'research_points' => 7,
            'project_points' => 10,
            'works_cap' => 30,
            'speaker_points' => 5,
            'committee_points' => 4,
            'participant_points' => 2,
            'conference_cap' => 30,
            'cap' => 150,
        ],
        'competence' => [
            'dean_high_threshold' => 98,
            'dean_pass_threshold' => 92,
            'dean_high_points' => 80,
            'dean_pass_points' => 75,
            'dean_fail_points' => 40,
            'student_satisfactory_points' => 30,
            'student_very_satisfactory_points' => 50,
            'student_excellent_points' => 60,
            'national_award_points' => 5,
            'institutional_award_points' => 2,
            'award_cap' => 20,
            'international_grant_points' => 10,
            'local_grant_points' => 5,
            'grant_cap' => 20,
            'affiliation_president_points' => 4,
            'affiliation_position_points' => 3,
            'affiliation_member_points' => 2,
            'affiliation_cap' => 20,
            'professional_cap' => 60,
            'cap' => 100,
        ],
        'extension' => [
            'participation_day_points' => 3,
            'participation_cap' => 20,
            'advisor_points' => 2,
            'advisor_cap' => 20,
            'member_points' => 1,
            'member_cap' => 20,
            'cap' => 50,
        ],
        'ranks' => [
            ['name' => 'Instructor 1', 'minimum' => 70, 'maximum' => 93, 'hourly_rate' => 150],
            ['name' => 'Instructor 2', 'minimum' => 94, 'maximum' => 117, 'hourly_rate' => 173],
            ['name' => 'Instructor 3', 'minimum' => 118, 'maximum' => 141, 'hourly_rate' => 184],
            ['name' => 'Assistant Professor 1', 'minimum' => 142, 'maximum' => 165, 'hourly_rate' => 195],
            ['name' => 'Assistant Professor 2', 'minimum' => 166, 'maximum' => 189, 'hourly_rate' => 206],
            ['name' => 'Assistant Professor 3', 'minimum' => 190, 'maximum' => 213, 'hourly_rate' => 217],
            ['name' => 'Assistant Professor 4', 'minimum' => 214, 'maximum' => 237, 'hourly_rate' => 228],
            ['name' => 'Assistant Professor 5', 'minimum' => 238, 'maximum' => 261, 'hourly_rate' => 239],
            ['name' => 'Associate Professor 1', 'minimum' => 262, 'maximum' => 285, 'hourly_rate' => 250],
            ['name' => 'Associate Professor 2', 'minimum' => 286, 'maximum' => 309, 'hourly_rate' => 261],
            ['name' => 'Associate Professor 3', 'minimum' => 310, 'maximum' => 333, 'hourly_rate' => 272],
            ['name' => 'Associate Professor 4', 'minimum' => 334, 'maximum' => 357, 'hourly_rate' => 283],
            ['name' => 'Associate Professor 5', 'minimum' => 358, 'maximum' => 381, 'hourly_rate' => 294],
            ['name' => 'Professor 1', 'minimum' => 382, 'maximum' => 405, 'hourly_rate' => 305],
            ['name' => 'Professor 2', 'minimum' => 406, 'maximum' => 429, 'hourly_rate' => 316],
            ['name' => 'Professor 3', 'minimum' => 430, 'maximum' => 453, 'hourly_rate' => 327],
            ['name' => 'Professor 4', 'minimum' => 454, 'maximum' => 477, 'hourly_rate' => 338],
            ['name' => 'Professor 5', 'minimum' => 478, 'maximum' => 500, 'hourly_rate' => 350],
        ],
    ];
}

function validate_ranking_policy(array $input): array
{
    $defaults = default_ranking_policy();
    $validateSection = static function (array $candidate, array $template, string $path = 'policy') use (&$validateSection): array {
        $validated = [];
        foreach ($template as $key => $defaultValue) {
            $fieldPath = $path . '[' . $key . ']';
            if (!array_key_exists($key, $candidate)) {
                throw new RuntimeException('A required scoring policy value is missing.');
            }
            $value = $candidate[$key];
            if (is_array($defaultValue)) {
                if (!is_array($value)) {
                    throw new RuntimeException('A scoring policy section is invalid.');
                }
                if (array_is_list($defaultValue)) {
                    if (count($value) !== count($defaultValue)) {
                        throw new RuntimeException('The rank matrix must contain all 18 ranks.');
                    }
                    $validated[$key] = [];
                    foreach ($defaultValue as $index => $defaultRow) {
                        if (!isset($value[$index]) || !is_array($value[$index])) {
                            throw new RuntimeException('A rank matrix row is missing or invalid.');
                        }
                        $row = $value[$index];
                        $validatedRow = ['name' => $defaultRow['name']];
                        foreach (['minimum', 'maximum', 'hourly_rate'] as $rowKey) {
                            $rowValue = filter_var($row[$rowKey] ?? null, FILTER_VALIDATE_INT);
                            $rowMaximum = $rowKey === 'hourly_rate' ? 100000 : 500;
                            if ($rowValue === false || $rowValue < 0 || $rowValue > $rowMaximum) {
                                throw new RuntimeException('Rank points must be from 0 to 500 and hourly rates from 0 to 100000 PHP.');
                            }
                            $validatedRow[$rowKey] = $rowValue;
                        }
                        $validated[$key][] = $validatedRow;
                    }
                    continue;
                }
                $validated[$key] = $validateSection($value, $defaultValue, $fieldPath);
                continue;
            }
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if ($integer === false || $integer < 0 || $integer > 1000) {
                throw new RuntimeException('Scoring point values and caps must be whole numbers between 0 and 1000.');
            }
            $validated[$key] = $integer;
        }
        return $validated;
    };

    $policy = $validateSection($input, $defaults);
    if (
        $policy['academic']['cap'] > 500
        || $policy['experience']['cap'] > 500
        || $policy['competence']['cap'] > 500
        || $policy['extension']['cap'] > 500
        || $policy['experience']['teaching_other_years_per_point'] < 1
        || $policy['experience']['administrative_other_years_per_point'] < 1
        || $policy['competence']['dean_high_threshold'] > 100
        || $policy['competence']['dean_pass_threshold'] > 100
        || $policy['competence']['dean_pass_threshold'] > $policy['competence']['dean_high_threshold']
    ) {
        throw new RuntimeException('Check section caps, experience periods, and Dean evaluation thresholds.');
    }

    foreach ($policy['ranks'] as $index => $rank) {
        if ($rank['minimum'] > $rank['maximum']) {
            throw new RuntimeException('Each rank minimum must be less than or equal to its maximum.');
        }
        if ($index > 0 && $rank['minimum'] !== $policy['ranks'][$index - 1]['maximum'] + 1) {
            throw new RuntimeException('Rank score ranges must be continuous and have no gaps or overlaps.');
        }
    }
    return $policy;
}

function get_ranking_policy(): array
{
    if (!is_file(POLICY_FILE)) {
        return default_ranking_policy();
    }
    return validate_ranking_policy(read_php_data(POLICY_FILE, []));
}

function save_ranking_policy(array $policy): void
{
    write_php_data(POLICY_FILE, validate_ranking_policy($policy));
}

function degree_labels(): array
{
    return [
        'bachelor-4' => "4-year Bachelor's degree",
        'bachelor-5' => "5-year Bachelor's degree",
        'masters' => "Aligned Master's degree",
        'physician-lawyer' => 'Physician / Lawyer',
        'doctorate' => "Doctor's degree",
    ];
}

function empty_faculty_record(): array
{
    return [
        'name' => '',
        'highest_degree' => 'bachelor-4',
        'additional_bachelors' => 0,
        'additional_masters' => 0,
        'additional_doctorates' => 0,
        'short_course_certificates' => 0,
        'academic_honors' => 'none',
        'government_exam' => 'none',
        'masters_units' => 0,
        'thesis_enrolled' => false,
        'dissertation_enrolled' => false,
        'teaching_years_csta' => 0,
        'teaching_years_other' => 0,
        'admin_years_csta' => 0,
        'admin_years_other' => 0,
        'books_count' => 0,
        'books_authors' => 1,
        'research_count' => 0,
        'research_authors' => 1,
        'projects_count' => 0,
        'projects_creators' => 1,
        'conference_speaker' => 0,
        'conference_committee' => 0,
        'conference_participant' => 0,
        'dean_evaluation' => 0,
        'student_evaluation' => 'satisfactory',
        'national_awards' => 0,
        'institutional_awards' => 0,
        'international_grants' => 0,
        'local_grants' => 0,
        'affiliation_president' => 0,
        'affiliation_position' => 0,
        'affiliation_member' => 0,
        'extension_days' => 0,
        'extension_advisor_years' => 0,
        'extension_member_years' => 0,
    ];
}

function validate_faculty_input(array $input): array
{
    $nameInput = $input['name'] ?? '';
    if (!is_string($nameInput)) {
        throw new RuntimeException('Enter a valid faculty name.');
    }
    $name = trim($nameInput);
    $defaults = empty_faculty_record();
    $integer = static function (string $key, int $maximum, int $minimum = 0) use ($input): int {
        $value = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
        if ($value === false || $value < $minimum || $value > $maximum) {
            throw new RuntimeException('Check the value entered for ' . str_replace('_', ' ', $key) . '.');
        }

        return $value;
    };

    if ($name === '' || strlen($name) > 100) {
        throw new RuntimeException('Enter a faculty name of 1 to 100 characters.');
    }
    $highestDegreeInput = $input['highest_degree'] ?? '';
    $highestDegree = is_string($highestDegreeInput) ? $highestDegreeInput : '';
    if (!array_key_exists($highestDegree, degree_points())) {
        throw new RuntimeException('Choose a valid degree level.');
    }
    $honors = ['none', 'cum-laude', 'magna-cum-laude', 'summa-cum-laude'];
    $exams = ['none', 'subprofessional', 'professional'];
    $studentEvaluations = ['satisfactory', 'very-satisfactory', 'excellent'];
    $academicHonorsInput = $input['academic_honors'] ?? '';
    $governmentExamInput = $input['government_exam'] ?? '';
    $studentEvaluationInput = $input['student_evaluation'] ?? '';
    $academicHonors = is_string($academicHonorsInput) ? $academicHonorsInput : '';
    $governmentExam = is_string($governmentExamInput) ? $governmentExamInput : '';
    $studentEvaluation = is_string($studentEvaluationInput) ? $studentEvaluationInput : '';
    if (!in_array($academicHonors, $honors, true)) {
        throw new RuntimeException('Choose a valid academic honors level.');
    }
    if (!in_array($governmentExam, $exams, true)) {
        throw new RuntimeException('Choose a valid government examination level.');
    }
    if (!in_array($studentEvaluation, $studentEvaluations, true)) {
        throw new RuntimeException('Choose a valid student evaluation level.');
    }

    $record = [
        'name' => $name,
        'highest_degree' => $highestDegree,
        'academic_honors' => $academicHonors,
        'government_exam' => $governmentExam,
        'student_evaluation' => $studentEvaluation,
    ];
    foreach ([
        'additional_bachelors' => 50,
        'additional_masters' => 50,
        'additional_doctorates' => 50,
        'short_course_certificates' => 100,
        'masters_units' => 500,
        'teaching_years_csta' => 100,
        'teaching_years_other' => 200,
        'admin_years_csta' => 100,
        'admin_years_other' => 200,
        'books_count' => 100,
        'books_authors' => 100,
        'research_count' => 100,
        'research_authors' => 100,
        'projects_count' => 100,
        'projects_creators' => 100,
        'conference_speaker' => 100,
        'conference_committee' => 100,
        'conference_participant' => 100,
        'dean_evaluation' => 100,
        'national_awards' => 100,
        'institutional_awards' => 100,
        'international_grants' => 100,
        'local_grants' => 100,
        'affiliation_president' => 100,
        'affiliation_position' => 100,
        'affiliation_member' => 100,
        'extension_days' => 100,
        'extension_advisor_years' => 100,
        'extension_member_years' => 100,
    ] as $key => $maximum) {
        $record[$key] = $integer($key, $maximum, str_ends_with($key, '_authors') || str_ends_with($key, '_creators') ? 1 : 0);
    }

    foreach (['thesis_enrolled', 'dissertation_enrolled'] as $key) {
        $inputValue = $input[$key] ?? '0';
        $value = is_string($inputValue) ? $inputValue : '';
        if (!in_array($value, ['0', '1'], true)) {
            throw new RuntimeException('Choose a valid enrollment status.');
        }
        $record[$key] = $value === '1';
    }

    return array_merge($defaults, $record);
}

function rank_faculty(array $records): array
{
    $policy = get_ranking_policy();
    $academicPolicy = $policy['academic'];
    $experiencePolicy = $policy['experience'];
    $competencePolicy = $policy['competence'];
    $extensionPolicy = $policy['extension'];
    $degreePoints = $academicPolicy['degree_points'];
    $legacyDegrees = [
        'PhD' => 'doctorate',
        "Master's" => 'masters',
        "Bachelor's" => 'bachelor-4',
        'Other / none' => 'bachelor-4',
    ];
    $honorPoints = $academicPolicy['honors'];
    $rankNames = array_column($policy['ranks'], 'name');
    foreach ($records as &$record) {
        $legacy = !isset($record['highest_degree']);
        if ($legacy) {
            $record['highest_degree'] = $legacyDegrees[$record['degree'] ?? ''] ?? 'bachelor-4';
        }
        $record = array_merge(empty_faculty_record(), $record);
        if (!array_key_exists($record['highest_degree'], $degreePoints)) {
            throw new RuntimeException('A faculty record has an invalid degree. Correct or remove that record.');
        }
        $record['legacy_record'] = $legacy;
        if ($legacy) {
            $record['academic_points'] = 0;
            $record['experience_points'] = 0;
            $record['teaching_points'] = 0;
            $record['administrative_points'] = 0;
            $record['publication_points'] = 0;
            $record['conference_points'] = 0;
            $record['dean_points'] = 0;
            $record['student_points'] = 0;
            $record['professional_points'] = 0;
            $record['competence_points'] = 0;
            $record['extension_points'] = 0;
            $record['score'] = 0;
            $record['target_rank'] = null;
            $record['target_rank_name'] = 'Requires rescoring';
            $record['rank'] = null;
            $record['rank_name'] = 'Requires rescoring';
            $record['hourly_rate'] = null;
            $record['promotion_blocked'] = true;
            $record['promotion_reason'] = 'This record uses the old sample rubric. Re-enter the credentials using the official ranking instrument.';
            continue;
        }

        $additionalDegreePoints = min(
            $academicPolicy['additional_cap'],
            $record['additional_bachelors'] * $academicPolicy['additional_bachelors']
                + $record['additional_masters'] * $academicPolicy['additional_masters']
                + $record['additional_doctorates'] * $academicPolicy['additional_doctorates']
        );
        $examPoints = match ($record['government_exam']) {
            'professional' => $academicPolicy['exam_professional'],
            'subprofessional' => $academicPolicy['exam_subprofessional'],
            default => 0,
        };
        $academicPoints = min(
            $academicPolicy['cap'],
            $degreePoints[$record['highest_degree']]
                + $additionalDegreePoints
                + min($academicPolicy['short_course_cap'], $record['short_course_certificates'] * $academicPolicy['short_course_points'])
                + $honorPoints[$record['academic_honors']]
                + $examPoints
        );

        $teachingPoints = min(
            $experiencePolicy['teaching_cap'],
            $record['teaching_years_csta'] * $experiencePolicy['teaching_csta_per_year']
                + intdiv($record['teaching_years_other'], $experiencePolicy['teaching_other_years_per_point'])
                    * $experiencePolicy['teaching_other_points_per_period']
        );
        $administrativePoints = min(
            $experiencePolicy['administrative_cap'],
            $record['admin_years_csta'] * $experiencePolicy['administrative_csta_per_year']
                + intdiv($record['admin_years_other'], $experiencePolicy['administrative_other_years_per_point'])
                    * $experiencePolicy['administrative_other_points_per_period']
        );
        $publicationPoints = min(
            $experiencePolicy['works_cap'],
            ($record['books_count'] * $experiencePolicy['book_points'] / $record['books_authors'])
                + ($record['research_count'] * $experiencePolicy['research_points'] / $record['research_authors'])
                + ($record['projects_count'] * $experiencePolicy['project_points'] / $record['projects_creators'])
        );
        $conferencePoints = min(
            $experiencePolicy['conference_cap'],
            $record['conference_speaker'] * $experiencePolicy['speaker_points']
                + $record['conference_committee'] * $experiencePolicy['committee_points']
                + $record['conference_participant'] * $experiencePolicy['participant_points']
        );
        $experiencePoints = min($experiencePolicy['cap'], $teachingPoints + $administrativePoints + $publicationPoints + $conferencePoints);

        $deanPoints = $record['dean_evaluation'] >= $competencePolicy['dean_high_threshold']
            ? $competencePolicy['dean_high_points']
            : ($record['dean_evaluation'] >= $competencePolicy['dean_pass_threshold']
                ? $competencePolicy['dean_pass_points']
                : $competencePolicy['dean_fail_points']);
        $studentPoints = match ($record['student_evaluation']) {
            'excellent' => $competencePolicy['student_excellent_points'],
            'very-satisfactory' => $competencePolicy['student_very_satisfactory_points'],
            default => $competencePolicy['student_satisfactory_points'],
        };
        $awardPoints = min(
            $competencePolicy['award_cap'],
            $record['national_awards'] * $competencePolicy['national_award_points']
                + $record['institutional_awards'] * $competencePolicy['institutional_award_points']
        );
        $grantPoints = min(
            $competencePolicy['grant_cap'],
            $record['international_grants'] * $competencePolicy['international_grant_points']
                + $record['local_grants'] * $competencePolicy['local_grant_points']
        );
        $affiliationPoints = min(
            $competencePolicy['affiliation_cap'],
            $record['affiliation_president'] * $competencePolicy['affiliation_president_points']
                + $record['affiliation_position'] * $competencePolicy['affiliation_position_points']
                + $record['affiliation_member'] * $competencePolicy['affiliation_member_points']
        );
        $professionalPoints = min($competencePolicy['professional_cap'], $awardPoints + $grantPoints + $affiliationPoints);
        $competencePoints = min($competencePolicy['cap'], $deanPoints + $studentPoints + $professionalPoints);

        $extensionPoints = min(
            $extensionPolicy['cap'],
            min($extensionPolicy['participation_cap'], $record['extension_days'] * $extensionPolicy['participation_day_points'])
                + min($extensionPolicy['advisor_cap'], $record['extension_advisor_years'] * $extensionPolicy['advisor_points'])
                + min($extensionPolicy['member_cap'], $record['extension_member_years'] * $extensionPolicy['member_points'])
        );

        $record['academic_points'] = $academicPoints;
        $record['experience_points'] = round($experiencePoints, 2);
        $record['teaching_points'] = $teachingPoints;
        $record['administrative_points'] = $administrativePoints;
        $record['publication_points'] = round($publicationPoints, 2);
        $record['conference_points'] = $conferencePoints;
        $record['dean_points'] = $deanPoints;
        $record['student_points'] = $studentPoints;
        $record['professional_points'] = $professionalPoints;
        $record['competence_points'] = $competencePoints;
        $record['extension_points'] = $extensionPoints;
        $record['score'] = min(500, (int) round($academicPoints + $experiencePoints + $competencePoints + $extensionPoints));
        $record['target_rank'] = null;
        $record['rank'] = null;
        $record['hourly_rate'] = null;
        $record['promotion_blocked'] = false;
        $record['promotion_reason'] = '';

        foreach ($policy['ranks'] as $index => $rank) {
            if ($record['score'] >= $rank['minimum'] && $record['score'] <= $rank['maximum']) {
                $record['target_rank'] = $index;
                break;
            }
        }

        $degreeLevel = ['bachelor-4' => 0, 'bachelor-5' => 0, 'masters' => 1, 'physician-lawyer' => 1, 'doctorate' => 2];
        if ($record['dean_evaluation'] < $competencePolicy['dean_pass_threshold']) {
            $record['promotion_blocked'] = true;
            $record['promotion_reason'] = 'No promotion: dean evaluation must be at least ' . $competencePolicy['dean_pass_threshold'] . '.';
        } elseif ($record['target_rank'] !== null) {
            $eligibleRank = $record['target_rank'];
            $requirements = [
                [1, $record['masters_units'] >= 21, 'At least 21 master’s degree units are required for Instructor 2.'],
                [2, $record['thesis_enrolled'], 'Thesis/capstone enrollment is required for Instructor 3.'],
                [3, $degreeLevel[$record['highest_degree']] >= 1, 'A master’s degree or higher is required for Assistant Professor 1.'],
                [8, $record['dissertation_enrolled'], 'Dissertation enrollment is required for Associate Professor 1.'],
                [13, $record['highest_degree'] === 'doctorate', 'A doctorate is required for Professor 1.'],
            ];
            foreach ($requirements as [$requiredRank, $met, $reason]) {
                if ($eligibleRank >= $requiredRank && !$met) {
                    $eligibleRank = $requiredRank - 1;
                    $record['promotion_blocked'] = true;
                    $record['promotion_reason'] = $reason;
                    break;
                }
            }
            $record['rank'] = $eligibleRank;
            $record['hourly_rate'] = $policy['ranks'][$eligibleRank]['hourly_rate'];
        }
        $record['rank_name'] = $record['rank'] === null ? 'No rank assigned' : $rankNames[$record['rank']];
        $record['target_rank_name'] = $record['target_rank'] === null ? 'Outside rank score bands' : $rankNames[$record['target_rank']];
    }
    unset($record);

    usort($records, static function (array $left, array $right): int {
        $scoreOrder = $right['score'] <=> $left['score'];
        return $scoreOrder !== 0 ? $scoreOrder : strcasecmp($left['name'], $right['name']);
    });

    return $records;
}

function is_logged_in(): bool
{
    return ($_SESSION['authenticated'] ?? false) === true;
}

function require_authentication(): void
{
    if (!is_logged_in()) {
        throw new RuntimeException('Please sign in to continue.');
    }
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(): void
{
    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals(csrf_token(), $submittedToken)) {
        throw new RuntimeException('Your session token expired. Refresh the page and try again.');
    }
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}
