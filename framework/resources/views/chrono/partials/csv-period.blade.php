<details class="card mt-4">
    <summary class="card-body cursor-pointer font-semibold">{{ $copy->language === 'de' ? 'CSV herunterladen' : $copy->text('CSV_PERIOD_TITLE') }}</summary>
    <div class="card-body pt-0">
        <form method="get" action="export_csv.php" target="_blank" rel="noopener noreferrer" class="row g-3 align-items-end">
            <input type="hidden" name="period" value="1">
            <div class="col-sm-6 col-md-4"><label for="csv-period-from" class="form-label">{{ $copy->language === 'de' ? 'Von' : $copy->text('CSV_PERIOD_FROM') }}</label><input id="csv-period-from" name="from" type="date" min="0001-01-01" max="9999-12-31" required class="form-control"></div>
            <div class="col-sm-6 col-md-4"><label for="csv-period-to" class="form-label">{{ $copy->language === 'de' ? 'Bis' : $copy->text('CSV_PERIOD_TO') }}</label><input id="csv-period-to" name="to" type="date" min="0001-01-01" max="9999-12-31" required class="form-control"></div>
            <div class="col-sm-auto"><button class="btn btn-outline-secondary" type="submit">{{ $copy->language === 'de' ? 'Herunterladen' : $copy->text('CSV_PERIOD_SUBMIT') }}</button></div>
        </form>
        <div class="mt-3"><x-chrono-help><p>{{ $copy->text('CSV_PERIOD_NOTE') }}</p><p>{{ $copy->text('CSV_EXPORT_NEW_CONTEXT_HINT') }}</p></x-chrono-help></div>
    </div>
</details>
