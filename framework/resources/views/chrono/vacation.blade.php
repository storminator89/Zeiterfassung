@extends('chrono.layouts.app')

@section('content')
<link rel="stylesheet" href="assets/css/vacation_ui.css?v={{ substr(hash_file('sha256', public_path('assets/css/vacation_ui.css')),0,12) }}">
<main class="container mx-auto px-4 py-8 chrono-page chrono-vacation">
    @php($vacationRequestDraft = $vacationError && request()->isMethod('POST') && request()->input('action') === 'request')
    @php($vacationDraftReason = $vacationError && request()->isMethod('POST') && is_string(request()->input('reason')) && strlen(request()->input('reason')) <= 2000 ? request()->input('reason') : '')
    <header class="chrono-vacation-heading"><h1>{{ $vacationCopy->text('title') }}</h1><img src="assets/illustrations/calendar-plan.png" alt="" width="104" height="88"></header>
    @if ($vacationError)<div class="alert alert-danger my-4" role="alert">{{ $vacationError }}</div>@endif
    @if ($vacation !== null)
    @if ($vacation['scope']==='own')
    <section id="vacation-create" class="chrono-vacation-compose" aria-labelledby="vacation-create-title">
        <header><h2 id="vacation-create-title"><x-chrono-icon name="plane" size="20" /> {{ $vacationCopy->text('new') }}</h2><x-chrono-help label="Urlaubsantrag"><p>{{ $vacationCopy->text('intro') }}</p><p>{{ $vacationCopy->text('reason_privacy') }}</p></x-chrono-help></header>
        <form method="post" action="vacation.php?scope=own&amp;state=all&amp;page=1">
            @csrf
            <input type="hidden" name="action" value="request"><input type="hidden" name="command_id" value="{{ bin2hex(random_bytes(16)) }}">
            <div class="chrono-vacation-date-pair">
                <div><label class="form-label" for="vacation-start-date">{{ $vacationCopy->text('from') }}</label><input type="date" id="vacation-start-date" name="start_date" value="{{ $vacationRequestDraft && is_string(request()->input('start_date')) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D',request()->input('start_date')) ? request()->input('start_date') : '' }}" required class="form-control"></div>
                <div><label class="form-label" for="vacation-end-date">{{ $vacationCopy->text('through') }}</label><input type="date" id="vacation-end-date" name="end_date" value="{{ $vacationRequestDraft && is_string(request()->input('end_date')) && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D',request()->input('end_date')) ? request()->input('end_date') : '' }}" required class="form-control"></div>
            </div>
            <div class="chrono-vacation-reason"><label class="form-label" for="vacation-reason">{{ $vacationCopy->text('reason') }}</label><textarea id="vacation-reason" name="reason" required maxlength="2000" rows="2" class="form-control">{{ $vacationRequestDraft ? $vacationDraftReason : '' }}</textarea></div>
            <footer><a href="attendance.php">{{ $vacationCopy->text('back') }}</a><button type="submit" class="btn btn-primary">{{ $vacationCopy->text('submit') }}</button></footer>
        </form>
    </section>
    @else
    <div class="chrono-vacation-own-link"><a href="vacation.php?scope=own&amp;state=all#vacation-create" class="btn btn-outline-secondary"><x-chrono-icon name="plus" size="18" /> {{ $vacationCopy->text('new') }}</a></div>
    @endif

    <section class="chrono-vacation-ledger" aria-labelledby="vacation-list-title">
        <header class="chrono-vacation-toolbar">
            <nav aria-label="{{ $vacationCopy->text('title') }}" class="chrono-vacation-tabs">
                <a id="vacation-list-title" class="{{ $vacation['scope']==='own' ? 'active' : '' }}" href="vacation.php?scope=own&amp;state=all" @if ($vacation['scope']==='own') aria-current="page" @endif>{{ $vacationCopy->text('own') }}</a>
                @if ($vacation['can_review'])<a class="{{ $vacation['scope']==='review' ? 'active' : '' }}" href="vacation.php?scope=review&amp;state=pending" @if ($vacation['scope']==='review') aria-current="page" @endif>{{ $vacationCopy->text('review') }}</a>@endif
            </nav>
            <form method="get" action="vacation.php" class="chrono-vacation-filter">
                <input type="hidden" name="scope" value="{{ $vacation['scope'] }}">
                <label class="visually-hidden" for="vacation-state">{{ $vacationCopy->text('state') }}</label><select id="vacation-state" name="state" class="form-select">
                    <option value="all" @selected($vacation['state']==='all')>{{ $vacationCopy->text('all') }}</option>
                    @foreach (['pending','approved','rejected','withdrawn','cancel_pending','cancelled'] as $state)<option value="{{ $state }}" @selected($vacation['state']===$state)>{{ $vacationCopy->state($state) }}</option>@endforeach
                </select>
                <button class="btn btn-outline-secondary" type="submit">{{ $vacationCopy->text('filter') }}</button>
            </form>
        </header>
        @if ($vacation['rows']===[])<div class="chrono-vacation-empty"><x-chrono-icon name="calendar-off" size="32" /><p role="status">{{ $vacationCopy->text('empty') }}</p></div>@endif
        @foreach ($vacation['rows'] as $request)
        @php($base='vacation.php?'.http_build_query(['scope'=>$vacation['scope'],'state'=>$vacation['state'],'page'=>$vacation['page']]))
        @php($requestReason = collect($request['history'])->firstWhere('action', 'request')['reason'] ?? null)
        <article class="chrono-vacation-request" id="vacation-{{ $request['id'] }}">
            <header class="chrono-vacation-request-heading">
                <div>
                    @if ($vacation['scope']==='review')<h2>{{ $request['username'] }}</h2>@endif
                    <p class="chrono-vacation-range"><x-chrono-icon name="calendar" size="20" /><time datetime="{{ $request['start_date'] }}">{{ (new \DateTimeImmutable($request['start_date']))->format('d.m.Y') }}</time>@if ($request['end_date'] !== $request['start_date'])<span>{{ $vacationCopy->text('range_until') }}</span><time datetime="{{ $request['end_date'] }}">{{ (new \DateTimeImmutable($request['end_date']))->format('d.m.Y') }}</time>@endif</p>
                </div>
                <span class="chrono-vacation-state" data-vacation-state="{{ $request['state'] }}">{{ $vacationCopy->state($request['state']) }}</span>
            </header>
            @if ($requestReason !== null)<p class="chrono-vacation-request-reason">{{ $requestReason }}</p>@endif
            @php($actions=[])
            @if ($vacation['scope']==='own' && $request['state']==='pending')@php($actions=['withdraw'])@endif
            @if ($vacation['scope']==='own' && $request['state']==='approved')@php($actions=['request_cancel'])@endif
            @if ($vacation['scope']==='review' && $request['state']==='pending')
                @php($actions=['reject'])
                @if ($vacation['approval_request_id']===$request['id'] && $vacation['approval']!==null)
                <section class="chrono-vacation-preview" aria-label="{{ $vacationCopy->text('preview') }}">
                    <header><h3>{{ $vacationCopy->text('preview') }}</h3><x-chrono-help><p>{{ $vacationCopy->text('preview_note') }}</p></x-chrono-help></header>
                    <dl><div><dt>{{ $vacationCopy->text('credit_days') }}</dt><dd>{{ $vacation['approval']['credit_days'] }}</dd></div><div><dt>{{ $vacationCopy->text('credit_hours') }}</dt><dd>{{ sprintf('%02d:%02d',intdiv($vacation['approval']['total_minutes'],60),$vacation['approval']['total_minutes']%60) }} h</dd></div></dl>
                    <details class="chrono-vacation-credit-details"><summary>{{ $vacationCopy->text('preview_days') }}</summary><div class="table-responsive"><table class="table table-vcenter"><thead><tr><th>{{ $vacationCopy->text('date') }}</th><th class="text-end">{{ $vacationCopy->text('credit_hours') }}</th></tr></thead><tbody>@foreach ($vacation['approval']['days'] as $day)<tr><td>{{ (new \DateTimeImmutable($day['day']))->format('d.m.Y') }}</td><td class="text-end">{{ sprintf('%02d:%02d',intdiv($day['minutes'],60),$day['minutes']%60) }} h</td></tr>@endforeach</tbody></table></div></details>
                    @if ($vacation['approval']['approvable'])@php($actions=['approve','reject'])@else<p class="text-warning" role="status">{{ $vacationCopy->text('no_credit') }}</p>@endif
                </section>
                @endif
            @endif
            @if ($vacation['scope']==='review' && $request['state']==='cancel_pending')@php($actions=['approve_cancel','reject_cancel'])@endif
            <div class="chrono-vacation-request-actions">
                @if ($vacation['scope']==='review' && $request['state']==='pending')<a class="{{ $vacation['approval_request_id']===$request['id'] ? 'chrono-vacation-refresh' : 'btn btn-outline-secondary' }}" href="{{ $base.'&preview='.$request['id'].'#vacation-'.$request['id'] }}">{{ $vacationCopy->text($vacation['approval_request_id']===$request['id'] ? 'refresh' : 'preview') }}</a>@endif
                @foreach ($actions as $action)
                @php($dialogId='vacation-action-'.$request['id'].'-'.$action)
                <a class="btn {{ in_array($action,['approve','approve_cancel'],true) ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ $base.($action==='approve' ? '&preview='.$request['id'] : '').'&decision='.$action.'&decision_request='.$request['id'].'#'.$dialogId }}" data-vacation-dialog="{{ $dialogId }}">{{ $vacationCopy->action($action) }}</a>
                @endforeach
                @if (in_array($request['state'],['approved','cancel_pending'],true))<x-chrono-help><p>{{ $vacationCopy->text('cancel_note') }}</p></x-chrono-help>@endif
            </div>
            @foreach ($actions as $action)
            @php($dialogId='vacation-action-'.$request['id'].'-'.$action)
            <dialog id="{{ $dialogId }}" class="chrono-vacation-dialog" aria-labelledby="{{ $dialogId }}-title" @if ((request()->query('decision') === $action && request()->query('decision_request') === (string)$request['id']) || ($vacationError && request()->input('action') === $action && request()->input('request_id') === (string)$request['id'])) open data-vacation-open @endif>
                <header><h2 id="{{ $dialogId }}-title">{{ $vacationCopy->action($action) }}</h2><a href="{{ $base }}" class="btn btn-icon btn-ghost-secondary" data-vacation-close aria-label="{{ $vacationCopy->text('cancel') }}">×</a></header>
                <p class="chrono-vacation-dialog-period">{{ (new \DateTimeImmutable($request['start_date']))->format('d.m.Y') }} {{ $vacationCopy->text('range_until') }} {{ (new \DateTimeImmutable($request['end_date']))->format('d.m.Y') }}@if ($vacation['scope']==='review'), {{ $request['username'] }}@endif</p>
                <form method="post" action="{{ $base }}">
                    @csrf
                    <input type="hidden" name="action" value="{{ $action }}"><input type="hidden" name="request_id" value="{{ $request['id'] }}"><input type="hidden" name="revision" value="{{ $request['revision'] }}"><input type="hidden" name="command_id" value="{{ bin2hex(random_bytes(16)) }}">
                    @if ($action==='approve')<input type="hidden" name="plan_hash" value="{{ $vacation['approval']['plan_hash'] }}">@endif
                    <label class="form-label" for="{{ $dialogId }}-reason">{{ $vacationCopy->text('reason') }}</label><textarea id="{{ $dialogId }}-reason" name="reason" required maxlength="2000" rows="3" class="form-control">{{ $vacationError && request()->input('action') === $action && request()->input('request_id') === (string)$request['id'] ? $vacationDraftReason : '' }}</textarea>
                    <footer><x-chrono-help><p>{{ $vacationCopy->text('reason_privacy') }}</p>@if ($action==='approve')<p>{{ $vacationCopy->text('preview_note') }}</p>@endif @if (in_array($action,['request_cancel','approve_cancel','reject_cancel'],true))<p>{{ $vacationCopy->text('cancel_note') }}</p>@endif</x-chrono-help><a href="{{ $base }}" data-vacation-close class="btn btn-outline-secondary">{{ $vacationCopy->text('cancel') }}</a><button class="btn {{ in_array($action,['reject','reject_cancel','withdraw'],true)?'btn-outline-secondary':'btn-primary' }}" type="submit">{{ $vacationCopy->action($action) }}</button></footer>
                </form>
            </dialog>
            @endforeach
            <details class="chrono-vacation-history"><summary>{{ $vacationCopy->text('history') }}</summary>
                <ol>@foreach ($request['history'] as $event)<li><div><span>{{ $vacationCopy->text('history_'.$event['action']) }}</span><time datetime="{{ gmdate('Y-m-d\TH:i:s\Z',$event['occurred_at']) }}">{{ (new \DateTimeImmutable('@'.$event['occurred_at']))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y, H:i') }}</time></div><p>{{ $event['reason'] }}</p><x-chrono-help><p>Antrag #{{ $request['id'] }}, Version {{ $event['revision'] }}, Konto #{{ $event['actor_id'] }}.</p></x-chrono-help></li>@endforeach</ol>
                @if ($request['history_limited'])<p class="text-secondary small">{{ $vacationCopy->text('history_limit') }}</p>@endif
            </details>
        </article>
        @endforeach
    </section>
    @if ($vacation['pages'] > 1)
    <nav class="chrono-vacation-pagination" aria-label="{{ $vacationCopy->text('page') }}">
        <span>{{ $vacationCopy->text('page') }} {{ $vacation['page'] }} / {{ $vacation['pages'] }} ({{ $vacation['total'] }})</span>
        @foreach (array_unique([1,max(1,$vacation['page']-1),min($vacation['pages'],$vacation['page']+1),$vacation['pages']]) as $number)<a href="{{ 'vacation.php?'.http_build_query(['scope'=>$vacation['scope'],'state'=>$vacation['state'],'page'=>$number]) }}" @if ($vacation['page']===$number) aria-current="page" @endif>{{ $number }}</a>@endforeach
    </nav>
    @endif
    @endif
</main>
<script @if ($page->cspNonce !== null) nonce="{{ $page->cspNonce }}" @endif>
(() => {
    'use strict';
    const root = document.querySelector('.chrono-vacation');
    if (!root) return;
    function openDialog(dialog) {
        if (!dialog || typeof dialog.showModal !== 'function') return false;
        if (dialog.open) dialog.removeAttribute('open');
        dialog.showModal();
        return true;
    }
    root.addEventListener('click', event => {
        const trigger = event.target.closest('a[data-vacation-dialog], a[data-vacation-close]');
        if (!trigger) return;
        if (trigger.hasAttribute('data-vacation-dialog')) {
            const dialog = document.getElementById(trigger.dataset.vacationDialog);
            if (dialog && root.contains(dialog) && openDialog(dialog)) event.preventDefault();
        } else {
            const dialog = trigger.closest('dialog');
            if (dialog && typeof dialog.close === 'function') { dialog.close(); event.preventDefault(); }
        }
    });
    root.querySelectorAll('dialog[data-vacation-open]').forEach(openDialog);
})();
</script>
@endsection
