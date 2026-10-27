{{-- Native get_time_records.php fragment; all prepared values are escaped here. --}}
<div class="flex justify-between items-center mb-4">
    <h2 class="text-xl font-bold"><i class="fas fa-clock mr-2"></i>{{ $records->copy->text('TABLE_HEADER_TIME_RECORDS') }}</h2>
    <button disabled aria-label="{{ $records->copy->text('BUTTON_EXPORT_CSV') }}" title="CSV-Export ist in dieser Vorschau noch nicht verbunden."
            class="btn btn-outline btn-success btn-sm gap-2 hover:scale-105 transform transition-transform duration-200">
        <i class="fas fa-file-csv text-lg"></i>
        <span class="hidden sm:inline">{{ $records->copy->text('BUTTON_EXPORT_CSV') }}</span>
    </button>
</div>
<div id="timeRecordsTable" class="card bg-base-100 shadow-xl">
    <div class="card-body p-6">
        <h3 class="card-title text-2xl mb-6 flex items-center gap-3">
            <i class="fas fa-clock text-primary"></i>
            Arbeitszeiten und Abwesenheitsgutschriften
        </h3>
        <div class="overflow-x-auto rounded-lg border border-base-200">
            <table class="table w-full">
                <thead class="bg-base-200">
                    <tr>
                        <th class="text-left px-6 py-4 font-semibold text-sm uppercase tracking-wider w-48">Datum</th>
                        <th class="text-center px-6 py-4 font-semibold text-sm uppercase tracking-wider w-32">{{ $records->copy->text('TABLE_HEADER_DURATION') }}</th>
                        <th class="text-center px-6 py-4 font-semibold text-sm uppercase tracking-wider w-32">{{ $records->copy->text('TABLE_HEADER_BREAK') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-base-200">
                @foreach ($records->groups as $group)
                    <tr class="summary-row hover:bg-base-200/50 cursor-pointer transition-colors" data-date="{{ $group['work_date'] }}">
                        <td class="px-6 py-4 w-48">
                            <div class="flex items-center gap-3">
                                <div class="w-6 flex justify-center transition-transform duration-200">
                                    <i class="fas fa-chevron-right text-primary"></i>
                                </div>
                                <div class="flex items-center gap-2">
                                    <div class="font-medium">{{ $group['formatted_date'] }}</div>
                                    <div class="badge badge-sm badge-ghost" title="Anzahl der Einträge">{{ $group['entry_count'] }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 w-32">
                            <div class="flex items-center justify-center gap-2">
                                <i class="fas fa-clock text-primary/75"></i>
                                <span class="font-medium">{{ $group['total_duration'] }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 w-32">
                            <div class="flex items-center justify-center gap-2">
                                <i class="fas fa-pause text-primary/75"></i>
                                <span class="font-medium">{{ $group['total_pause'] }} min</span>
                            </div>
                        </td>
                        <td></td>
                    </tr>
                    <tr class="details-row bg-base-100/50" data-date="{{ $group['work_date'] }}" style="display: none;">
                        <td colspan="4" class="p-0">
                            <div class="pl-14 pr-6 py-4 border-l-4 border-primary/10">
                                <table class="table w-full">
                                    <thead class="bg-base-200/50">
                                        <tr>
                                            <th class="text-left px-4 py-3 text-sm font-medium">{{ $records->copy->text('TABLE_HEADER_WEEK') }}</th>
                                            <th class="text-left px-4 py-3 text-sm font-medium">{{ $records->copy->text('TABLE_HEADER_START_TIME') }}</th>
                                            <th class="text-left px-4 py-3 text-sm font-medium">{{ $records->copy->text('TABLE_HEADER_END_TIME') }}</th>
                                            <th class="text-left px-4 py-3 text-sm font-medium">{{ $records->copy->text('TABLE_HEADER_DURATION') }}</th>
                                            <th class="text-left px-4 py-3 text-sm font-medium">{{ $records->copy->text('TABLE_HEADER_BREAK') }}</th>
                                            <th class="text-left px-4 py-3 text-sm font-medium">{{ $records->copy->text('TABLE_HEADER_LOCATION') }}</th>
                                            <th class="text-left px-4 py-3 text-sm font-medium">{{ $records->copy->text('TABLE_HEADER_COMMENT') }}</th>
                                            <th class="text-left px-4 py-3 text-sm font-medium w-12">{{ $records->copy->text('TABLE_HEADER_ACTIONS') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-base-200/50">
                                    @foreach ($group['records'] as $row)
                                        <tr><td>{{ $row['week_number'] }}</td><td>{{ $row['start_label'] }}</td><td>{{ $row['end_label'] }}</td><td>{{ $row['duration_label'] }}</td><td>{{ $row['pause'] }}</td><td>{{ $row['location'] }}</td><td>{{ $row['comment'] }}</td><td><a class="link link-primary" href="adjustments.php?record_id={{ $row['id'] }}">Korrektur / Storno</a></td></tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex justify-center mt-4">
            <div class="btn-group">
                @if ($records->page > 1)
                    <button onclick="updateTimeRecordsTable(1)" class="btn">«</button>
                    <button onclick="updateTimeRecordsTable({{ $records->page - 1 }})" class="btn">‹</button>
                @endif
                @for ($i = max(1, $records->page - 2); $i <= min($records->totalPages, $records->page + 2); $i++)
                    <button onclick="updateTimeRecordsTable({{ $i }})" class="btn {{ $i === $records->page ? 'btn-active' : '' }}">{{ $i }}</button>
                @endfor
                @if ($records->page < $records->totalPages)
                    <button onclick="updateTimeRecordsTable({{ $records->page + 1 }})" class="btn">›</button>
                    <button onclick="updateTimeRecordsTable({{ $records->totalPages }})" class="btn">»</button>
                @endif
            </div>
        </div>
    </div>
</div>
