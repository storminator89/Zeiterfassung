<details class="card bg-base-100 shadow-xl p-4 mb-4">
    <summary class="cursor-pointer font-semibold">{{ $copy->text('CSV_PERIOD_TITLE') }}</summary>
    <form method="get" action="export_csv.php" target="_blank" rel="noopener noreferrer" class="grid sm:grid-cols-3 gap-3 mt-4 items-end">
        <input type="hidden" name="period" value="1">
        <div><label for="csv-period-from" class="block text-sm font-semibold mb-1">{{ $copy->text('CSV_PERIOD_FROM') }}</label><input id="csv-period-from" name="from" type="date" min="0001-01-01" max="9999-12-31" required class="input input-bordered w-full"></div>
        <div><label for="csv-period-to" class="block text-sm font-semibold mb-1">{{ $copy->text('CSV_PERIOD_TO') }}</label><input id="csv-period-to" name="to" type="date" min="0001-01-01" max="9999-12-31" required class="input input-bordered w-full"></div>
        <button class="btn btn-outline btn-success" type="submit">{{ $copy->text('CSV_PERIOD_SUBMIT') }}</button>
    </form>
    <x-chrono-help><p class="text-sm opacity-70 mt-3">{{ $copy->text('CSV_PERIOD_NOTE') }}</p>
<p class="text-sm opacity-70 mt-2">{{ $copy->text('CSV_EXPORT_NEW_CONTEXT_HINT') }}</p></x-chrono-help>
</details>
