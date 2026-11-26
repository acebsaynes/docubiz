
<?php

/*
|--------------------------------------------------------------------------
| DocuBiz Automatic Email Reminders
|--------------------------------------------------------------------------
| Run this script using PHP CLI / Windows Task Scheduler.
| It checks projects and calendar events for upcoming dates.
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

$today = new DateTimeImmutable('today', new DateTimeZone('Asia/Manila'));

$reminderDays = [7, 3, 1, 0];

$sentCount = 0;
$skippedCount = 0;
$errorCount = 0;


/*
|--------------------------------------------------------------------------
| Process One Reminder
|--------------------------------------------------------------------------
*/

function processDocuBizReminder(
    mysqli $conn,
    array $item,
    string $sourceType,
    DateTimeImmutable $today,
    array $reminderDays,
    int &$sentCount,
    int &$skippedCount,
    int &$errorCount
) {
    $sourceId = (int) ($item['id'] ?? 0);

    $recipientEmail = trim($item['recipient_email'] ?? '');
    $recipientName = trim($item['recipient_name'] ?? 'DocuBiz User');

    $eventName = trim($item['event_name'] ?? 'Upcoming Event');
    $eventTime = trim($item['start_time'] ?? '');
    $location = trim($item['location'] ?? '');

    $rawDate = substr(trim($item['event_date'] ?? ''), 0, 10);

    if (
        $sourceId < 1 ||
        !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) ||
        $rawDate === ''
    ) {
        $skippedCount++;
        echo "Skipped invalid {$sourceType} record: {$sourceId}\n";
        return;
    }

    $eventDate = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $rawDate,
        new DateTimeZone('Asia/Manila')
    );

    if (!$eventDate || $eventDate->format('Y-m-d') !== $rawDate) {
        $skippedCount++;
        echo "Skipped invalid date for {$sourceType} #{$sourceId}\n";
        return;
    }

    $daysUntil = (int) $today->diff($eventDate)->format('%r%a');

    if (!in_array($daysUntil, $reminderDays, true)) {
        return;
    }

    $eventDateString = $eventDate->format('Y-m-d');

    /*
     * Check whether this reminder was already sent.
     */

    $checkStmt = $conn->prepare(
        "SELECT id
         FROM email_reminder_logs
         WHERE source_type = ?
           AND source_id = ?
           AND reminder_days = ?
           AND event_date = ?
         LIMIT 1"
    );

    if (!$checkStmt) {
        throw new RuntimeException($conn->error);
    }

    $checkStmt->bind_param(
        "siis",
        $sourceType,
        $sourceId,
        $daysUntil,
        $eventDateString
    );

    $checkStmt->execute();

    $alreadySent = $checkStmt->get_result()->num_rows > 0;

    $checkStmt->close();

    if ($alreadySent) {
        $skippedCount++;
        echo "Already sent: {$sourceType} #{$sourceId}, {$daysUntil} day(s)\n";
        return;
    }

    /*
     * Send the reminder email.
     */

    $sent = sendReminderEmail(
        $recipientEmail,
        $recipientName,
        $eventName,
        $eventDate->format('F j, Y'),
        $eventTime,
        $location,
        $daysUntil,
        $sourceType
    );

    if (!$sent) {
        $errorCount++;
        echo "Email failed: {$sourceType} #{$sourceId}\n";
        return;
    }

    /*
     * Record the successful email.
     */

    $insertStmt = $conn->prepare(
        "INSERT INTO email_reminder_logs
         (source_type, source_id, reminder_days, event_date)
         VALUES (?, ?, ?, ?)"
    );

    if (!$insertStmt) {
        throw new RuntimeException($conn->error);
    }

    $insertStmt->bind_param(
        "siis",
        $sourceType,
        $sourceId,
        $daysUntil,
        $eventDateString
    );

    $insertStmt->execute();
    $insertStmt->close();

    $sentCount++;

    echo "Email sent: {$sourceType} #{$sourceId} to {$recipientEmail}\n";
}


/*
|--------------------------------------------------------------------------
| Get Upcoming Projects
|--------------------------------------------------------------------------
| Project reminders use start_date.
| The recipient is the user who created the project (projects.user_id).
|--------------------------------------------------------------------------
*/

try {

    $endDate = $today->modify('+7 days')->format('Y-m-d');
    $todayString = $today->format('Y-m-d');

    $projectStmt = $conn->prepare(
        "SELECT
            p.id,
            p.project_name AS event_name,
            p.start_date AS event_date,
            p.event_time AS start_time,
            p.location,
            u.email AS recipient_email,
            u.full_name AS recipient_name
         FROM projects p
         INNER JOIN users u ON u.id = p.user_id
         WHERE p.start_date IS NOT NULL
           AND p.start_date BETWEEN ? AND ?
           AND LOWER(COALESCE(p.status, '')) NOT IN ('completed', 'cancelled')"
    );

    if (!$projectStmt) {
        throw new RuntimeException($conn->error);
    }

    $projectStmt->bind_param(
        "ss",
        $todayString,
        $endDate
    );

    $projectStmt->execute();

    $projectResult = $projectStmt->get_result();

    while ($project = $projectResult->fetch_assoc()) {

        try {
            processDocuBizReminder(
                $conn,
                $project,
                'project',
                $today,
                $reminderDays,
                $sentCount,
                $skippedCount,
                $errorCount
            );

        } catch (Throwable $e) {
            $errorCount++;
            error_log("Project reminder error: " . $e->getMessage());
            echo "Project reminder error for #{$project['id']}\n";
        }
    }

    $projectStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Get Upcoming Calendar Events
    |--------------------------------------------------------------------------
    | The recipient is the event creator (events.created_by).
    |--------------------------------------------------------------------------
    */

    $eventStmt = $conn->prepare(
        "SELECT
            e.id,
            e.event_name,
            e.event_date,
            e.start_time,
            e.location,
            u.email AS recipient_email,
            u.full_name AS recipient_name
         FROM events e
         INNER JOIN users u ON u.id = e.created_by
         WHERE e.event_date IS NOT NULL
           AND e.event_date BETWEEN ? AND ?"
    );

    if (!$eventStmt) {
        throw new RuntimeException($conn->error);
    }

    $eventStmt->bind_param(
        "ss",
        $todayString,
        $endDate
    );

    $eventStmt->execute();

    $eventResult = $eventStmt->get_result();

    while ($event = $eventResult->fetch_assoc()) {

        try {
            processDocuBizReminder(
                $conn,
                $event,
                'event',
                $today,
                $reminderDays,
                $sentCount,
                $skippedCount,
                $errorCount
            );

        } catch (Throwable $e) {
            $errorCount++;
            error_log("Event reminder error: " . $e->getMessage());
            echo "Event reminder error for #{$event['id']}\n";
        }
    }

    $eventStmt->close();


    /*
    |--------------------------------------------------------------------------
    | Summary
    |--------------------------------------------------------------------------
    */

    echo "\n";
    echo "====================================\n";
    echo "       DOCUBIZ REMINDER SUMMARY\n";
    echo "====================================\n";
    echo "Date: " . $today->format('F j, Y') . "\n";
    echo "Emails sent: {$sentCount}\n";
    echo "Skipped: {$skippedCount}\n";
    echo "Errors: {$errorCount}\n";
    echo "====================================\n";

} catch (Throwable $e) {

    error_log("DocuBiz Reminder Script Error: " . $e->getMessage());

    echo "Reminder script failed: " . $e->getMessage() . "\n";

    exit(1);

}

$conn->close();

