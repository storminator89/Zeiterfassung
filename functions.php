<?php
require_once __DIR__ . '/security.php';

// Set default language if not set
$lang = $_SESSION['lang'] ?? 'de';
$langFile = __DIR__ . "/languages/$lang.php";

// Load language file first
if (file_exists($langFile)) {
    require_once $langFile;
} else {
    require_once __DIR__ . '/languages/de.php'; // Fallback to German
}

// Now include other dependencies
require_once 'config.php';
require_once __DIR__ . '/lib/home_projection.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Establish a PDO connection
try {
    // Keep the configured connection with audit context.
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Handle connection error
    die("Verbindungsfehler: " . $e->getMessage());
}

// Fetch user's regular working hours and previous overtime
try {
    $stmt = $conn->prepare('SELECT regelarbeitszeit, ueberstunden FROM users WHERE id = :user_id');
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    $userRegularWorkingHours = $userData['regelarbeitszeit'] ?? 8; // Default to 8 hours if not set
    $previousOvertime = $userData['ueberstunden'] ?? 0.0; // Default to 0 if not set
} catch (PDOException $e) {
    // Handle query error
    die("Datenbankfehler: " . $e->getMessage());
}

// Fetch all entries from 'zeiterfassung' for the current user, adding week number
try {
    $stmt = $conn->prepare('
        SELECT *
        FROM zeiterfassung 
        WHERE user_id = :user_id
        ORDER BY startzeit DESC
    ');
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($records as &$entry) {
        $calculation=report_record_calculation($entry);
        $entry['weekNumber']=$calculation['week']===null ? '-' : substr($calculation['week'],5);
    } unset($entry);
} catch (PDOException $e) {
    // Handle query error
    die("Datenbankfehler: " . $e->getMessage());
}

$months = [
    "01" => MONTH_JANUARY,
    "02" => MONTH_FEBRUARY,
    "03" => MONTH_MARCH,
    "04" => MONTH_APRIL,
    "05" => MONTH_MAY,
    "06" => MONTH_JUNE,
    "07" => MONTH_JULY,
    "08" => MONTH_AUGUST,
    "09" => MONTH_SEPTEMBER,
    "10" => MONTH_OCTOBER,
    "11" => MONTH_NOVEMBER,
    "12" => MONTH_DECEMBER
];

// Getting current date details
$currentWeekNumber = date("W");
$currentYear = date("Y");
$currentMonth = date("m");
$currentMonthName = $months[$currentMonth];

// Load the holiday calendar before calculating any target in this response.
fetchFeiertageDB($currentYear);
[$firstDayOfWeek,$lastDayOfWeek]=report_week_bounds(date('Y-m-d'));
$reportInvalidRecords=[];
$reportAbsenceConflicts=[];
$reportDays=report_days($records,$reportInvalidRecords,$reportAbsenceConflicts);
$reportTotalUnresolved=$reportAbsenceConflicts!==[];
$reportWeekUnresolved=report_conflicts_in_period($reportAbsenceConflicts,$firstDayOfWeek,$lastDayOfWeek);
$totalMinutesThisWeek=report_period_minutes($reportDays,$firstDayOfWeek,$lastDayOfWeek);
$totalHoursThisWeek=$totalMinutesThisWeek/60;
$currentMonthStart="{$currentYear}-{$currentMonth}-01";
$currentMonthEnd=date('Y-m-t');
$reportMonthUnresolved=report_conflicts_in_period($reportAbsenceConflicts,$currentMonthStart,$currentMonthEnd);
$reportYearUnresolved=report_conflicts_in_period($reportAbsenceConflicts,"$currentYear-01-01","$currentYear-12-31");
$workingHoursThisMonth=getWorkingHours($currentMonthStart,$currentMonthEnd,$userRegularWorkingHours);
$currentDate=date('Y-m-d');
$workHoursPerDay=[];$days=[];$hours=[];
foreach ($reportDays as $day=>$minutes) {
    if ($day<$firstDayOfWeek || $day>$lastDayOfWeek) { continue; }
    $workHoursPerDay[]=['tag'=>$day,'arbeitsstunden'=>$minutes];
    $days[]=date('d.m.Y',strtotime($day));$hours[]=round($minutes/60,2);
}

// Function to check if a date is a holiday
function istFeiertag($datum)
{
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Feiertage WHERE Datum = ?");
    $stmt->execute([$datum]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] > 0;
}

// Function to get working days between two dates
function getWorkingDays($startDate, $endDate)
{
    global $conn, $user_id;
    return home_working_days($conn, (int)$user_id, $startDate, $endDate);
}

// Function to get working hours between two dates
function getWorkingHours($startDate, $endDate, $hoursPerDay)
{
    global $conn, $user_id;
    return planned_hours($conn,(int)$user_id,$startDate,$endDate);
}

// Function to fetch holidays from the database for a given year
function fetchFeiertageDB($jahr)
{
    global $conn;
    $stmt = $conn->prepare("SELECT EXISTS(SELECT 1 FROM Feiertage WHERE strftime('%Y', Datum) = ?)");
    $stmt->execute([$jahr]);
    $exists = $stmt->fetchColumn();
    if ($exists) {
        return;
    }

    $url = "https://feiertage-api.de/api/?jahr=" . urlencode($jahr) . "&nur_land=BW";
    $context = stream_context_create(['http' => ['timeout' => 5]]);
    $json = @file_get_contents($url, false, $context);
    if ($json === false) {
        error_log("Holiday service unavailable");
        return;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        error_log("Unexpected format of holiday data for year $jahr");
        return;
    }

    $feiertage = array_filter($data, function ($feiertag) {
        return empty($feiertag['hinweis']);
    });

    $feiertage = array_map(function ($feiertag, $name) {
        return [
            'datum' => $feiertag['datum'] ?? null,
            'name' => $name
        ];
    }, $feiertage, array_keys($feiertage));

    $feiertage = array_filter($feiertage, function ($feiertag) {
        return $feiertag['datum'] !== null;
    });

    if (count($feiertage) > 0) {
        $placeholders = implode(',', array_fill(0, count($feiertage), '(?, ?)'));
        $stmt = $conn->prepare("INSERT OR IGNORE INTO Feiertage (Datum, Name) VALUES $placeholders");

        $flatParams = [];
        foreach ($feiertage as $feiertag) {
            $flatParams[] = $feiertag['datum'];
            $flatParams[] = $feiertag['name'];
        }

        $stmt->execute($flatParams);
    }
}



// Function to fetch holidays for this week
function fetchFeiertageDieseWoche($currentYear, $currentWeekNumber)
{
    global $conn;

    return home_week_holidays($conn, $currentYear, $currentWeekNumber);
}

// Fetching holidays of this week
$feiertageDieseWoche = fetchFeiertageDieseWoche(date('o'), $currentWeekNumber);

// First day of the current month
$firstDayOfTheMonth = "{$currentYear}-{$currentMonth}-01";

// Last day of the current month
$lastDayOfTheMonth = date("Y-m-t", strtotime($currentMonthStart));
$workingDaysThisMonth = getWorkingDays($firstDayOfTheMonth, $lastDayOfTheMonth);

$workHoursByDate=[];
$recordedAsOfDay=(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->format('Y-m-d');
$recordedDayBalances=home_recorded_day_balances($conn,(int)$user_id,$reportDays,(float)$previousOvertime,null,$recordedAsOfDay);
$totalOverHours=$recordedDayBalances['total_hours'];
foreach ($reportDays as $day=>$minutes) {
    $workHoursByDate[$day]=$minutes/60;
}
$totalOverHoursFormatted=$reportTotalUnresolved ? 'Klärung offen' : report_format_hours($totalOverHours);
$totalMinutesThisMonthFromRecords=report_period_minutes($reportDays,$currentMonthStart,$currentMonthEnd);
$totalHoursThisMonthFromRecords=$totalMinutesThisMonthFromRecords/60;
$overHoursThisMonth=$totalHoursThisMonthFromRecords-$workingHoursThisMonth;
$totalMinutesThisYearFromRecords=report_period_minutes($reportDays,"$currentYear-01-01","$currentYear-12-31");
$totalHoursThisYearFromRecords=$totalMinutesThisYearFromRecords/60;

// 2. Expected working hours of the year
$firstDayOfTheYear = "{$currentYear}-01-01";
$lastDayOfTheYear = "{$currentYear}-12-31";
$workingHoursThisYear = getWorkingHours($firstDayOfTheYear, $lastDayOfTheYear, $userRegularWorkingHours);

// Calculating over hours for the year
$overHoursThisYear = $totalHoursThisYearFromRecords - $workingHoursThisYear;

// Determining the first and last working day of the current week (Monday to Friday)
$firstDayOfWeek = date('Y-m-d', strtotime('Monday this week'));
$lastWorkingDayOfWeek = date('Y-m-d', strtotime('Friday this week'));

$expectedHoursThisWeek = planned_hours($conn,(int)$user_id,$firstDayOfWeek,date('Y-m-d',strtotime('Sunday this week')));

// Calculating over hours for this week
$overHoursThisWeek = $totalHoursThisWeek - $expectedHoursThisWeek;

// Function to get holidays for a year
function getFeiertageForYear($jahr)
{
    global $conn;

    $stmt = $conn->prepare("SELECT DATE(datum) AS datum, name FROM Feiertage WHERE strftime('%Y', datum) = ?");
    $stmt->execute([$jahr]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentYear = date("Y");
$feiertageThisYear = getFeiertageForYear($currentYear);

// Function to get German day name
function getGermanDayName($date)
{
    $days = ["Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag"];
    return $days[date("w", strtotime($date))];
}

function getEntryById($conn, $id)
{
    $stmt = $conn->prepare("SELECT * FROM zeiterfassung WHERE id = :id AND user_id = :user_id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode(["success" => true, "data" => $result]);
    } else {
        echo json_encode(["success" => false, "message" => "Kein Eintrag gefunden mit ID: $id"]);
    }
}

$weeks=[];
foreach($records as $record) {
    $calculation=report_record_calculation($record);
    if ($calculation['status']==='completed') { $weeks[$calculation['week']]=true; }
}
$weeksCount=count($weeks);$isFirstWeek=$weeksCount===1;

$events = [];

// Looping through the records to create events
foreach ($records as $record) {
    $calculation=report_record_calculation($record);
    // No invented calendar date for corrupt source times; the header warns that
    // reports/calendar may be incomplete and retains the correction entrypoint.
    if ($calculation['status']==='invalid') { continue; }
    $startDateTime = booking_local_date($calculation['start']);
    $endDateTime = $calculation['end']===null ? null : booking_local_date($calculation['end']);

    // Überprüfen, ob das 'beschreibung'-Feld leer ist und entsprechend den Titel setzen
    $title = empty($record['beschreibung']) ? 'Arbeit' : $record['beschreibung'];

    $events[] = [
        'id' => $record['id'],
        'title' => $title,
        'start' => $startDateTime->format("Y-m-d\TH:i:s"),
        'end' => $endDateTime?->format("Y-m-d\TH:i:s"),
        'category' => 'time',
        'dueDateClass' => '',
        'isAllDay' => false,
        'standort' => $record['standort']
    ];
}

// Looping through the holidays to create events
foreach ($feiertageThisYear as $feiertag) {
    $feiertagDateTime = new DateTime($feiertag['datum']); // Corrected key to 'datum'

    $events[] = [
        'id' => 'feiertag_' . $feiertag['datum'], // Corrected key to 'datum'
        'title' => 'Feiertag - ' . $feiertag['name'], // Including the name of the holiday
        'start' => $feiertagDateTime->format("Y-m-d"),
        'end' => $feiertagDateTime->format("Y-m-d"),
        'category' => 'allday',
        'isAllDay' => true
    ];
}


function getPauseDuration($totalHours)
{
    global $conn;
    $stmt = $conn->prepare("SELECT minimum_pause FROM pause_settings WHERE hours_threshold <= ? ORDER BY hours_threshold DESC LIMIT 1");
    $stmt->execute([$totalHours]);
    return (int)$stmt->fetchColumn();
}

// Example function to fetch time records with Standort and Beschreibung
function getTimeRecords($conn, $user_id) {
    try {
        $stmt = $conn->prepare('SELECT id, startzeit, endzeit, pause, standort, beschreibung FROM zeiterfassung WHERE user_id = :user_id ORDER BY startzeit DESC');
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Datenbankfehler: " . $e->getMessage());
    }
}

// Ensure functions handling updates include Standort and Beschreibung if necessary
function updateTimeRecord($conn, $id, $column, $data) {
    throw new AttendanceError('Bitte Korrekturantrag verwenden.');
}
