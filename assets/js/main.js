$(function () {

    $(".main-title").on("click", function () {
        $(this).next(".toggle-content").slideToggle();

        $(this).find('i.fas').toggleClass('fa-chevron-down fa-chevron-up');
    });

    function updateDateTimeField() {
        
        var now = new Date();    
        var timezoneOffsetMinutes = now.getTimezoneOffset();        
        
        var correctedTime = new Date(now.getTime() - timezoneOffsetMinutes * 60000);        
        $("#endzeit").val(correctedTime.toISOString().slice(0, 16));
    }    
    
    updateDateTimeField();    
    setInterval(updateDateTimeField, 60000);
    var startButton = document.getElementById('startButton');
    var endButton = document.getElementById('endButton');

    startButton.addEventListener('click', function () {
        var now = new Date();
        var formattedDateTime = now.getFullYear() + '-' +
            ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
            ('0' + now.getDate()).slice(-2) + 'T' +
            ('0' + now.getHours()).slice(-2) + ':' +
            ('0' + now.getMinutes()).slice(-2);
        document.getElementById('startzeit').value = formattedDateTime;
        startButton.disabled = true;
        localStorage.setItem('startzeit', formattedDateTime);
    });

    endButton.addEventListener('click', function () {
        var now = new Date();
        var formattedDateTime = now.getFullYear() + '-' +
            ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
            ('0' + now.getDate()).slice(-2) + 'T' +
            ('0' + now.getHours()).slice(-2) + ':' +
            ('0' + now.getMinutes()).slice(-2);
        document.getElementById('endzeit').value = formattedDateTime;
        startButton.disabled = false;
        var addButton = document.getElementById('addButton');
        addButton.style.display = 'block';
        addButton.classList.add('fade-in');
    });




    const exportOptions = {
        columns: ':not(:last-child)'
    };

    const buttons = [{
        extend: 'copyHtml5',
        exportOptions,
        text: '<i class="fas fa-copy"></i> Kopieren'
    },
    {
        extend: 'excelHtml5',
        exportOptions,
        text: '<i class="fas fa-file-excel"></i> Excel'
    },
    {
        extend: 'csvHtml5',
        exportOptions,
        text: '<i class="fas fa-file-csv"></i> CSV'
    },
    {
        extend: 'pdfHtml5',
        exportOptions,
        text: '<i class="fas fa-file-pdf"></i> PDF'
    }
    ];

    $(".alert-success").fadeIn(1000);

    // Variablen zuerst definieren
    let isFirstWeekInput = document.getElementById('isFirstWeek');
    let isFirstWeek = isFirstWeekInput.value === '1';
    let notificationContainer = document.getElementById('firstWeekNotification');

    if (isFirstWeek && !localStorage.getItem("firstWeekNotified")) {
        // Erstelle die Benachrichtigungs-Div und füge sie zum Platzhalter hinzu
        let notificationDiv = document.createElement('div');
        notificationDiv.className = 'alert-success col';
        notificationDiv.style.display = "block";  // <-- Hier setzen wir die Anzeige auf "block"
        notificationDiv.innerHTML = `
            <div class='alert-header'>
                <i class='fas fa-trophy'></i>
                <strong>Gratulation!</strong>
            </div>
            <p>Sie haben Ihre erste Arbeitswoche erfasst! Weiter so!</p>
        `;
        notificationContainer.appendChild(notificationDiv);

        // Setzen Sie den localStorage-Eintrag, um zu vermerken, dass die Benachrichtigung angezeigt wurde.
        localStorage.setItem("firstWeekNotified", "true");
    }

    $('.table').DataTable({
        dom: 'Bfrtip',
        buttons,
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json"
        }
    });

    $('.table tbody').on('dblclick', 'td', function () {
        let $cell = $(this);
        let col = $cell.closest('table').DataTable().cell($cell).index().column;

        if ($cell.closest('table').find('th').eq(col).data('name') !== "dauer") {
            let html = $cell.html();
            $cell.html(`<input type="text" value="${html}"/>`);
            $cell.find('input').focus();
        }
    });

    $('.table tbody').on('blur', 'td input', function () {
        let $input = $(this);
        let cell = $input.closest('table').DataTable().cell($input.parent());
        let col = cell.index().column;
        let newVal = $input.val();
        let id = $input.closest('tr').find('input[name="id"]').val();
        let columnName = $input.closest('table').find('th').eq(col).data('name');

        // Prüfen, ob das bearbeitete Feld ein Datum oder eine Zeit ist
        if (columnName === 'startzeit' || columnName === 'endzeit') {
            // Versuchen, das Datum/Zeit im deutschen Format zu parsen
            let germanDatePattern = /(\d{2})\.(\d{2})\.(\d{4}) (\d{2}):(\d{2}):(\d{2})/;
            if (germanDatePattern.test(newVal)) {
                // Umwandlung in das ISO-8601-Format
                let matches = germanDatePattern.exec(newVal);
                newVal = `${matches[3]}-${matches[2]}-${matches[1]}T${matches[4]}:${matches[5]}:${matches[6]}`;
            }
        }

        cell.data(newVal).draw();

        $.post('save.php', {
            update: true,
            id: id,
            column: columnName,
            data: newVal
        });
    });

    $('select[name="beschreibung"]').change(function () {
        let desc = $(this).val();
        let date = $('input[name="startzeit"]').val().split('T')[0];
    
        if (desc === "Feiertag") {
            $('input[name="startzeit"]').val(`${date}T00:00`);
            $('input[name="endzeit"]').val(`${date}T00:00`);
        } else if (["Urlaub", "Krankheit"].includes(desc)) {
            $('input[name="startzeit"]').val(`${date}T09:00`);
            $('input[name="endzeit"]').val(`${date}T17:00`);
        }
        $('#addButton').show();
    });

    let pauseBtn = $('#pauseButton');
    let pauseDisplay = $('#pauseDisplay');
    let startTime = parseInt(localStorage.getItem('startTime')) || 0;
    let elapsedPause = parseInt(localStorage.getItem('elapsedPauseInSeconds')) || 0;
    let interval;

    let startzeitField = $('input[name="startzeit"]');
    let savedStartzeit = localStorage.getItem('startzeit');

    if (savedStartzeit) {
        startzeitField.val(savedStartzeit);
    } else {
        startzeitField.val("");
    }

    let timeUntilMidnight = new Date(Date.now() + 86400000) - Date.now();
    setTimeout(() => {
        localStorage.removeItem('startzeit');
    }, timeUntilMidnight);

    function updatePause() {
        let currentTime = Date.now();
        let currentDuration = Math.round((currentTime - startTime) / 1000);
        let totalDuration = elapsedPause + currentDuration;

        pauseDisplay.val(formatTime(totalDuration));
    }

    function formatTime(seconds) {
        return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    }

    if (startTime) {
        interval = setInterval(updatePause, 1000);
        pauseBtn.text('Pause beenden');
    }

    pauseBtn.click(function () {
        if (!startTime) {
            startTime = Date.now();
            localStorage.setItem('startTime', startTime);
            localStorage.setItem('elapsedPauseInSeconds', elapsedPause);
            pauseBtn.text('Pause beenden');
            interval = setInterval(updatePause, 1000);
        } else {
            clearInterval(interval);
            let endTime = Date.now();
            let duration = Math.round((endTime - startTime) / 1000);
            elapsedPause += duration;

            let totalPauseMinutes = Math.round(elapsedPause / 60);
            document.getElementById('pauseInput').value = totalPauseMinutes;

            localStorage.removeItem('startTime');
            localStorage.setItem('elapsedPauseInSeconds', elapsedPause);

            pauseBtn.text('Pause fortsetzen');
        }
    });

    let standortSelect = document.querySelector('select[name="standort"]');
    if (standortSelect) {
        let savedStandort = localStorage.getItem('standort');
        if (savedStandort) {
            standortSelect.value = savedStandort;
        }

        standortSelect.addEventListener('change', () => {
            localStorage.setItem('standort', standortSelect.value);
        });
    }

    let startInput = document.querySelector('input[name="startzeit"]');
    let endInput = document.querySelector('input[name="endzeit"]');
    let pauseInput = document.querySelector('#pauseInput');

    let endTooltip = new bootstrap.Tooltip(endInput, {
        title: "Endzeit sollte nach der Startzeit liegen.",
        trigger: 'manual',
        placement: 'bottom'
    });

    let pauseTooltip = new bootstrap.Tooltip(pauseInput, {
        title: "Pause sollte nicht negativ sein.",
        trigger: 'manual',
        placement: 'bottom'
    });

    $('#mainForm').on('submit', function (e) {
        let pauseManuell = $('#pauseManuell').val();
        if (pauseManuell) {
            pauseInput.value = pauseManuell;
        }

        let pauseVal = parseInt(pauseInput.value);
        let start = new Date(startInput.value);
        let end = new Date(endInput.value);

        if (end < start) {
            e.preventDefault();
            endTooltip.show();
        } else if (pauseVal < 0) {
            e.preventDefault();
            pauseTooltip.show();
        } else {
            endTooltip.hide();
            pauseTooltip.hide();
        }

        localStorage.removeItem('startzeit');
        localStorage.removeItem('elapsedPauseInSeconds');
    });

});

if (window.location.pathname.includes('dashboard.php')) {

    document.addEventListener('DOMContentLoaded', () => {

        function createChart(id, type, data, options) {
            let ctx = document.getElementById(id).getContext('2d');
            return new Chart(ctx, {
                type,
                data,
                options
            });
        }

        let weeklyData = {
            labels: ['Diese Woche'],
            datasets: [{
                label: 'Arbeitsstunden',
                data: [totalHoursThisWeek],
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        };

        let weeklyOptions = {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 60,
                    stepSize: 5,
                    ticks: {
                        callback(value) {
                            return value + (value === 40 ? 'h Ziel' : 'h');
                        }
                    }
                }
            },
            plugins: {
                annotation: {
                    annotations: {
                        line1: {
                            type: 'line',
                            scaleID: 'y',
                            value: 40,
                            borderColor: '#ffcc33',
                            borderWidth: 2,
                            label: {
                                enabled: true,
                                content: '40h Ziel'
                            }
                        }
                    }
                }
            }
        };

        let weeklyChart = createChart('weeklyHoursChart', 'bar', weeklyData, weeklyOptions);

        let dailyData = {
            labels: days,
            datasets: [{
                label: 'Arbeitsstunden pro Tag diese Woche',
                data: hours,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(255, 159, 64, 0.2)',
                    'rgba(255, 205, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(201, 203, 207, 0.2)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(255, 159, 64, 1)',
                    'rgba(255, 205, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(201, 203, 207, 1)'
                ],
                borderWidth: 1
            }]
        };

        let dailyOptions = {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    stepSize: 1,
                    ticks: {
                        callback(value) {
                            return value + 'h';
                        }
                    }
                }
            },
            plugins: {
                annotation: {
                    annotations: {
                        line1: {
                            type: 'line',
                            scaleID: 'y',
                            value: 8,
                            borderColor: '#ffcc33',
                            borderWidth: 2,
                            label: {
                                enabled: true,
                                content: '8h Ziel'
                            }
                        }
                    }
                }
            }
        };

        let dailyChart = createChart('dailyHoursChart', 'bar', dailyData, dailyOptions);

        let monthlyData = {
            labels: ['Dieser Monat'],
            datasets: [
                {
                    label: 'Tatsächliche Arbeitsstunden',
                    data: [totalHoursThisMonthFromRecords],
                    backgroundColor: 'rgba(153, 102, 255, 0.2)',
                    borderColor: 'rgba(153, 102, 255, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Soll-Arbeitsstunden',
                    data: [workingHoursThisMonth],
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 1
                }
            ]
        };

        let monthlyOptions = {
            scales: {
                y: {
                    beginAtZero: true,
                    max: Math.max(workingHoursThisMonth, totalHoursThisMonthFromRecords) + 10,
                    stepSize: 5,
                    ticks: {
                        callback(value) {
                            return value + 'h';
                        }
                    }
                }
            },
            plugins: {
                annotations: {
                    annotations: {
                        line1: {
                            type: 'line',
                            scaleID: 'y',
                            value: workingHoursThisMonth,
                            borderColor: '#ffcc33',
                            borderWidth: 2
                        }
                    }
                }
            }
        };

        let monthlyChart = createChart('monthlyHoursChart', 'bar', monthlyData, monthlyOptions);

        let calendar = new tui.Calendar('#calendar', {
            defaultView: 'week',
            workweek: true,
            startDayOfWeek: 1,
            taskView: false,
            milestoneView: false,
            week: {
                startDayOfWeek: 1
            },
            template: {
                // hier können Sie weitere Vorlagen hinzufügen, falls benötigt
            }
        });

        calendar.createSchedules(allEvents);
        document.getElementById('prevMonthBtn').addEventListener('click', function () {
            calendar.prev();
        });

        document.getElementById('nextMonthBtn').addEventListener('click', function () {
            calendar.next();
        });

        document.getElementById('todayBtn').addEventListener('click', function () {
            calendar.today();
        });

        function formatDate(date) {
            let day = ("0" + date.getDate()).slice(-2);
            let month = ("0" + (date.getMonth() + 1)).slice(-2);
            let year = date.getFullYear();
            return day + "." + month + "." + year;
        }

        let scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));

        calendar.on('clickSchedule', function (event) {
            let schedule = event.schedule;

            if (schedule.title === 'Arbeit') {
                let startDate = new Date(schedule.start);
                let endDate = new Date(schedule.end);

                document.getElementById('startTime').textContent = formatDate(startDate) + " " + startDate.toLocaleTimeString();
                document.getElementById('endTime').textContent = formatDate(endDate) + " " + endDate.toLocaleTimeString();

                scheduleModal.show();
            }
        });
    });

}