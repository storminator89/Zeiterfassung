<?php
include 'config.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Establish a PDO connection
try {
    $conn = new PDO("sqlite:$database");
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
        SELECT *, strftime("%W", startzeit) as weekNumber 
        FROM zeiterfassung 
        WHERE user_id = :user_id
        ORDER BY startzeit DESC
    ');
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
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

// Calculate total worked minutes this week excluding breaks
try {
    $stmt = $conn->prepare('
        SELECT SUM(
            ((strftime("%s", endzeit) - strftime("%s", startzeit)) / 60) - COALESCE(pause, 0)
        ) as totalMinutes
        FROM zeiterfassung
        WHERE user_id = :user_id
        AND strftime("%Y", startzeit) = :currentYear
        AND strftime("%W", startzeit) = :weekNumber
        AND beschreibung != "Feiertag"
    ');
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':currentYear', $currentYear, PDO::PARAM_STR);
    $stmt->bindParam(':weekNumber', $currentWeekNumber, PDO::PARAM_STR);
    $stmt->execute();
    $totalMinutesThisWeek = $stmt->fetchColumn();
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// Ensuring the result is a number
if ($totalMinutesThisWeek === false) {
    $totalMinutesThisWeek = 0;
}

$totalMinutesThisWeek = $totalMinutesThisWeek ?? 0;
$totalHours = intdiv($totalMinutesThisWeek, 60);
$remainingMinutes = $totalMinutesThisWeek % 60;
// Converting total minutes into hours with one decimal place
$totalHoursThisWeek = $totalHours + ($remainingMinutes / 60);

// First day of the current month
$currentMonthStart = "{$currentYear}-{$currentMonth}-01";
$currentMonthEnd = date("Y-m-t"); // Last day of the current month

$workingHoursThisMonth = getWorkingHours($currentMonthStart, $currentMonthEnd, $userRegularWorkingHours);

// Getting today's date
$currentDate = date("Y-m-d");

// Adjusting SQL query to fetch data for the current calendar week
$stmt = $conn->prepare('
    SELECT
        strftime("%Y-%m-%d", startzeit) AS tag,
        SUM((strftime("%s", endzeit) - strftime("%s", startzeit)) / 60.0) AS arbeitsstunden
    FROM
        zeiterfassung
    WHERE
        user_id = :user_id
        AND strftime("%W", startzeit) = :weekNumber
        AND strftime("%Y", startzeit) = :currentYear
    GROUP BY
        strftime("%Y-%m-%d", startzeit)
');
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':weekNumber', $currentWeekNumber, PDO::PARAM_STR);
$stmt->bindParam(':currentYear', $currentYear, PDO::PARAM_STR);
$stmt->execute();
$workHoursPerDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

$days = [];
$hours = [];

// Looping through the work hours per day
foreach ($workHoursPerDay as $record) {
    $date = date("d.m.Y", strtotime($record['tag']));
    $roundedHours = round($record['arbeitsstunden'] / 60, 2);

    $days[] = $date;
    $hours[] = $roundedHours;
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
    $begin = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');  // Adding one day to the end date

    $interval = DateInterval::createFromDateString('1 day');
    $dateRange = new DatePeriod($begin, $interval, $end);

    $workingDays = 0;
    foreach ($dateRange as $date) {
        if ($date->format('N') < 6 && !istFeiertag($date->format('Y-m-d'))) {
            $workingDays++;
        }
    }

    return $workingDays;
}

// Function to get working hours between two dates
function getWorkingHours($startDate, $endDate, $hoursPerDay)
{
    $workingDays = getWorkingDays($startDate, $endDate);
    return $workingDays * $hoursPerDay;
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
    $json = file_get_contents($url);
    if ($json === false) {
        throw new Exception("Error fetching holiday data.");
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
        $stmt = $conn->prepare("INSERT INTO Feiertage (Datum, Name) VALUES $placeholders");

        $flatParams = [];
        foreach ($feiertage as $feiertag) {
            $flatParams[] = $feiertag['datum'];
            $flatParams[] = $feiertag['name'];
        }

        $stmt->execute($flatParams);
    }
}

// Calling the function at the beginning of the year
fetchFeiertageDB($currentYear);

// Function to fetch holidays for this week
function fetchFeiertageDieseWoche($currentYear, $currentWeekNumber)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT DATE(datum) AS datum, name
        FROM Feiertage
        WHERE strftime('%Y', datum) = :jahr
        AND strftime('%W', datum) = :weekNumber
    ");
    $stmt->bindParam(':jahr', $currentYear);
    $stmt->bindParam(':weekNumber', $currentWeekNumber);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetching holidays of this week
$feiertageDieseWoche = fetchFeiertageDieseWoche($currentYear, $currentWeekNumber);

// First day of the current month
$firstDayOfTheMonth = "{$currentYear}-{$currentMonth}-01";

// Last day of the current month
$lastDayOfTheMonth = date("Y-m-t", strtotime($currentMonthStart));
$workingDaysThisMonth = getWorkingDays($firstDayOfTheMonth, $lastDayOfTheMonth);

$totalOverHours = 0; // Gesamtüberstunden
$workHoursByDate = []; // Speichert Arbeitsstunden pro Datum

foreach ($records as $record) {
    $datum = (new DateTime($record['startzeit']))->format('Y-m-d');
    $startzeit = new DateTime($record['startzeit']);
    $endzeit = new DateTime($record['endzeit']);
    $gesamtdauer = $endzeit->diff($startzeit);

    $gesamtstunden = $gesamtdauer->h + ($gesamtdauer->i / 60);
    $pause = is_numeric($record['pause']) ? ($record['pause'] / 60) : 0;
    $arbeitstunden = $gesamtstunden - $pause;

    // Speichern der Arbeitsstunden pro Datum
    if (!isset($workHoursByDate[$datum])) {
        $workHoursByDate[$datum] = 0;
    }
    $workHoursByDate[$datum] += $arbeitstunden;
}

// Initialisierung der Gesamtüberstunden
$totalOverHours = 0;

// Durchlaufen der gesammelten Arbeitsstunden pro Tag
foreach ($workHoursByDate as $datum => $hours) {
    $wochentag = date('N', strtotime($datum));
    if ($wochentag >= 1 && $wochentag <= 5) { // Montag bis Freitag
        $totalOverHours += $hours - $userRegularWorkingHours;
    } else {
        $totalOverHours += $hours; // Wochenendarbeit als Überstunden
    }
}

// Hinzufügen der vorherigen Überstunden
$totalOverHours += $previousOvertime;

// Berechnung der Gesamtüberstunden in Stunden und Minuten
if ($totalOverHours < 0) {
    // Negative Gesamtüberstunden, verwenden Absolutwerte für Stunden und Minuten
    $totalOverHoursHours = floor(abs($totalOverHours)); // Absolutwert für Stunden, abgerundet auf die nächste niedrigere ganze Zahl
    $totalOverHoursMinutes = round((abs($totalOverHours) - $totalOverHoursHours) * 60); // Differenz zu ganzen Stunden, umgewandelt in Minuten
    $totalOverHoursFormatted = sprintf("-%02d:%02d", $totalOverHoursHours, $totalOverHoursMinutes);
} else {
    // Positive Gesamtüberstunden
    $totalOverHoursHours = floor($totalOverHours); // Ganze Stunden
    $totalOverHoursMinutes = round(($totalOverHours - $totalOverHoursHours) * 60); // Umwandlung der restlichen Dezimalstunden in Minuten
    $totalOverHoursFormatted = sprintf("%02d:%02d", $totalOverHoursHours, $totalOverHoursMinutes);
}

// SQL query for total working hours this month
$stmt = $conn->prepare('
    SELECT
        SUM((strftime("%s", endzeit) - strftime("%s", startzeit)) / 60) as totalMinutes
    FROM
        zeiterfassung
    WHERE
        user_id = :user_id
        AND strftime("%Y", startzeit) = :currentYear
        AND strftime("%m", startzeit) = :currentMonth
');
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':currentYear', $currentYear, PDO::PARAM_STR);
$stmt->bindParam(':currentMonth', $currentMonth, PDO::PARAM_STR);
$stmt->execute();
$totalMinutesThisMonthFromRecords = $stmt->fetchColumn();
$totalHoursThisMonthFromRecords = floor($totalMinutesThisMonthFromRecords / 60);

// Over hours:
$overHoursThisMonth = $totalHoursThisMonthFromRecords - $workingHoursThisMonth;

// Total working hours of the year
$stmt = $conn->prepare('
    SELECT
        SUM((strftime("%s", endzeit) - strftime("%s", startzeit)) / 60) as totalMinutes
    FROM
        zeiterfassung
    WHERE
        user_id = :user_id
        AND strftime("%Y", startzeit) = :currentYear
');
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindParam(':currentYear', $currentYear, PDO::PARAM_STR);
$stmt->execute();
$totalMinutesThisYearFromRecords = $stmt->fetchColumn();
$totalHoursThisYearFromRecords = floor($totalMinutesThisYearFromRecords / 60);

// 2. Expected working hours of the year
$firstDayOfTheYear = "{$currentYear}-01-01";
$lastDayOfTheYear = "{$currentYear}-12-31";
$workingHoursThisYear = getWorkingHours($firstDayOfTheYear, $lastDayOfTheYear, $userRegularWorkingHours);

// Calculating over hours for the year
$overHoursThisYear = $totalHoursThisYearFromRecords - $workingHoursThisYear;

// Determining the first and last working day of the current week (Monday to Friday)
$firstDayOfWeek = date('Y-m-d', strtotime('Monday this week'));
$lastWorkingDayOfWeek = date('Y-m-d', strtotime('Friday this week'));

$expectedHoursThisWeek = 5 * $userRegularWorkingHours; // 5 Arbeitstage pro Woche

// Checking each day of the working week if it's a holiday
$currentDate = $firstDayOfWeek;
while (strtotime($currentDate) <= strtotime($lastWorkingDayOfWeek)) {
    if (istFeiertag($currentDate)) {
        $expectedHoursThisWeek -= $userRegularWorkingHours;
    }
    $currentDate = date('Y-m-d', strtotime("+1 day", strtotime($currentDate)));
}

// Calculating over hours for this week
$overHoursThisWeek = round($totalHoursThisWeek - $expectedHoursThisWeek, 1);

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

// Gamification: Counting the different weeks worked
$stmt = $conn->prepare("SELECT COUNT(DISTINCT strftime('%W', startzeit)) as weeksCount FROM zeiterfassung WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$isFirstWeek = $result['weeksCount'] == 1;

$events = [];

// Looping through the records to create events
foreach ($records as $record) {
    $startDateTime = new DateTime($record['startzeit']);
    $endDateTime = new DateTime($record['endzeit']);

    // Überprüfen, ob das 'beschreibung'-Feld leer ist und entsprechend den Titel setzen
    $title = empty($record['beschreibung']) ? 'Arbeit' : $record['beschreibung'];

    $events[] = [
        'id' => $record['id'],
        'title' => $title,
        'start' => $startDateTime->format("Y-m-d\TH:i:s"),
        'end' => $endDateTime->format("Y-m-d\TH:i:s"),
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
?>
