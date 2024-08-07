<?php
include 'header.php';

// Fetch user's regular working hours and previous overtime
$stmt = $conn->prepare('SELECT regelarbeitszeit, ueberstunden FROM users WHERE id = :user_id');
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$userData = $stmt->fetch(PDO::FETCH_ASSOC);
$userRegularWorkingHours = $userData['regelarbeitszeit'] ?? 8;
$previousOvertime = $userData['ueberstunden'] ?? 0.0;

// Current date details
$currentWeekNumber = date("W");
$currentYear = date("Y");
$currentMonth = date("m");
$currentMonthName = $months[$currentMonth];

// Fetch working hours data
$totalHoursThisWeek = $totalHoursThisWeek ?? 0;
$totalHoursThisMonth = $totalHoursThisMonthFromRecords ?? 0;
$workingHoursThisMonth = $workingHoursThisMonth ?? 0;
$totalOverHours = $totalOverHours ?? 0;
$overHoursThisWeek = $totalHoursThisWeek - (5 * $userRegularWorkingHours);
$overHoursThisMonth = $overHoursThisMonth ?? 0;
$overHoursThisYear = $overHoursThisYear ?? 0;

// Fetch events for the calendar
$stmt = $conn->prepare("SELECT id, startzeit as start, endzeit as end, beschreibung as title FROM zeiterfassung WHERE user_id = ? AND startzeit >= ? ORDER BY startzeit");
$stmt->execute([$user_id, date('Y-m-d', strtotime('-1 month'))]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gamification data
$stmt = $conn->prepare("SELECT COUNT(DISTINCT strftime('%W', startzeit)) as weeksCount FROM zeiterfassung WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$weeksCount = $result['weeksCount'];
$isFirstWeek = $weeksCount == 1;

// Fetch the number of worked days this month
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT DATE(startzeit)) as workedDays 
    FROM zeiterfassung 
    WHERE user_id = :user_id 
    AND startzeit >= :month_start 
    AND startzeit < :month_end
");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->bindValue(':month_start', date('Y-m-01 00:00:00'), PDO::PARAM_STR);
$stmt->bindValue(':month_end', date('Y-m-d 23:59:59', strtotime('last day of this month')), PDO::PARAM_STR);
$stmt->execute();
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$workedDays = $result['workedDays'];

// Calculate average daily hours for this month
$averageDailyHours = $workedDays > 0 ? $totalHoursThisMonth / $workedDays : 0;

// Calculate monthly productivity score
$productivityScore = min(100, ($totalHoursThisMonth / $workingHoursThisMonth) * 100);

// Calculate weekly productivity score
$weeklyExpectedHours = 5 * $userRegularWorkingHours; // Assuming a 5-day work week
$weeklyProductivityScore = min(100, ($totalHoursThisWeek / $weeklyExpectedHours) * 100);

// Theme Mode aus der Session lesen
$theme_mode = $_SESSION['theme_mode'] ?? 'light';

// Function to format hours as HH:MM
function formatHoursAsHHMM($hours) {
    $isNegative = $hours < 0;
    $hours = abs($hours);
    $h = floor($hours);
    $m = round(($hours - $h) * 60);
    return ($isNegative ? '-' : '') . sprintf("%02d:%02d", $h, $m);
}

// Function to calculate remaining hours to work
function calculateRemainingHours($workedHours, $expectedHours) {
    $remaining = $expectedHours - $workedHours;
    return $remaining > 0 ? formatHoursAsHHMM($remaining) : '00:00';
}
?>

<!DOCTYPE html>
<html lang="<?= $lang ?>" data-theme="<?= $theme_mode ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= TITLE ?> - Dashboard</title>
    <link rel="icon" href="assets/kolibri_icon.png" type="image/png">

    <!-- DaisyUI and Tailwind CSS -->
    <link href="https://cdn.jsdelivr.net/npm/daisyui@3.9.4/dist/full.css" rel="stylesheet" type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    <!-- Toast UI Calendar -->
    <link rel="stylesheet" href="https://uicdn.toast.com/calendar/latest/toastui-calendar.min.css" />
    <script src="https://uicdn.toast.com/calendar/latest/toastui-calendar.js"></script>

    <!-- Custom CSS -->
    <link rel="stylesheet" type="text/css" href="./assets/css/main.css">

    <style>
        .stat-value {
            font-size: 2rem;
        }
        .calendar-container {
            height: 700px;
            overflow: hidden;
            border-radius: 1rem;
        }
        .toastui-calendar-milestone,
        .toastui-calendar-allday,
        .toastui-calendar-task {
            display: none !important;
        }
        .stat,
        .stat-title,
        .stat-value,
        .stat-desc {
            color: white !important;
        }
    </style>
</head>

<body class="bg-base-200">
    <div class="container mx-auto px-4 py-8">
        <h2 class="text-4xl font-bold mb-8 text-center"><?= DASHBOARD_TITLE ?></h2>
        
        <!-- Overall Stats -->
        <div class="mb-8">
            <h3 class="text-2xl font-semibold mb-4"><?= DASHBOARD_OVERALL_STATS ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="stat bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-user-clock fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_REGULAR_HOURS ?></div>
                    <div class="stat-value"><?= formatHoursAsHHMM($userRegularWorkingHours) ?></div>
                    <div class="stat-desc text-sm font-medium opacity-80"><?= DASHBOARD_HOURS_PER_DAY ?></div>
                </div>
                <div class="stat bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-trophy fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_WEEKS_WORKED ?></div>
                    <div class="stat-value"><?= $weeksCount ?></div>
                    <div class="stat-desc text-sm font-medium opacity-80">
                        <?= $isFirstWeek ? DASHBOARD_FIRST_WEEK : DASHBOARD_KEEP_GOING ?>
                    </div>
                </div>
                <div class="stat bg-gradient-to-br from-indigo-400 to-indigo-500 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-hourglass-half fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_TOTAL_OVERTIME ?></div>
                    <div class="stat-value"><?= formatHoursAsHHMM($totalOverHours) ?></div>
                    <div class="stat-desc text-sm font-medium opacity-80"><?= sprintf(DASHBOARD_OVER_HOURS_YEAR, formatHoursAsHHMM($overHoursThisYear)) ?></div>
                </div>
            </div>
        </div>

        <!-- Weekly Overview -->
        <div class="mb-8">
            <h3 class="text-2xl font-semibold mb-4"><?= DASHBOARD_WEEKLY_OVERVIEW ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="stat bg-gradient-to-br from-blue-600 to-blue-700 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-clock fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_WEEKLY_HOURS ?></div>
                    <div class="stat-value"><?= formatHoursAsHHMM($totalHoursThisWeek) ?></div>
                </div>
                <div class="stat bg-gradient-to-br from-blue-500 to-blue-600 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-hourglass-half fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_REMAINING_HOURS_WEEK ?></div>
                    <div class="stat-value"><?= calculateRemainingHours($totalHoursThisWeek, 5 * $userRegularWorkingHours) ?></div>
                </div>
                <div class="stat bg-gradient-to-br from-blue-400 to-blue-500 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-tachometer-alt fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_WEEKLY_PRODUCTIVITY ?></div>
                    <div class="stat-value"><?= number_format($weeklyProductivityScore, 1) ?>%</div>
                </div>
            </div>
        </div>

        <!-- Monthly Overview -->
        <div class="mb-8">
            <h3 class="text-2xl font-semibold mb-4"><?= DASHBOARD_MONTHLY_OVERVIEW ?></h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="stat bg-gradient-to-br from-teal-600 to-teal-700 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-calendar fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_MONTHLY_HOURS ?></div>
                    <div class="stat-value"><?= formatHoursAsHHMM($totalHoursThisMonth) ?></div>
                    <div class="stat-desc text-sm font-medium opacity-80">
                        <?= sprintf(DASHBOARD_MONTHLY_HOURS_PROGRESS, formatHoursAsHHMM($totalHoursThisMonth), formatHoursAsHHMM($workingHoursThisMonth)) ?>
                    </div>
                </div>
                <div class="stat bg-gradient-to-br from-teal-500 to-teal-600 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-chart-line fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_AVERAGE_DAILY_HOURS ?></div>
                    <div class="stat-value"><?= formatHoursAsHHMM($averageDailyHours) ?></div>
                    <div class="stat-desc text-sm font-medium opacity-80">
                        <?= sprintf(DASHBOARD_WORKED_DAYS_THIS_MONTH, $workedDays) ?>
                    </div>
                </div>
                <div class="stat bg-gradient-to-br from-teal-400 to-teal-500 rounded-box shadow-lg">
                    <div class="stat-figure opacity-70">
                        <i class="fas fa-tachometer-alt fa-3x"></i>
                    </div>
                    <div class="stat-title text-lg font-semibold opacity-80"><?= DASHBOARD_PRODUCTIVITY_SCORE ?></div>
                    <div class="stat-value"><?= number_format($productivityScore, 1) ?>%</div>
                </div>
            </div>
        </div>

        <!-- Calendar Section -->
        <div class="card bg-base-100 shadow-xl mb-8">
            <div class="card-body">
                <h3 class="card-title text-2xl mb-6"><?= DASHBOARD_CALENDAR ?></h3>
                <div class="flex justify-between mb-6">
                    <button id="prevWeekBtn" class="btn btn-primary btn-sm"><i class="fas fa-arrow-left mr-2"></i><?= BUTTON_PREV_WEEK ?></button>
                    <button id="todayBtn" class="btn btn-secondary btn-sm"><i class="fas fa-calendar-day mr-2"></i><?= BUTTON_TODAY ?></button>
                    <button id="nextWeekBtn" class="btn btn-primary btn-sm"><?= BUTTON_NEXT_WEEK ?><i class="fas fa-arrow-right ml-2"></i></button>
                </div>
                <div id="calendar" class="calendar-container"></div>
            </div>
        </div>
    </div>

        <!-- Schedule Modal -->
        <div class="modal" id="scheduleModal">
        <div class="modal-box">
            <h3 class="font-bold text-lg" id="scheduleModalLabel"><?= MODAL_TITLE_SCHEDULE ?></h3>
            <p class="py-4">
                <strong><?= FORM_START ?>:</strong> <span id="startTime"></span><br>
                <strong><?= FORM_END ?>:</strong> <span id="endTime"></span>
            </p>
            <div class="modal-action">
                <button class="btn" onclick="closeModal()"><?= BUTTON_CLOSE ?></button>
            </div>
        </div>
    </div>

    <script>
    // Calendar initialization
    const Calendar = tui.Calendar;
    const calendar = new Calendar('#calendar', {
        defaultView: 'week',
        useCreationPopup: false,
        useDetailPopup: false,
        isReadOnly: true,
        week: {
            dayNames: ['<?= SUNDAY ?>', '<?= MONDAY ?>', '<?= TUESDAY ?>', '<?= WEDNESDAY ?>', '<?= THURSDAY ?>', '<?= FRIDAY ?>', '<?= SATURDAY ?>'],
            startDayOfWeek: 1,
            workweek: true,
            hourStart: 6,
            hourEnd: 22,
            taskView: false,
            eventView: ['time']
        },
        month: {
            visibleWeeksCount: 0 // Hide the month view completely
        },
        template: {
            time: function(event) {
                const start = new Date(event.start);
                const end = new Date(event.end);
                const startTime = start.getHours().toString().padStart(2, '0') + ':' + start.getMinutes().toString().padStart(2, '0');
                const endTime = end.getHours().toString().padStart(2, '0') + ':' + end.getMinutes().toString().padStart(2, '0');
                return `<span>${startTime} - ${endTime} ${event.title}</span>`;
            }
        },
        taskView: false,
        scheduleView: ['time'],
        milestone: {
            visible: false
        },
        timezones: [{
            timezoneOffset: 0,
            displayLabel: 'GMT',
            tooltip: 'GMT'
        }],
        disableClick: true,
        disableDblClick: true,
    });

    // Populate calendar with events
    const events = <?= json_encode($events) ?>;
    calendar.createEvents(events.map(event => ({
        id: event.id,
        calendarId: '1',
        title: event.title,
        start: event.start,
        end: event.end,
        category: 'time',
        isAllDay: false
    })));

    // Calendar navigation
    document.getElementById('prevWeekBtn').addEventListener('click', function() {
        calendar.prev();
    });

    document.getElementById('nextWeekBtn').addEventListener('click', function() {
        calendar.next();
    });

    document.getElementById('todayBtn').addEventListener('click', function() {
        calendar.today();
    });

    function formatDate(date) {
        let day = ("0" + date.getDate()).slice(-2);
        let month = ("0" + (date.getMonth() + 1)).slice(-2);
        let year = date.getFullYear();
        return day + "." + month + "." + year;
    }

    function showModal() {
        document.getElementById('scheduleModal').classList.add('modal-open');
    }

    function closeModal() {
        document.getElementById('scheduleModal').classList.remove('modal-open');
    }

    calendar.on('clickEvent', function (event) {
        const validTitles = ['<?= WORK ?>', '<?= VACATION ?>'];
        if (validTitles.includes(event.event.title)) {
            let startDate = new Date(event.event.start);
            let endDate = new Date(event.event.end);

            document.getElementById('startTime').textContent = formatDate(startDate) + " " + startDate.toLocaleTimeString();
            document.getElementById('endTime').textContent = formatDate(endDate) + " " + endDate.toLocaleTimeString();

            showModal();
        }
    });
    </script>

</body>
</html>

