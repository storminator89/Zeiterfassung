$(function () {

    // Click event listener for expanding/collapsing toggle-content sections under main-title classes
    $(".main-title").on("click", function () {
        $(this).next(".toggle-content").slideToggle();
        $(this).find('i.fas').toggleClass('fa-chevron-down fa-chevron-up');
    });

    // Event listener for showing the importModal dialog box when clicking the #importDbButton
    const importDbButton = document.getElementById('importDbButton');
    if (importDbButton) {
        importDbButton.addEventListener('click', function () {
            // Replace bootstrap modal with native dialog
            document.getElementById('importModal').showModal();
        });
    }

    // Event listener for submitting the file import form once the primary button is clicked inside the importModal
    const importModal = document.getElementById('importModal');
    if (importModal) {
        importModal.addEventListener('shown.bs.modal', function () {
            // Remove previously attached click event listeners to avoid duplicate triggers
            $(this).find('.modal-footer .btn-primary').off('click');

            // Attach a click event listener for submitting the form
            $(this).find('.modal-footer .btn-primary').on('click', function (e) {
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
                            // Ersetze das reguläre Alert mit SweetAlert
                            Swal.fire({
                                icon: 'error',
                                title: 'Importfehler',
                                text: 'Fehler beim Importieren der Datenbankdatei.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#3085d6' // Added contrasting color
                            });
                        }
                    });
                } else {
                    // Ersetze das reguläre Alert mit SweetAlert
                    Swal.fire({
                        icon: 'warning',
                        title: 'Keine Datei ausgewählt',
                        text: 'Bitte wählen Sie eine Datei aus.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6' // Added contrasting color
                    });
                }
            });
        });
    }

    // Event listener to validate file selection prior to form submission
    const importButton = document.getElementById('importButton');
    if (importButton) {
        importButton.addEventListener('click', function (e) {
            var fileInput = document.getElementById('dbFile');

            // Block form submission if no file is selected
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                // Ersetze das reguläre Alert mit SweetAlert
                Swal.fire({
                    icon: 'warning',
                    title: 'Keine Datei ausgewählt',
                    text: 'Bitte wählen Sie eine Datei aus.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6' // Added contrasting color
                });
            }
        });
    }

    var datenEintragenButton = document.getElementById('datenEintragenButton');
    datenEintragenButton.addEventListener('click', function () {
        var urlaubStart = document.getElementById('urlaubStart').value;
        var urlaubEnde = document.getElementById('urlaubEnde').value;
        var ereignistyp = document.querySelector('input[name="ereignistyp"]:checked').value;

        if (urlaubStart && urlaubEnde) {
            if (urlaubStart <= urlaubEnde) {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', 'save.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onload = function () {
                    if (this.status === 200) {
                        location.reload();
                    } else {
                        // Ersetze das reguläre Alert mit SweetAlert
                        Swal.fire({
                            icon: 'error',
                            title: 'Fehler',
                            text: 'Fehler beim Eintragen des Ereignisses.',
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#3085d6' // Added contrasting color
                        });
                    }
                };
                xhr.send("urlaubStart=" + encodeURIComponent(urlaubStart) + "&urlaubEnde=" + encodeURIComponent(urlaubEnde) + "&ereignistyp=" + encodeURIComponent(ereignistyp));
            } else {
                // Ersetze das reguläre Alert mit SweetAlert
                Swal.fire({
                    icon: 'warning',
                    title: 'Ungültiges Datum',
                    text: 'Das Startdatum muss vor dem Enddatum liegen.',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6' // Added contrasting color
                });
            }
        } else {
            // Ersetze das reguläre Alert mit SweetAlert
            Swal.fire({
                icon: 'warning',
                title: 'Unvollständige Informationen',
                text: 'Bitte füllen Sie beide Datumsfelder aus.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6' // Added contrasting color
            });
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

        // Get the selected location
        var standort = document.querySelector('select[name="standort"]').value;

        // Send an HTTP request to save.php with the startzeit and standort parameters
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "save.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (this.readyState == 4) {
                if (this.status === 200) {
                    // Upon success, save the returned lastId in local storage and refresh the page
                    var lastId = this.responseText;
                    localStorage.setItem('lastId', lastId);
                    location.reload();
                } else {
                    // Display error popup if start time submission fails
                    Swal.fire({
                        icon: 'error',
                        title: 'Fehler beim Starten der Arbeitszeit',
                        text: 'Bitte überprüfen Sie Ihre Eingaben.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6' // Added contrasting color
                    });
                }
            }
        };
        xhr.send("startzeit=" + encodeURIComponent(formattedDateTime) + "&standort=" + encodeURIComponent(standort));
    })


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
            if (this.readyState == 4) {
                if (this.status === 200) {
                    // Upon success, reload the page
                    location.reload();
                } else {
                    // Display error popup if end time submission fails
                    Swal.fire({
                        icon: 'error',
                        title: 'Fehler beim Beenden der Arbeitszeit',
                        text: 'Endzeit darf nicht vor der Startzeit liegen.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6' // Added contrasting color
                    });
                }
            }
        };
        xhr.send("endzeit=" + encodeURIComponent(formattedDateTime) +
            "&id=" + lastId +
            "&aktion=gehen" +
            "&pause=" + pauseManuell +
            "&standort=" + encodeURIComponent(standort) +
            "&beschreibung=" + encodeURIComponent(beschreibung));

        localStorage.removeItem('startzeit');
        updateTimer(false);
    });



    // Fade in the success alert
    $(".alert-success").fadeIn(1000);

    // First week notification handling with null checks
    const isFirstWeekInput = document.getElementById('isFirstWeek');
    const notificationContainer = document.getElementById('firstWeekNotification');
    
    if (isFirstWeekInput && notificationContainer) {
        const isFirstWeek = isFirstWeekInput.value === '1';
        
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
    }

    // Entfernen Sie die alten Event-Handler
    $('.table tbody').off('click');
    $('.table tbody').off('keypress');
    $('.table tbody').off('blur');

    // Fügen Sie Event Delegation hinzu, um Klicks auf .edit-datetime-btn zu behandeln
    $(document).on('click', '.edit-datetime-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const btn = this;
        const span = btn.previousElementSibling;
        const originalValue = span.dataset.value;

        try {
            const fp = flatpickr(span, {
                enableTime: true,
                time_24hr: true,
                dateFormat: "Y-m-d H:i",
                defaultDate: span.dataset.value,
                locale: "de",
                allowInput: false,
                // Aktualisiere den Server beim Schließen des Kalenders nur wenn sich das Datum geändert hat
                onClose: function(selectedDates, dateStr) {
                    if (dateStr !== originalValue && selectedDates.length > 0) {
                        // Fetch corresponding startzeit and endzeit
                        const row = $(span).closest('tr');
                        const startzeit = row.find('span[data-field="startzeit"]').data('value');
                        const endzeit = row.find('span[data-field="endzeit"]').data('value');

                        // Validate that endzeit is not before startzeit
                        if (span.dataset.field === 'startzeit' && endzeit && new Date(dateStr) > new Date(endzeit)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Ungültige Startzeit',
                                text: 'Startzeit darf nicht nach der Endzeit liegen.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#3085d6' // Added contrasting color
                            });
                            return;
                        }

                        if (span.dataset.field === 'endzeit' && startzeit && new Date(dateStr) < new Date(startzeit)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Ungültige Endzeit',
                                text: 'Endzeit darf nicht vor der Startzeit liegen.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#3085d6' // Added contrasting color
                            });
                            return;
                        }

                        $.post('save.php', {
                            update: true,
                            id: span.dataset.id,
                            column: span.dataset.field,
                            data: dateStr
                        }).done(function() {
                            location.reload();
                        }).fail(function(err) {
                            // Ersetze das reguläre Alert mit SweetAlert
                            Swal.fire({
                                icon: 'error',
                                title: 'Aktualisierungsfehler',
                                text: 'Fehler beim Aktualisieren der Daten.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#3085d6' // Added contrasting color
                            });
                        });
                    }
                }
            });

            fp.open();

        } catch (error) {
            // Optional: Verwende SweetAlert für Fehlerbehandlung
            Swal.fire({
                icon: 'error',
                title: 'Fehler',
                text: 'Ein unerwarteter Fehler ist aufgetreten.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6' // Added contrasting color
            });
        }
    });

    // Helper function to format duration
    function formatDuration(startDate, endDate, pauseMinutes) {
        if (!startDate || !endDate) return '-';
        const diff = endDate - startDate;
        const totalMinutes = Math.floor(diff / 60000) - pauseMinutes;
        if (totalMinutes <= 0) return '00:00';
        const hours = Math.floor(totalMinutes / 60);
        const minutes = totalMinutes % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    }

    // Helper function to format date for display
    function formatDateForDisplay(date) {
        return date.toLocaleString('de-DE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // Replace the existing event handler for edit-datetime-btn with this updated version
    $(document).on('click', '.edit-datetime-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const btn = this;
        const span = btn.previousElementSibling;
        const originalValue = span.dataset.value;
        const row = $(span).closest('tr');

        try {
            const fp = flatpickr(span, {
                enableTime: true,
                time_24hr: true,
                dateFormat: "Y-m-d H:i",
                defaultDate: span.dataset.value,
                locale: "de",
                allowInput: false,
                onClose: function(selectedDates, dateStr) {
                    if (dateStr !== originalValue && selectedDates.length > 0) {
                        // Get current values from the row
                        const startzeitSpan = row.find('span[data-field="startzeit"]');
                        const endzeitSpan = row.find('span[data-field="endzeit"]');
                        const pauseInput = row.find('input[name="pause"]');
                        
                        const startzeit = span.dataset.field === 'startzeit' ? dateStr : startzeitSpan.data('value');
                        const endzeit = span.dataset.field === 'endzeit' ? dateStr : endzeitSpan.data('value');
                        const pause = parseInt(pauseInput.val()) || 0;

                        // Validate times
                        if (new Date(endzeit) < new Date(startzeit)) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Ungültige Zeit',
                                text: span.dataset.field === 'startzeit' ? 
                                    'Startzeit darf nicht nach der Endzeit liegen.' : 
                                    'Endzeit darf nicht vor der Startzeit liegen.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#3085d6'
                            });
                            return;
                        }

                        // Update the server
                        $.post('save.php', {
                            update: true,
                            id: span.dataset.id,
                            column: span.dataset.field,
                            data: dateStr
                        })
                        .done(function(response) {
                            // Update the display value and data attribute
                            const displayDate = new Date(dateStr);
                            span.textContent = formatDateForDisplay(displayDate);
                            span.dataset.value = dateStr;

                            // Update the duration cell
                            const duration = formatDuration(
                                new Date(startzeit),
                                new Date(endzeit),
                                pause
                            );
                            const durationCell = row.find('td').eq(4); // Assuming duration is the 5th column
                            durationCell.text(duration);

                            // Add a brief highlight effect
                            durationCell.addClass('highlight')
                                .delay(1000)
                                .queue(function(next) {
                                    $(this).removeClass('highlight');
                                    next();
                                });
                        })
                        .fail(function(err) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Aktualisierungsfehler',
                                text: 'Fehler beim Aktualisieren der Daten.',
                                confirmButtonText: 'OK',
                                confirmButtonColor: '#3085d6'
                            });
                        });
                    }
                    fp.destroy();
                }
            });

            fp.open();

        } catch (error) {
            Swal.fire({
                icon: 'error',
                title: 'Fehler',
                text: 'Ein unerwarteter Fehler ist aufgetreten.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6'
            });
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
            // Ersetze das reguläre Alert mit SweetAlert
            Swal.fire({
                icon: 'warning',
                title: 'Ungültige Zeit',
                text: 'Endzeit sollte nach der Startzeit liegen.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6' // Added contrasting color
            });
        } else if (pauseVal < 0) {
            e.preventDefault();
            // Ersetze das reguläre Alert mit SweetAlert
            Swal.fire({
                icon: 'warning',
                title: 'Ungültige Pause',
                text: 'Pause sollte nicht negativ sein.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6' // Added contrasting color
            });
        }

        // Include Standort and Beschreibung in the form submission
        var standort = $('select[name="standort"]').val();
        var beschreibung = $('textarea[name="beschreibung"]').val();
        $('<input>').attr({
            type: 'hidden',
            name: 'standort',
            value: standort
        }).appendTo('#mainForm');
        $('<input>').attr({
            type: 'hidden',
            name: 'beschreibung',
            value: beschreibung
        }).appendTo('#mainForm');

        localStorage.removeItem('startzeit');
        localStorage.removeItem('elapsedPauseInSeconds');
    });

    // Event Listener für das Verlassen des Pausenfelds - Neuer Ajax-Ansatz
    $(document).on('blur', 'input[name="pause"]', function() {
        const input = $(this);
        const newValue = parseInt(input.val(), 10);
        const oldValue = parseInt(input.data('old-value'), 10);
        const id = input.data('id');

        // Wenn sich der Wert nicht geändert hat, nichts tun
        if (newValue === oldValue) {
            return;
        }

        // Fetch startzeit and endzeit from the DOM
        const row = input.closest('tr');
        const startzeitSpan = row.find('span[data-field="startzeit"]');
        const endzeitSpan = row.find('span[data-field="endzeit"]');

        const startzeit = startzeitSpan.data('value');
        const endzeit = endzeitSpan.data('value');

        // Calculate new duration if endzeit is set
        let newDuration = '-';
        if (endzeit) {
            const start = new Date(startzeit);
            const end = new Date(endzeit);
            const pauseMinutes = newValue;

            // Calculate total minutes worked
            const totalMinutes = Math.floor((end - start) / 60000) - pauseMinutes;
            if (totalMinutes > 0) {
                const hours = Math.floor(totalMinutes / 60);
                const minutes = totalMinutes % 60;
                newDuration = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
            } else {
                newDuration = '00:00';
            }
        }

        // Update the duration cell
        const durationCell = row.find('td').eq(4); // Assuming duration is the 5th column
        durationCell.text(newDuration);

        // Send the AJAX request to update the pause
        fetch('save.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `update=true&id=${id}&column=pause&data=${newValue}`
        })
        .then(response => {
            if (response.ok) {
                // Update the row's values
                input.val(newValue).data('old-value', newValue);
                
                // Recalculate daily summary
                const currentRow = input.closest('tr');
                const sameDate = currentRow.nextUntil('.bg-base-200').addBack();
                const summaryRow = sameDate.last().next('.bg-base-200');
                
                if (summaryRow.length) {
                    let totalMinutes = 0;
                    let totalPause = 0;
                    
                    sameDate.each(function() {
                        const duration = $(this).find('td').eq(4).text().split(':');
                        if (duration.length === 2) {
                            totalMinutes += parseInt(duration[0]) * 60 + parseInt(duration[1]);
                        }
                        totalPause += parseInt($(this).find('input[name="pause"]').val() || 0);
                    });
                    
                    const hours = Math.floor(totalMinutes / 60);
                    const minutes = totalMinutes % 60;
                    summaryRow.find('td').eq(3).text(
                        `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
                    );
                    summaryRow.find('td').eq(4).text(totalPause);
                }
                
                // Visual feedback
                input.addClass('highlight')
                     .delay(1000)
                     .queue(function(next) {
                         $(this).removeClass('highlight');
                         next();
                     });
            } else {
                return response.text().then(text => { throw new Error(text); });
            }
        })
        .catch(error => {
            // Bei Fehler: Alten Wert wiederherstellen und Fehlermeldung zeigen
            input.val(oldValue);
            Swal.fire({
                icon: 'warning',
                title: 'Ungültige Pause',
                text: error.message,
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6' // Added contrasting color
            });
        });
    });

    $(document).on('keypress', 'input[name="pause"]', function(e) {
        if (e.which === 13) { // Enter-Taste
            e.preventDefault();
            $(this).blur(); 
        }
    });

    // Speichern der Originalwerte beim Laden
    $('input[name="pause"]').each(function() {
        $(this).attr('data-original-value', $(this).val());
    });

    // Initialisiere alte Pausenwerte beim Laden der Seite
    $(document).ready(function() {
        $('input[name="pause"]').each(function() {
            $(this).data('old-value', $(this).val());
        });
    });

    // Enhanced click handler for summary rows
    $(document).on('click', '.summary-row', function() {
        const workDate = $(this).data('date');
        const detailsRow = $(`.details-row[data-date="${workDate}"]`);
        const chevron = $(this).find('.fa-chevron-right');
        
        // Smooth animation for details
        detailsRow.slideToggle({
            duration: 200,
            start: function() {
                chevron.toggleClass('rotate-90');
            }
        });
        
        // Save state to localStorage
        const expanded = !detailsRow.is(':visible'); // Toggle the current state
        const expandedDates = JSON.parse(localStorage.getItem('expandedDates') || '{}');
        expandedDates[workDate] = expanded;
        localStorage.setItem('expandedDates', JSON.stringify(expandedDates));
    });

    // Add keyboard navigation
    $(document).on('keydown', '.summary-row', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            $(this).click();
        }
    });

    // Add loading state
    function showLoading() {
        const overlay = $('<div class="loading-overlay"><div class="loading loading-spinner loading-lg"></div></div>');
        $('#timeRecordsTable').append(overlay);
    }

    function hideLoading() {
        $('.loading-overlay').remove();
    }

    // Enhance updateTimeRecordsTable with loading state
    function updateTimeRecordsTable(page = 1) {
        showLoading();
        fetch(`get_time_records.php?page=${page}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('timeRecordsContainer').innerHTML = data;
                attachEventListeners();
                updateTimeRecordsView();
                restoreExpandedState();
            })
            .catch((error) => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating time records table',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6'
                });
            })
            .finally(() => {
                hideLoading();
            });
    }

    // Restore expanded state on page load
    function restoreExpandedState() {
        const expandedDates = JSON.parse(localStorage.getItem('expandedDates') || '{}');
        Object.entries(expandedDates).forEach(([date, expanded]) => {
            if (expanded) {
                const summaryRow = $(`.summary-row[data-date="${date}"]`);
                const detailsRow = $(`.details-row[data-date="${date}"]`);
                const chevron = summaryRow.find('.fa-chevron-right');
                
                detailsRow.show();
                chevron.addClass('rotate-90');
            }
        });
    }

    // Call restore function when table is updated
    function updateTimeRecordsTable(page = 1) {
        fetch(`get_time_records.php?page=${page}`)
            .then(response => response.text())
            .then(data => {
                document.getElementById('timeRecordsContainer').innerHTML = data;
                attachEventListeners();
                updateTimeRecordsView();
                restoreExpandedState(); // Restore expanded state after table update
            })
            .catch((error) => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Error updating time records table',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#3085d6'
                });
            });
    }

    // Initial restore on page load
    restoreExpandedState();

    // Funktion zum Aktualisieren von Standort
    function updateStandort(recordId, newStandort) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "save.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (this.readyState == 4) {
                if (this.status === 200) {
                    console.log('Standort erfolgreich aktualisiert.');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Fehler',
                        text: 'Fehler beim Aktualisieren des Standorts.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    });
                }
            }
        };
        xhr.send("id=" + encodeURIComponent(recordId) + "&column=standort&data=" + encodeURIComponent(newStandort));
    }

    // Funktion zum Aktualisieren von Beschreibung
    function updateBeschreibung(recordId, newBeschreibung) {
        var xhr = new XMLHttpRequest();
        xhr.open("POST", "save.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function () {
            if (this.readyState == 4) {
                if (this.status === 200) {
                    console.log('Beschreibung erfolgreich aktualisiert.');
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Fehler',
                        text: 'Fehler beim Aktualisieren der Beschreibung.',
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#3085d6'
                    });
                }
            }
        };
        xhr.send("id=" + encodeURIComponent(recordId) + "&column=beschreibung&data=" + encodeURIComponent(newBeschreibung));
    }

    // Ereignis-Listener für Standort-Änderungen
    document.querySelectorAll('select[name="standort"]').forEach(function(select) {
        select.addEventListener('change', function() {
            var recordId = this.dataset.id; // Stellen Sie sicher, dass data-id gesetzt ist
            var newStandort = this.value;
            updateStandort(recordId, newStandort);
        });
    });

    // Ereignis-Listener für Beschreibung-Änderungen
    document.querySelectorAll('textarea[name="beschreibung"]').forEach(function(textarea) {
        textarea.addEventListener('blur', function() {
            var recordId = this.dataset.id; // Stellen Sie sicher, dass data-id gesetzt ist
            var newBeschreibung = this.value;
            updateBeschreibung(recordId, newBeschreibung);
        });
    });

    // Event-Listener für Standort und Beschreibung Änderungen
    $(document).on('change', 'select[data-field="standort"], input[data-field="beschreibung"]', function() {
        const input = $(this);
        const id = input.data('id');
        const field = input.data('field');
        const value = input.val();

        // Send the AJAX request to update the field
        $.post('save.php', {
            update: true,
            id: id,
            column: field,
            data: value
        })
        .fail(function(err) {
            Swal.fire({
                icon: 'error',
                title: 'Aktualisierungsfehler',
                text: 'Fehler beim Aktualisieren der Daten.',
                confirmButtonText: 'OK',
                confirmButtonColor: '#3085d6'
            });
        });
    });

    $(document).on('change', '#dateRange', function() {
        // ...existing date range handling...

        // Hide event selection fields
        $('.event-selection-fields').hide();
    });

    // Optionally, ensure no other scripts are showing the event selection fields
    $(document).ready(function() {
        $('.event-selection-fields').hide();
    });

    function updateSettingsVisibility() {
        var settingsDropdown = document.getElementById('settingsDropdown');
        if (settingsDropdown) { // Nullprüfung hinzugefügt
            if (window.innerWidth < 1024) {
                settingsDropdown.style.display = 'none';
            } else {
                settingsDropdown.style.display = 'block';
            }
        }
    }

    // Add timer persistence across browser tabs
    // Function to format elapsed time as HH:MM:SS
    function formatElapsedTime(elapsed) {
        const hours = Math.floor(elapsed / (1000 * 60 * 60));
        const minutes = Math.floor((elapsed % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((elapsed % (1000 * 60)) / 1000);
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }

    // Declare a variable to hold the interval ID
    let timerInterval;

    // Function to update the timer display and browser tab title
    function updateTimer(startInterval = true) {
        const startZeit = localStorage.getItem('startzeit');
        if (startZeit) {
            const startTime = new Date(startZeit);
            const now = new Date();
            const elapsed = now - startTime;

            const formattedTime = formatElapsedTime(elapsed);
            $('#timer').text(formattedTime);
            document.title = `Quodara Chrono: ${formattedTime}`;

            if (startInterval && !timerInterval) {
                timerInterval = setInterval(updateTimer, 1000);
            }
        } else {
            $('#timer').text('00:00:00');
            document.title = 'Quodara Chrono';
            // Clear the interval if it exists
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
        }
    }

    // Initialize timer display
    updateTimer();

    // Remove the existing setInterval call
    // setInterval(updateTimer, 1000);

    // Ensure the timer stops and resets when "gehen" is clicked
    endButton.addEventListener('click', function () {
        // ...existing code...

        // Remove 'startzeit' from localStorage to stop the timer
        localStorage.removeItem('startzeit');

        // Clear the timer interval and reset the timer display
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        $('#timer').text('00:00:00');
        document.title = 'Quodara Chrono';

        // ...existing code...
    });

    // Listen for changes in localStorage to update timer across all tabs
    window.addEventListener('storage', function(e) {
        if (e.key === 'startzeit' || e.key === 'endzeit') {
            updateTimer();
        }
    });

});

