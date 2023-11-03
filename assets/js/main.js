$(document).ready(function () {

    $(".main-title").click(function () {
        $(this).next(".toggle-content").slideToggle();
    });

    function updateDateTimeField() {
        const now = new Date();
        const formattedDate = now.toISOString().slice(0, 16);
        $("#endzeit").val(formattedDate);
    }

    updateDateTimeField();
    setInterval(updateDateTimeField, 60000); // Aktualisieren alle 1 Minute

    const exportOptions = {
        columns: ':not(:last-child)' // schließt die letzte Spalte aus
    };

    const dataTableButtons = ['copyHtml5', 'excelHtml5', 'csvHtml5', 'pdfHtml5'].map(type => ({
        extend: type,
        exportOptions: exportOptions
    }));

    const table = $('.table').DataTable({
        dom: 'Bfrtip',
        buttons: dataTableButtons,
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json"
        }
    });

    $('.table tbody').on('dblclick', 'td', function () {
        const $this = $(this);
        if ($this.closest('table').find('th').eq(table.cell($this).index().column).data('name') !== "dauer") {
            const originalText = $this.text();
            $this.empty().append('<input type="text" value="' + originalText + '"/>').find('input').focus();
        }
    });

    $('.table tbody').on('blur', 'td input', function () {
        const $this = $(this);
        const cellIndex = table.cell($this.parent()).index().column;
        const columnName = $this.closest('table').find('th').eq(cellIndex).data('name');
        const newText = $this.val();
        const cell = table.cell($this.parent());
        const id = $this.closest('tr').find('input[name="id"]').val();

        cell.data(newText).draw();
        $.post('save.php', {
            update: true,
            id: id,
            column: columnName,
            data: newText
        });
    });

    $('select[name="beschreibung"]').change(function () {
        const description = $(this).val();
        if (["Feiertag", "Krankheit"].includes(description)) {
            const startDate = $('input[name="startzeit"]').val().split('T')[0];
            $('input[name="startzeit"]').val(startDate + 'T09:00');
            $('input[name="endzeit"]').val(startDate + 'T17:00');
        }
    });

    const pauseButton = $('#pauseButton');
    const pauseInput = $('#pauseInput');
    const pauseDisplay = $('#pauseDisplay');
    let startTime = parseInt(localStorage.getItem('startTime') || '0');
    let elapsedPauseInSeconds = parseInt(localStorage.getItem('elapsedPauseInSeconds') || '0');
    let updateInterval;

    function getLocalISOTime() {
        const now = new Date();
        const offset = now.getTimezoneOffset() * 60 * 1000;
        const localNow = new Date(now.getTime() - offset);
        return localNow.toISOString().slice(0, 16);
    }

    const startzeitField = $('input[name="startzeit"]');
    const savedStartzeit = localStorage.getItem('startzeit');
    startzeitField.val(savedStartzeit || getLocalISOTime());

    if (!savedStartzeit) {
        localStorage.setItem('startzeit', getLocalISOTime());
    }

    const now = new Date();
    const timeUntilMidnight = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 1) - now;
    setTimeout(function () {
        localStorage.removeItem('startzeit');
    }, timeUntilMidnight);

    function updatePauseDuration() {
        const currentTime = new Date().getTime();
        const currentDurationInSeconds = Math.round((currentTime - startTime) / 1000);
        const totalDurationInSeconds = elapsedPauseInSeconds + currentDurationInSeconds;
        const formattedTime = `${String(Math.floor(totalDurationInSeconds / 60)).padStart(2, '0')}:${String(totalDurationInSeconds % 60).padStart(2, '0')}`;
        pauseDisplay.val(formattedTime);
    }

    if (startTime) {
        updateInterval = setInterval(updatePauseDuration, 1000);
        pauseButton.text('Pause beenden');
    }

    pauseButton.click(function () {
        if (!startTime) {
            startTime = new Date().getTime();
            localStorage.setItem('startTime', startTime.toString());
            localStorage.setItem('elapsedPauseInSeconds', elapsedPauseInSeconds.toString());
            pauseButton.text('Pause beenden');
            updateInterval = setInterval(updatePauseDuration, 1000);
        } else {
            clearInterval(updateInterval);
            const endTime = new Date().getTime();
            const durationInSeconds = Math.round((endTime - startTime) / 1000);
            elapsedPauseInSeconds += durationInSeconds;

            localStorage.removeItem('startTime');
            localStorage.setItem('elapsedPauseInSeconds', elapsedPauseInSeconds.toString());

            const totalDurationInMinutes = Math.round(elapsedPauseInSeconds / 60);
            pauseInput.val(totalDurationInMinutes.toString());

            startTime = 0;
            pauseButton.text('Pause fortsetzen');
        }
    });

    const standortSelect = document.querySelector('select[name="standort"]');
    if (standortSelect) {
        const savedStandort = localStorage.getItem('standort');
        if (savedStandort) {
            standortSelect.value = savedStandort;
        }
        standortSelect.addEventListener('change', function () {
            localStorage.setItem('standort', this.value);
        });
    }

    $('form').submit(function () {
        const pauseManuell = $('#pauseManuell');
        if (pauseManuell.val()) {
            pauseInput.val(pauseManuell.val());
        }
        localStorage.removeItem('startzeit');
        localStorage.removeItem('elapsedPauseInSeconds');
    });

    const startzeitInput = document.querySelector('input[name="startzeit"]');
    const tooltip = new bootstrap.Tooltip(startzeitInput, {
        placement: 'bottom',
        trigger: 'manual' // Nur manuell anzeigen/ausblenden
    });

    $('form').on('submit', function (e) {
        const endzeitInput = document.querySelector('input[name="endzeit"]');
        const pauseInputValue = parseInt(document.querySelector('input[name="pause"]').value);
        const startzeit = new Date(startzeitInput.value);
        const endzeit = new Date(endzeitInput.value);

        if (endzeit < startzeit) {
            e.preventDefault(); // Verhindert das Absenden des Formulars
            tooltip.show();
        } else if (pauseInputValue < 0) {
            e.preventDefault(); // Verhindert das Absenden des Formulars
            new bootstrap.Tooltip(pauseInput, {
                trigger: 'manual'
            }).show();
        } else {
            tooltip.hide();
        }
    });

});



if (window.location.pathname.includes('dashboard.php')) {
    document.addEventListener('DOMContentLoaded', function () {

        function createChart(elementId, type, data, options) {
            const ctx = document.getElementById(elementId).getContext('2d');
            const chart = new Chart(ctx, {
                type: type,
                data: data,
                options: options
            });
        }

        createChart('weeklyHoursChart', 'bar', {
            labels: ['Diese Woche'],
            datasets: [{
                label: 'Arbeitsstunden',
                data: [totalHoursThisWeek],
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                borderColor: 'rgba(75, 192, 192, 1)',
                borderWidth: 1
            }]
        }, {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 60,
                    stepSize: 5,
                    ticks: {
                        callback: function (value, index, values) {
                            if (value === 40) {
                                return value + 'h Ziel';  // Fügt "Ziel" hinzu, wenn der Wert 40 ist
                            }
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
                            mode: 'horizontal',
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
        });

        // Tägliches Chart
        createChart('dailyHoursChart', 'bar', {
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
        }, {
            scales: {
                y: {
                    beginAtZero: true,
                    max: 10,
                    stepSize: 1,
                    ticks: {
                        callback: function (value, index, values) {
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
                            mode: 'horizontal',
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
        });

        // Monats-Chart
        createChart('monthlyHoursChart', 'bar', {
            labels: ['Dieser Monat'],
            datasets: [{
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
        }, {
            scales: {
                y: {
                    beginAtZero: true,
                    max: Math.max(workingHoursThisMonth, totalHoursThisMonthFromRecords) + 10,
                    stepSize: 5,
                    ticks: {
                        callback: function (value, index, values) {
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
                            mode: 'horizontal',
                            scaleID: 'y',
                            value: workingHoursThisMonth,
                            borderColor: '#ffcc33',
                            borderWidth: 2
                        }
                    }
                }
            }
        });

    })
}

