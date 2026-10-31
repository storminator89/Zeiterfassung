@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <header class="page-header mb-4"><div class="row align-items-center"><div class="col"><h1 class="page-title"><x-chrono-icon name="calendar-check" size="24" /> {{ $vacationCopy->text('title') }}</h1></div></div></header>
    @if ($vacationError)<div class="alert alert-error my-4" role="alert">{{ $vacationError }}</div>@endif
    @if ($vacation !== null)

    <details class="chrono-disclosure chrono-create-action mb-4" @if ($vacationError) open @endif>
        <summary class="btn btn-primary">Urlaub beantragen</summary>
        <div class="card chrono-vacation-form mt-3"><div class="card-body">
            <form method="post" action="vacation.php?scope=own&amp;state=all&amp;page=1">
                @csrf
                <input type="hidden" name="action" value="request"><input type="hidden" name="command_id" value="{{ bin2hex(random_bytes(16)) }}">
                <div class="row g-3 mb-3">
                    <div class="col-sm-6"><label class="form-label" for="vacation-start-date">{{ $vacationCopy->text('from') }}</label><input type="date" id="vacation-start-date" name="start_date" required class="form-control"></div>
                    <div class="col-sm-6"><label class="form-label" for="vacation-end-date">{{ $vacationCopy->text('through') }}</label><input type="date" id="vacation-end-date" name="end_date" required class="form-control"></div>
                </div>
                <div class="mb-3"><label class="form-label" for="vacation-reason">{{ $vacationCopy->text('reason') }}</label><textarea id="vacation-reason" name="reason" required maxlength="2000" rows="3" class="form-control"></textarea></div>
                <button type="submit" class="btn btn-primary">{{ $vacationCopy->text('submit') }}</button>
            </form><div class="mt-3"><a class="link text-sm" href="attendance.php">{{ $vacationCopy->text('back') }}</a></div>
        </div></div>
    </details>
    <section class="card">
        <div class="card-header d-flex flex-wrap gap-3 justify-content-between">
            <nav class="nav nav-pills" aria-label="{{ $vacationCopy->text('title') }}">
                <a class="nav-link {{ $vacation['scope']==='own' ? 'active' : '' }}" href="vacation.php?scope=own&amp;state=all" @if ($vacation['scope']==='own') aria-current="page" @endif>{{ $vacationCopy->text('own') }}</a>
                @if ($vacation['can_review'])<a class="nav-link {{ $vacation['scope']==='review' ? 'active' : '' }}" href="vacation.php?scope=review&amp;state=pending" @if ($vacation['scope']==='review') aria-current="page" @endif>{{ $vacationCopy->text('review') }}</a>@endif
            </nav>
            <form method="get" action="vacation.php" class="d-flex flex-wrap align-items-end gap-2">
                <input type="hidden" name="scope" value="{{ $vacation['scope'] }}">
                <div><label class="form-label visually-hidden" for="vacation-state">{{ $vacationCopy->text('state') }}</label><select id="vacation-state" name="state" class="form-select">
                    <option value="all" @selected($vacation['state']==='all')>{{ $vacationCopy->text('all') }}</option>
                    @foreach (['pending','approved','rejected','withdrawn','cancel_pending','cancelled'] as $state)<option value="{{ $state }}" @selected($vacation['state']===$state)>{{ $vacationCopy->state($state) }}</option>@endforeach
                </select></div>
                <button class="btn btn-outline-secondary" type="submit">{{ $vacationCopy->text('filter') }}</button>
            </form>
        </div>
        <div class="list-group list-group-flush">
            @if ($vacation['rows']===[])<div class="empty"><img src="assets/illustrations/calendar-plan.png" class="chrono-empty-illustration" alt="" width="104" height="104" loading="lazy"><p class="empty-title" role="status">{{ $vacationCopy->text('empty') }}</p></div>@endif
    @foreach ($vacation['rows'] as $request)
    @php($base='vacation.php?'.http_build_query(['scope'=>$vacation['scope'],'state'=>$vacation['state'],'page'=>$vacation['page']]))
    <section class="list-group-item py-3" id="vacation-{{ $request['id'] }}">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <h2 class="h3 mb-0">{{ (new \DateTimeImmutable($request['start_date']))->format('d.m.Y') }} bis {{ (new \DateTimeImmutable($request['end_date']))->format('d.m.Y') }}</h2>
            <span class="badge bg-secondary-lt">{{ $vacationCopy->state($request['state']) }}</span>
        </div>
        @if ($vacation['scope']==='review')<p class="text-secondary mt-1">{{ $request['username'] }}</p>@endif
        @php($actions=[])
        @if ($vacation['scope']==='own' && $request['state']==='pending')@php($actions=['withdraw'])@endif
        @if ($vacation['scope']==='own' && $request['state']==='approved')@php($actions=['request_cancel'])@endif
        @if ($vacation['scope']==='review' && $request['state']==='pending')
            @php($actions=['reject'])
            <a class="link link-primary" href="{{ $base.'&preview='.$request['id'].'#vacation-'.$request['id'] }}">{{ $vacationCopy->text('preview') }}</a>
            @if ($vacation['approval_request_id']===$request['id'] && $vacation['approval']!==null)
                <div class="alert alert-info my-3">
                    <x-chrono-help><p class="text-sm">{{ $vacationCopy->text('preview_note') }}</p></x-chrono-help>
                    <p>{{ $vacationCopy->text('credit_days') }}: {{ $vacation['approval']['credit_days'] }}</p>
                    <ul>@foreach ($vacation['approval']['days'] as $day)<li>{{ (new \DateTimeImmutable($day['day']))->format('d.m.Y') }}: {{ $day['minutes'] }} {{ $vacationCopy->text('minutes') }}</li>@endforeach</ul>
                    @if ($vacation['approval']['approvable'])@php($actions=['approve','reject'])
                    @else<p class="text-warning">{{ $vacationCopy->text('no_credit') }}</p>@endif
                </div>
            @endif
        @endif
        @if ($vacation['scope']==='review' && $request['state']==='cancel_pending')@php($actions=['approve_cancel','reject_cancel'])@endif
        @if (in_array($request['state'],['approved','cancel_pending'],true))<x-chrono-help><p class="text-sm opacity-70">{{ $vacationCopy->text('cancel_note') }}</p></x-chrono-help>@endif
        @foreach ($actions as $action)
        <details class="chrono-disclosure mt-3" @if ($vacationError) open @endif><summary>{{ $vacationCopy->action($action) }}</summary>
        <form method="post" action="{{ $base }}" class="chrono-vacation-form pt-3">
            @csrf
            <input type="hidden" name="action" value="{{ $action }}"><input type="hidden" name="request_id" value="{{ $request['id'] }}"><input type="hidden" name="revision" value="{{ $request['revision'] }}"><input type="hidden" name="command_id" value="{{ bin2hex(random_bytes(16)) }}">
            @if ($action==='approve')<input type="hidden" name="plan_hash" value="{{ $vacation['approval']['plan_hash'] }}">@endif
            <label class="form-label">{{ $vacationCopy->text('reason') }}<textarea name="reason" required maxlength="2000" rows="2" class="form-control mt-1 mb-3"></textarea></label>
            <button class="btn {{ in_array($action,['reject','approve_cancel'],true)?'btn-outline-secondary':'btn-primary' }}" type="submit">{{ $vacationCopy->action($action) }}</button>
        </form></details>
        @endforeach
        <details class="mt-4"><summary class="cursor-pointer font-semibold">{{ $vacationCopy->text('history') }}</summary><p class="text-sm opacity-70 mt-2">Antrag #{{ $request['id'] }}, Version {{ $request['revision'] }}</p><ol class="space-y-2 mt-2">
            @foreach ($request['history'] as $event)<li class="text-sm">{{ $vacationCopy->action($event['action']) }} (Version {{ $event['revision'] }}, #{{ $event['actor_id'] }}) <time datetime="{{ gmdate('Y-m-d\TH:i:s\Z',$event['occurred_at']) }}">{{ (new \DateTimeImmutable('@'.$event['occurred_at']))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y, H:i') }}</time><p class="break-words">{{ $event['reason'] }}</p></li>@endforeach
        </ol>@if ($request['history_limited'])<p class="text-xs opacity-70">{{ $vacationCopy->text('history_limit') }}</p>@endif</details>
    </section>
    @endforeach
        </div>
    </section>
    @if ($vacation['pages'] > 1)
    <nav class="flex flex-wrap items-center gap-4 my-6" aria-label="{{ $vacationCopy->text('page') }}">
        <span>{{ $vacationCopy->text('page') }} {{ $vacation['page'] }} / {{ $vacation['pages'] }} ({{ $vacation['total'] }})</span>
        @foreach (array_unique([1,max(1,$vacation['page']-1),min($vacation['pages'],$vacation['page']+1),$vacation['pages']]) as $number)<a class="link" href="{{ 'vacation.php?'.http_build_query(['scope'=>$vacation['scope'],'state'=>$vacation['state'],'page'=>$number]) }}">{{ $number }}</a>@endforeach
    </nav>
    @endif
    @endif
</main>
@endsection
