<?php
include 'config.php';

// Fetch all records
$stmt = $conn->prepare('SELECT *, DATEPART(ISO_WEEK, startzeit) as weekNumber FROM zeiterfassung ORDER BY startzeit DESC');
$stmt->execute();
$records = $stmt->fetchAll();

$currentWeekNumber = date("W");

// Get total hours worked this week minus breaks
$stmt = $conn->prepare('
SELECT SUM(DATEDIFF(MINUTE, startzeit, endzeit) - pause) as totalMinutes
FROM zeiterfassung
WHERE YEAR(startzeit) = 2023
AND DATEPART(ISO_WEEK, startzeit) = :weekNumber
');
$stmt->bindParam(':weekNumber', $currentWeekNumber);
$stmt->execute();
$totalMinutesThisWeek = $stmt->fetchColumn();
$totalHours = floor($totalMinutesThisWeek / 60);
$remainingMinutes = $totalMinutesThisWeek % 60;
$totalHoursThisWeek = $totalHours + round($remainingMinutes / 60, 1);



// Heutiges Datum ermitteln
$currentDate = date("Y-m-d");

// Kalenderwoche für das heutige Datum ermitteln
$currentWeekNumber = date("W", strtotime($currentDate));

$currentYear = date("Y");


// SQL-Abfrage anpassen, um Daten für die aktuelle Kalenderwoche abzurufen
$stmt = $conn->prepare('
    SELECT
        CONVERT(DATE, startzeit) AS tag,
        SUM(DATEDIFF(MINUTE, startzeit, endzeit)) / 60.0 AS arbeitsstunden
    FROM
        zeiterfassung
    WHERE
        DATEPART(ISO_WEEK, startzeit) = :weekNumber AND
        YEAR(startzeit) = :currentYear
    GROUP BY
        CONVERT(DATE, startzeit)
');
$stmt->bindParam(':weekNumber', $currentWeekNumber);
$stmt->bindParam(':currentYear', $currentYear);
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

$currentYear = date("Y");  // Das aktuelle Jahr
$currentMonth = date("m"); // Der aktuelle Monat
$currentMonthName = (new DateTime())->format('F');

function fetchFeiertageDB($jahr)
{
    global $conn;

    // Überprüfen, ob das aktuelle Jahr bereits in der Datenbank ist
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM Feiertage WHERE YEAR(Datum) = ?");
    $stmt->execute([$jahr]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        // Das Jahr wurde bereits in der Datenbank gespeichert, kein erneuter Abruf notwendig
        return;
    }

    $url = "https://feiertage-api.de/api/?jahr=$jahr&nur_land=BW";
    $json = file_get_contents($url);
    $data = json_decode($json, true);

    $feiertage = [];
    foreach ($data as $feiertag) {
        $feiertage[] = $feiertag['datum'];
    }

    // Daten in die Datenbank einfügen
    $stmt = $conn->prepare("INSERT INTO Feiertage (Datum) VALUES (?)");
    foreach ($feiertage as $feiertag) {
        $stmt->execute([$feiertag]);
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
        WHERE YEAR(Datum) = :jahr
        AND DATEPART(ISO_WEEK, Datum) = :weekNumber
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
$lastDayOfTheMonth = date("Y-m-t", strtotime($firstDayOfTheMonth));
$workingDaysThisMonth = getWorkingDays($firstDayOfTheMonth, $lastDayOfTheMonth);

// Testen der Funktion
$currentMonthStart = date("Y-m") . "-01"; // Erster Tag des aktuellen Monats
$currentMonthEnd = date("Y-m-t"); // Letzter Tag des aktuellen Monats

$workingHoursThisMonth = getWorkingHours($currentMonthStart, $currentMonthEnd);

// SQL-Abfrage für die gesamten Arbeitsstunden dieses Monats
$stmt = $conn->prepare('
    SELECT
        SUM(DATEDIFF(MINUTE, startzeit, endzeit)) as totalMinutes
    FROM
        zeiterfassung
    WHERE
        YEAR(startzeit) = :currentYear AND
        MONTH(startzeit) = :currentMonth
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
        SUM(DATEDIFF(MINUTE, startzeit, endzeit)) as totalMinutes
    FROM
        zeiterfassung
    WHERE
        YEAR(startzeit) = :currentYear
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
$overHoursThisWeek = $totalHoursThisWeek - $expectedHoursThisWeek;

function getFeiertageForYear($jahr) {
    global $conn;

    $stmt = $conn->prepare("SELECT Datum FROM Feiertage WHERE YEAR(Datum) = ?");
    $stmt->execute([$jahr]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$feiertageThisYear = getFeiertageForYear($currentYear);

function getGermanDayName($date) {
    $days = ["Sonntag", "Montag", "Dienstag", "Mittwoch", "Donnerstag", "Freitag", "Samstag"];
    return $days[date("w", strtotime($date))];
}

//Gamification
$stmt = $conn->prepare("SELECT COUNT(DISTINCT DATEPART(ISO_WEEK, startzeit)) as weeksCount FROM zeiterfassung");
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$isFirstWeek = $result['weeksCount'] == 1;

$events = [];
foreach ($records as $record) {
    $events[] = [
        'id' => $record['id'],
        'title' => 'Arbeit',
        'start' => date("Y-m-d\TH:i:s", strtotime($record['startzeit'])),
        'end' => date("Y-m-d\TH:i:s", strtotime($record['endzeit'])),
        'category' => 'time',
        'dueDateClass' => '',
        'isAllDay' => false
    ];
}

foreach ($feiertageThisYear as $feiertag) {
    $events[] = [
        'id' => 'feiertag_' . $feiertag['Datum'],
        'title' => 'Feiertag',
        'start' => date("Y-m-d", strtotime($feiertag['Datum'])),
        'end' => date("Y-m-d", strtotime($feiertag['Datum'])),
        'category' => 'allday',
        'isAllDay' => true
    ];
}







