<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (!is_logged_in()) {
    redirect('System.php');
}

$records = rank_faculty(get_faculty());
$output = fopen('php://output', 'wb');
if ($output === false) {
    throw new RuntimeException('Could not prepare the CSV export.');
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="csta-faculty-ranking-' . date('Y-m-d') . '.csv"');
header('X-Content-Type-Options: nosniff');
fwrite($output, "\xEF\xBB\xBF");

$writeRow = static function ($stream, array $fields): void {
    $safeFields = array_map(static function (string|int|float $value): string {
        $cell = (string) $value;
        if (preg_match('/^[\x00-\x20]*[=+\-@]/', $cell) === 1) {
            return "'" . $cell;
        }

        return $cell;
    }, $fields);
    fputcsv($stream, $safeFields, ',', '"', '');
};

$writeRow($output, [
    'Position',
    'Faculty name',
    'Highest degree',
    'Faculty rank',
    'Academic preparation points',
    'Experience and academic points',
    'Competence points',
    'Extension points',
    'Total points / 500',
    'Hourly rate PHP',
    'Promotion status',
    'Promotion notes',
]);
foreach ($records as $index => $record) {
    $writeRow($output, [
        $index + 1,
        $record['name'],
        $record['legacy_record'] ? 'Not rated - rescore required' : degree_labels()[$record['highest_degree']],
        $record['rank_name'],
        $record['academic_points'],
        $record['experience_points'],
        $record['competence_points'],
        $record['extension_points'],
        $record['score'],
        $record['hourly_rate'] ?? '',
        $record['promotion_blocked'] ? 'Blocked' : ($record['rank'] !== null ? 'Eligible' : 'Below minimum points'),
        $record['promotion_reason'],
    ]);
}

fclose($output);
