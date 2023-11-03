<?php
include 'config.php';

// Fetch all records
$stmt = $conn->prepare('SELECT *, DATEPART(ISO_WEEK, startzeit) as weekNumber FROM zeiterfassung ORDER BY startzeit DESC');
$stmt->execute();
$records = $stmt->fetchAll();

$currentWeekNumber = date("W");

// Get total hours worked this week
$stmt = $conn->prepare('
SELECT SUM(DATEDIFF(MINUTE, startzeit, endzeit)) as totalMinutes
FROM zeiterfassung
WHERE YEAR(startzeit) = 2023
AND DATEPART(ISO_WEEK, startzeit) = :weekNumber
');
$stmt->bindParam(':weekNumber', $currentWeekNumber);
$stmt->execute();
$totalMinutesThisWeek = $stmt->fetchColumn();
$totalHoursThisWeek = floor($totalMinutesThisWeek / 60);


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

function fetchFeiertage($jahr)
{
    if (isset($_SESSION['feiertage'][$jahr])) {
        return $_SESSION['feiertage'][$jahr];
    }

    $url = "https://feiertage-api.de/api/?jahr=$jahr&nur_land=BW";
    $json = file_get_contents($url);
    $data = json_decode($json, true);

    $feiertage = [];
    foreach ($data as $feiertag) {
        $feiertage[] = $feiertag['datum'];
    }

    $_SESSION['feiertage'][$jahr] = $feiertage;
    
    return $feiertage;
}

function istFeiertag($datum)
{
    $jahr = date('Y', strtotime($datum));
    
    // Verwenden Sie den Cached-Wert, anstatt jedes Mal die API aufzurufen
    if (!isset($_SESSION['feiertage'][$jahr])) {
        $_SESSION['feiertage'][$jahr] = fetchFeiertage($jahr);
    }
    
    return in_array($datum, $_SESSION['feiertage'][$jahr]);
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