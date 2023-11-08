<?php
include 'config.php';

$database = __DIR__ . '/timetracking.sqlite'; // Pfad zur SQLite-Datenbankdatei
$conn = new PDO("sqlite:$database");
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Alle Einträge aus zeiterfassung abrufen und nach startzeit sortieren
$stmt = $conn->prepare('SELECT *, strftime("%W", startzeit) as weekNumber FROM zeiterfassung ORDER BY startzeit DESC');
$stmt->execute();
$records = $stmt->fetchAll();

$currentWeekNumber = date("W");
$currentYear = date("Y");  // Das aktuelle Jahr
$currentMonth = date("m"); // Der aktuelle Monat
$currentMonthName = (new DateTime())->format('F');


// Gesamtanzahl der gearbeiteten Minuten dieser Woche abzüglich Pausen berechnen
$stmt = $conn->prepare('
SELECT SUM(
    ((strftime("%s", endzeit) - strftime("%s", startzeit)) / 60) - COALESCE(pause, 0)
) as totalMinutes
FROM zeiterfassung
WHERE strftime("%Y", startzeit) = "2023"
AND strftime("%W", startzeit) = :weekNumber
');
$stmt->bindParam(':weekNumber', $currentWeekNumber, PDO::PARAM_STR);
$stmt->execute();
$totalMinutesThisWeek = $stmt->fetchColumn();

// Sicherstellen, dass das Ergebnis eine Zahl ist
if ($totalMinutesThisWeek === false) {
    $totalMinutesThisWeek = 0;
}

// Berechnung der Stunden und Minuten
$totalHours = intdiv($totalMinutesThisWeek, 60);
$remainingMinutes = $totalMinutesThisWeek % 60;
// Konvertierung der Gesamtminuten in Stunden mit einer Dezimalstelle
$totalHoursThisWeek = $totalHours + ($remainingMinutes / 60);


// Erster Tag des aktuellen Monats
$currentMonthStart = "{$currentYear}-{$currentMonth}-01";
$currentMonthEnd = date("Y-m-t"); // Letzter Tag des aktuellen Monats

$workingHoursThisMonth = getWorkingHours($currentMonthStart, $currentMonthEnd);



// Heutiges Datum ermitteln
$currentDate = date("Y-m-d");

// Kalenderwoche für das heutige Datum ermitteln
$currentWeekNumber = date("W");
$currentYear = date("Y");

// SQL-Abfrage anpassen, um Daten für die aktuelle Kalenderwoche abzurufen
$stmt = $conn->prepare('
    SELECT
        strftime("%Y-%m-%d", startzeit) AS tag,
        SUM((strftime("%s", endzeit) - strftime("%s", startzeit)) / 60.0) AS arbeitsstunden
    FROM
        zeiterfassung
    WHERE
        strftime("%W", startzeit) = :weekNumber AND
        strftime("%Y", startzeit) = :currentYear
    GROUP BY
        strftime("%Y-%m-%d", startzeit)
');
$stmt->bindParam(':weekNumber', $currentWeekNumber, PDO::PARAM_STR);
$stmt->bindParam(':currentYear', $currentYear, PDO::PARAM_STR);
$stmt->execute();
$workHoursPerDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

$days = [];
$hours = [];

foreach ($workHoursPerDay as $record) {
    // Das Datum in deutsches Datumsformat konvertieren
    $date = date("d.m.Y", strtotime($record['tag']));

    $days[] = $date; // Das konvertierte Datum hinzufügen
    $hours[] = $record['arbeitsstunden'];
}

function istFeiertag($datum)
{
    global $conn;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Feiertage WHERE Datum = ?");
    $stmt->execute([$datum]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result['count'] > 0;
}


function getWorkingDays($startDate, $endDate)
{
    $begin = new DateTime($startDate);
    $end = new DateTime($endDate);
    $end->modify('+1 day');  // Füge einen Tag zum Enddatum hinzu

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

function getWorkingHours($startDate, $endDate)
{
    $workingDays = getWorkingDays($startDate, $endDate);
    $hoursPerDay = 8; // 40 Stunden pro Woche geteilt durch 5 Tage
    return $workingDays * $hoursPerDay;
}



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
        throw new Exception("Fehler beim Abrufen der Feiertage-Daten.");
    }
    
    $data = json_decode($json, true);
    if (!is_array($data)) {        
        throw new Exception("Unerwartetes Format der Feiertage-Daten.");
    }
    
    $feiertage = array_map(function ($feiertag) {
        return $feiertag['datum'] ?? null;
    }, $data);
    
    $feiertage = array_filter($feiertage);
    
    if (count($feiertage) > 0) {
        $placeholders = implode(',', array_fill(0, count($feiertage), '(?)'));
        $stmt = $conn->prepare("INSERT INTO Feiertage (Datum) VALUES $placeholders");
        $stmt->execute($feiertage);
    }
}


// Aufrufen der Funktion zu Jahresbeginn
fetchFeiertageDB($currentYear);

function fetchFeiertageDieseWoche($currentYear, $currentWeekNumber) {
    global $conn;

    // Feiertage dieser Woche aus der Datenbank abrufen
    $stmt = $conn->prepare("
        SELECT Datum
        FROM Feiertage
        WHERE strftime('%Y', Datum) = :jahr
        AND strftime('%W', Datum) = :weekNumber
    ");
    $stmt->bindParam(':jahr', $currentYear);
    $stmt->bindParam(':weekNumber', $currentWeekNumber);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// Feiertage dieser Woche holen
$feiertageDieseWoche = fetchFeiertageDieseWoche($currentYear, $currentWeekNumber);


// Erster Tag des aktuellen Monats
$firstDayOfTheMonth = "{$currentYear}-{$currentMonth}-01";

// Letzter Tag des aktuellen Monats
$lastDayOfTheMonth = date("Y-m-t", strtotime($currentMonthStart));
$workingDaysThisMonth = getWorkingDays($firstDayOfTheMonth, $lastDayOfTheMonth);






// SQL-Abfrage für die gesamten Arbeitsstunden dieses Monats
$stmt = $conn->prepare('
    SELECT
        SUM((strftime("%s", endzeit) - strftime("%s", startzeit)) / 60) as totalMinutes
    FROM
        zeiterfassung
    WHERE
        strftime("%Y", startzeit) = :currentYear AND
        strftime("%m", startzeit) = :currentMonth
');
$stmt->bindParam(':currentYear', $currentYear);
$stmt->bindParam(':currentMonth', $currentMonth);
$stmt->execute();
$totalMinutesThisMonthFromRecords = $stmt->fetchColumn();
$totalHoursThisMonthFromRecords = floor($totalMinutesThisMonthFromRecords / 60);


//Ueberstunden:
$overHoursThisMonth = $totalHoursThisMonthFromRecords - $workingHoursThisMonth;

// Gesamtarbeitsstunden des Jahres
$stmt = $conn->prepare('
    SELECT
        SUM((strftime("%s", endzeit) - strftime("%s", startzeit)) / 60) as totalMinutes
    FROM
        zeiterfassung
    WHERE
        strftime("%Y", startzeit) = :currentYear
');
$stmt->bindParam(':currentYear', $currentYear);
$stmt->execute();
$totalMinutesThisYearFromRecords = $stmt->fetchColumn();
$totalHoursThisYearFromRecords = floor($totalMinutesThisYearFromRecords / 60);

// 2. Sollarbeitsstunden des Jahres
$firstDayOfTheYear = "{$currentYear}-01-01";
$lastDayOfTheYear = "{$currentYear}-12-31";
$workingHoursThisYear = getWorkingHours($firstDayOfTheYear, $lastDayOfTheYear);

// Überstunden für das Jahr berechnen
$overHoursThisYear = $totalHoursThisYearFromRecords - $workingHoursThisYear;

// Bestimmen Sie den ersten und den letzten Tag der aktuellen Arbeitswoche (Montag bis Freitag)
$firstDayOfWeek = date('Y-m-d', strtotime('Monday this week'));
$lastWorkingDayOfWeek = date('Y-m-d', strtotime('Friday this week'));

$expectedHoursThisWeek = 40;

// Überprüfen Sie jeden Tag der Arbeitswoche, ob er ein Feiertag ist
$currentDate = $firstDayOfWeek;
while (strtotime($currentDate) <= strtotime($lastWorkingDayOfWeek)) {
    if (istFeiertag($currentDate)) {
        $expectedHoursThisWeek -= 8;
    }
    $currentDate = date('Y-m-d', strtotime("+1 day", strtotime($currentDate)));
}

// Überstunden für diese Woche berechnen
$overHoursThisWeek = round($totalHoursThisWeek - $expectedHoursThisWeek, 1);

function getFeiertageForYear($jahr) {
    global $conn;

    $stmt = $conn->prepare("SELECT Datum FROM Feiertage WHERE strftime('%Y', Datum) = ?");
    $stmt->execute([$jahr]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$currentYear = date("Y");
$feiertageThisYear = getFeiertageForYear($currentYear);


function getGermanDayName($date) {
    $days = ["Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag"];
    return $days[date("w", strtotime($date))];
}

// Gamification: Zählen der unterschiedlichen Wochen, in denen gearbeitet wurde
$stmt = $conn->prepare("SELECT COUNT(DISTINCT strftime('%W', startzeit)) as weeksCount FROM zeiterfassung");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$isFirstWeek = $result['weeksCount'] == 1;

$events = [];

foreach ($records as $record) {
    $startDateTime = new DateTime($record['startzeit']);
    $endDateTime = new DateTime($record['endzeit']);
    
    $events[] = [
        'id' => $record['id'],
        'title' => 'Arbeit',
        'start' => $startDateTime->format("Y-m-d\TH:i:s"),
        'end' => $endDateTime->format("Y-m-d\TH:i:s"),
        'category' => 'time',
        'dueDateClass' => '',
        'isAllDay' => false
    ];
}

foreach ($feiertageThisYear as $feiertag) {
    $feiertagDateTime = new DateTime($feiertag['Datum']);
    
    $events[] = [
        'id' => 'feiertag_' . $feiertag['Datum'],
        'title' => 'Feiertag',
        'start' => $feiertagDateTime->format("Y-m-d"),
        'end' => $feiertagDateTime->format("Y-m-d"),
        'category' => 'allday',
        'isAllDay' => true
    ];
}








