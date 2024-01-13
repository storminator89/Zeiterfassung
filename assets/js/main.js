$(function () {

    // Click event listener for expanding/collapsing toggle-content sections under main-title classes
    $(".main-title").on("click", function () {
        $(this).next(".toggle-content").slideToggle();
        $(this).find('i.fas').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Event listener for showing the importModal dialog box when clicking the #importDbButton
    document.getElementById('importDbButton').addEventListener('click', function () {
        var importModal = new bootstrap.Modal(document.getElementById('importModal'));
        importModal.show();
    });

    // Event listener for submitting the file import form once the primary button is clicked inside the importModal
    document.getElementById('importModal').addEventListener('shown.bs.modal', function () {
        // Remove previously attached click event listeners to avoid duplicate triggers
        $(this).find('.modal-footer .btn-primary').off('click');

        // Attach a click event listener for submitting the form
        $(this).find('.modal-footer .btn-primary').on('click', function (e) {
            console.log('Import-Button geklickt'); // Logging for development purposes
            e.preventDefault(); // Prevent the form from being submitted normally

            // Initialize a FormData object
            var formData = new FormData();

            // Get the file input element
            var fileInput = document.getElementById('dbFile');

            // Ensure a file is chosen
            if (fileInput.files.length > 0) {
                // Append the selected file to the FormData object
                formData.append('dbFile', fileInput.files[0]);

                // Send an AJAX POST request to the import.php endpoint
                $.ajax({
                    url: 'import.php',
                    type: 'POST',
                    data: formData,
                    contentType: false, // Disable automatic conversion of data to query string format
                    processData: false, // Do not convert objects to strings
                    success: function (response) {
                        location.reload(); // Refresh the page upon successful import
                    },
                    error: function (jqXHR, textStatus, errorThrown) {
                        console.log('Fehler: ', textStatus, errorThrown); // Logging for development purposes
                        alert('error import database file'); // Show generic error message to users
                    }
                });
            } else {
                alert('please choose a file'); // Show error message to users when no file is selected
            }
        });
    });

    // Event listener to validate file selection prior to form submission
    document.getElementById('importButton').addEventListener('click', function (e) {
        var fileInput = document.getElementById('dbFile');

        // Block form submission if no file is selected
        if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
            e.preventDefault();
            alert('Please choose a file!');
        }
    });

    var urlaubEintragenButton = document.getElementById('urlaubEintragenButton');
    urlaubEintragenButton.addEventListener('click', function () {
        var urlaubstag = document.querySelector('input[name="urlaubstag"]').value;
        if (urlaubstag) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'save.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (this.status == 200) {
                    console.log('Erfolgreich eingetragen: ', this.responseText);
                    location.reload();
                } else {
                    console.error('Fehler beim Eintragen des Urlaubs');
                }
            };
            xhr.send('urlaubstag=' + encodeURIComponent(urlaubstag));
        } else {
            alert('Bitte wählen Sie ein Datum für den Urlaubstag.');
        }
    });

    var feiertagEintragenButton = document.getElementById('feiertagEintragenButton');
    feiertagEintragenButton.addEventListener('click', function () {
        var feiertag = document.querySelector('input[name="urlaubstag"]').value;
        if (feiertag) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', 'save.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (this.status == 200) {
                    console.log('Erfolgreich eingetragen: ', this.responseText);
                    // Seite aktualisieren
                    location.reload();
                } else {
                    console.error('Fehler beim Eintragen des Feiertags');
                }
            };
            xhr.send('feiertag=' + encodeURIComponent(feiertag));
        } else {
            alert('Bitte wählen Sie ein Datum für den Feiertag.');
        }
    });

    function updateDateTimeField() {
        // Get the current date and time
        var now = new Date();

        // Calculate the offset in minutes between UTC and the local time zone
        var timezoneOffsetMinutes = now.getTimezoneOffset();

        // Adjust the date and time to match the UTC time
        var correctedTime = new Date(now.getTime() - timezoneOffsetMinutes * 60000);

        // Format the adjusted date and time to YYYY-MM-DDTHH:mm and store it in the 'endzeit' field
        $("#endzeit").val(correctedTime.toISOString().slice(0, 16));
    }

    // Initial call to updateDateTimeField followed by periodic calls every minute
    updateDateTimeField();
    setInterval(updateDateTimeField, 60000);

    // Cache DOM references for performance reasons
    var startButton = document.getElementById('startButton'),
        endButton = document.getElementById('endButton');

    // Event listener for the start button
    startButton.addEventListener('click', function () {
        // Get the current date and time and format it to YYYY-MM-DDTHH:mm
        var now = new Date(),
            formattedDateTime = now.getFullYear() + '-' +
                ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
                ('0' + now.getDate()).slice(-2) + 'T' +
                ('0' + now.getHours()).slice(-2) + ':' +
                ('0' + now.getMinutes()).slice(-2);

        // Populate the 'startzeit' field with the formatted date and time
        document.getElementById('startzeit').value = formattedDateTime;

        // Disable the start button
        startButton.disabled = true;

        // Store the formatted date and time in local storage
        localStorage.setItem('startzeit', formattedDateTime);

        // Send an HTTP request to save.php with the startzeit parameter
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "save.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                // Upon success, save the returned lastId in local storage and refresh the page
                var lastId = this.responseText;
                localStorage.setItem('lastId', lastId);
                location.reload();
            }
        };
        xhr.send("startzeit=" + formattedDateTime);
    });

    // Event listener for the end button
    endButton.addEventListener('click', function () {
        // Get the current date and time and format it to YYYY-MM-DDTHH:mm
        var now = new Date(),
            formattedDateTime = now.getFullYear() + '-' +
                ('0' + (now.getMonth() + 1)).slice(-2) + '-' +
                ('0' + now.getDate()).slice(-2) + 'T' +
                ('0' + now.getHours()).slice(-2) + ':' +
                ('0' + now.getMinutes()).slice(-2);

        // Populate the 'endzeit' field with the formatted date and time
        document.getElementById('endzeit').value = formattedDateTime;

        // Enable the start button again
        startButton.disabled = false;

        // Cache DOM reference for efficiency reasons
        var addButton = document.getElementById('addButton');

        // Get relevant input fields and values
        var lastId = localStorage.getItem('lastId'),
            pauseManuell = document.getElementById('pauseManuell').value,
            standort = document.querySelector('select[name="standort"]').value,
            beschreibung = document.querySelector('textarea[name="beschreibung"]').value;

        // Send an HTTP request to save.php with required parameters
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "save.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (this.readyState == 4 && this.status == 200) {
                // Upon success, reload the page
                location.reload();
            }
        };
        xhr.send("endzeit=" + formattedDateTime +
            "&id=" + lastId +
            "&aktion=gehen" +
            "&pause=" + pauseManuell +
            "&standort=" + encodeURIComponent(standort) +
            "&beschreibung=" + encodeURIComponent(beschreibung));
    });



    // Configuration for DataTables export options
    const exportOptions = {
        columns: ':not(:last-child)'
    };

    // Buttons configuration for DataTables
    const buttons = [
        {
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

    // Fade in the success alert
    $(".alert-success").fadeIn(1000);

    // Define variables earlier
    const isFirstWeekInput = document.getElementById('isFirstWeek');
    const isFirstWeek = isFirstWeekInput.value === '1';
    const notificationContainer = document.getElementById('firstWeekNotification');

    // Conditionally create and append the first-week notification div
    if (isFirstWeek && !localStorage.getItem("firstWeekNotified")) {
        const notificationDiv = document.createElement('div');
        notificationDiv.className = 'alert-success col';
        notificationDiv.style.display = "block";
        notificationDiv.innerHTML = `
        <div class='alert-header'>
            <i class='fas fa-trophy'></i>
            <strong>Gratulation!</strong>
        </div>
        <p>Sie haben Ihre erste Arbeitswoche erfasst! Weiter so!</p>
    `;
        notificationContainer.appendChild(notificationDiv);

        // Mark the notification as displayed
        localStorage.setItem("firstWeekNotified", "true");
    }

    $.fn.dataTable.moment('DD.MM.YYYY HH:mm:ss');

    // Initialize the DataTable
    $('.table').DataTable({
        dom: 'Bfrtip',
        buttons,
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json"
        },
        order: [[2, 'desc']],
        paging: true
    });

    // Double-click event handler for table cells
    $('.table tbody').on('dblclick', 'td', function () {
        let $cell = $(this);
        let col = $cell.closest('table').DataTable().cell($cell).index().column;
        let columnName = $cell.closest('table').find('th').eq(col).data('name');

        // Prevent editing of the "dauer" column
        if (columnName !== "dauer") {
            let html = $cell.text().trim();
            let inputElement;

            // For "startzeit" and "endzeit", use datetime-local
            if (columnName === 'startzeit' || columnName === 'endzeit') {
                // Convert the date into the correct format for datetime-local
                let currentDateTime = new Date(html.replace(/(\d+).(\d+).(\d+) (\d+):(\d+):(\d+)/, '$3-$2-$1T$4:$5'));
                // Create an offset in minutes and convert it to milliseconds
                let timezoneOffset = currentDateTime.getTimezoneOffset() * 60000;
                // Create a new date by subtracting the offset to get the local time
                let localDateTime = new Date(currentDateTime.getTime() - timezoneOffset);
                // Convert the date into the local format for datetime-local
                let dateTimeLocalString = localDateTime.toISOString().slice(0, 16);
                inputElement = `<input type="datetime-local" value="${dateTimeLocalString}"/>`;
            } else {
                inputElement = `<input type="text" value="${html}"/>`;
            }

            $cell.html(inputElement);
            $cell.find('input').focus();
        }
    });

    // Blur event handler for table cells
    $('.table tbody').on('blur', 'td input', function () {
        let $input = $(this);
        let cell = $input.closest('table').DataTable().cell($input.parent());
        let col = cell.index().column;
        let newVal = $input.val();
        let id = $input.closest('tr').find('input[name="id"]').val();
        let columnName = $input.closest('table').find('th').eq(col).data('name');

        cell.data(newVal).draw();

        $.post('save.php', {
            update: true,
            id: id,
            column: columnName,
            data: newVal
        }).done(function () {
            location.reload();
        });
    });

    // Keypress event handler for table cells
    $('.table tbody').on('keypress', 'td input', function (e) {
        if (e.which == 13) { // Return key
            let $input = $(this);
            let cell = $input.closest('table').DataTable().cell($input.parent());
            let col = cell.index().column;
            let newVal = $input.val();
            let id = $input.closest('tr').find('input[name="id"]').val();
            let columnName = $input.closest('table').find('th').eq(col).data('name');

            cell.data(newVal).draw();

            $.post('save.php', {
                update: true,
                id: id,
                column: columnName,
                data: newVal
            }).done(function () {
                location.reload();
            });

            e.preventDefault();
        }
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
    let elapsedPause = parseInt(localStorage.getItem('elapsedPauseInSeconds') || 0, 10);
    let previousDate = localStorage.getItem('previousDate');

    if (previousDate && new Date(previousDate).getDate() != new Date().getDate()) {
        elapsedPause = 0;
    }

    localStorage.setItem('previousDate', new Date().toString());
    let isPaused = !startTime;
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
        let totalMinutes = Math.round(totalDuration / 60);
        $('#pauseManuell').val(totalMinutes);
    }

    function formatTime(seconds) {
        return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
    }

    if (elapsedPause > 0) {
        pauseDisplay.val(formatTime(elapsedPause));
    }

    if (startTime) {
        interval = setInterval(updatePause, 1000);
        pauseBtn.text('Pause beenden');
    } else if (elapsedPause > 0) {
        pauseBtn.text('Pause fortsetzen');
    } else {
        pauseBtn.text('Pause starten');
    }

    pauseBtn.click(function () {
        if (isPaused) {
            startTime = Date.now();
            localStorage.setItem('startTime', startTime);
            pauseBtn.text('Pause beenden');
            interval = setInterval(updatePause, 1000);
            isPaused = false;
        } else {
            clearInterval(interval);
            let endTime = Date.now();
            let duration = Math.round((endTime - startTime) / 1000);
            elapsedPause += duration;

            let totalMinutes = Math.round(elapsedPause / 60);
            $('#pauseManuell').val(totalMinutes);

            localStorage.setItem('elapsedPauseInSeconds', elapsedPause);
            localStorage.removeItem('startTime');
            startTime = null;

            pauseBtn.text('Pause starten');
            isPaused = true;
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

function toggleDarkMode() {
    document.body.classList.toggle('dark-mode');
    var icon = document.querySelector('.fancy-title img');
    if (document.body.classList.contains('dark-mode')) {
        icon.src = 'assets/kolibri_icon_weiß.png';
    } else {
        icon.src = 'assets/kolibri_icon.png';
    }
}




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

            const validTitles = ['Arbeit', 'Urlaub'];
            if (validTitles.includes(schedule.title)) {
                let startDate = new Date(schedule.start);
                let endDate = new Date(schedule.end);

                document.getElementById('startTime').textContent = formatDate(startDate) + " " + startDate.toLocaleTimeString();
                document.getElementById('endTime').textContent = formatDate(endDate) + " " + endDate.toLocaleTimeString();

                scheduleModal.show();
            }
        });
    });

}