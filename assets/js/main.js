$(function() {

    $(".main-title").on("click", function() {
      $(this).next(".toggle-content").slideToggle();
      
      $(this).find('i.fas').toggleClass('fa-chevron-down fa-chevron-up');
    });
  
    function updateDateTimeField() {
      $("#endzeit").val(new Date().toISOString().slice(0, 16)); 
    }
  
    updateDateTimeField();
    setInterval(updateDateTimeField, 60000);
  
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
  
    $('.table').DataTable({
      dom: 'Bfrtip',
      buttons,
      language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json"
      }
    });
  
    $('.table tbody').on('dblclick', 'td', function() {
      let $cell = $(this);
      let col = $cell.closest('table').DataTable().cell($cell).index().column;
  
      if ($cell.closest('table').find('th').eq(col).data('name') !== "dauer") {
        let html = $cell.html();
        $cell.html(`<input type="text" value="${html}"/>`);
        $cell.find('input').focus(); 
      }
    });
  
    $('.table tbody').on('blur', 'td input', function() {
      let $input = $(this);
      let cell = $input.closest('table').DataTable().cell($input.parent());
      let col = cell.index().column;
      let newVal = $input.val();
      let id = $input.closest('tr').find('input[name="id"]').val();
  
      cell.data(newVal).draw();
      
      $.post('save.php', {
        update: true,
        id,
        column: $input.closest('table').find('th').eq(col).data('name'),  
        data: newVal
      });
    });
  
    $('select[name="beschreibung"]').change(function() {
      let desc = $(this).val();
  
      if (["Feiertag", "Krankheit"].includes(desc)) {
        let date = $('input[name="startzeit"]').val().split('T')[0];
        $('input[name="startzeit"]').val(`${date}T09:00`); 
        $('input[name="endzeit"]').val(`${date}T17:00`);
      }
    });
  
    let pauseBtn = $('#pauseButton');
    let pauseDisplay = $('#pauseDisplay');
    let startTime = parseInt(localStorage.getItem('startTime')) || 0;
    let elapsedPause = parseInt(localStorage.getItem('elapsedPauseInSeconds')) || 0;
    let interval;
  
    function getLocalISOTime() {
      let offset = new Date().getTimezoneOffset() * 60 * 1000; 
      return new Date(Date.now() - offset).toISOString().slice(0, 16);
    }
  
    let startzeitField = $('input[name="startzeit"]');
    let savedStartzeit = localStorage.getItem('startzeit');
    startzeitField.val(savedStartzeit || getLocalISOTime());
  
    if (!savedStartzeit) {
      localStorage.setItem('startzeit', getLocalISOTime());
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
  
    pauseBtn.click(function() {
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
  
    $('#mainForm').on('submit', function(e) {
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
  
    });
  
  }