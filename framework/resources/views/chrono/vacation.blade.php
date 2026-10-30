@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-4">{{ $vacationCopy->text('title') }}</h1>
    <x-chrono-help><p class="opacity-70 mb-6">{{ $vacationCopy->text('intro') }}</p></x-chrono-help>
    <a class="link link-primary" href="attendance.php">{{ $vacationCopy->text('back') }}</a>
    @if ($vacationError)<div class="alert alert-error my-4" role="alert">{{ $vacationError }}</div>@endif
    @if ($vacation !== null)
    <p class="text-sm opacity-70 my-4" role="status">{{ $vacationCopy->text('current') }} · {{ gmdate('Y-m-d H:i:s',$vacation['loaded_at']) }} UTC</p>
    <section class="card bg-base-100 shadow-xl my-6"><div class="card-body p-4 sm:p-6">
        <h2 class="card-title">{{ $vacationCopy->text('new') }}</h2>
        <form method="post" action="vacation.php?scope=own&amp;state=all&amp;page=1" class="space-y-4">
            @csrf
            <input type="hidden" name="action" value="request"><input type="hidden" name="command_id" value="{{ bin2hex(random_bytes(16)) }}">
            <div class="grid sm:grid-cols-2 gap-3">
                <label>{{ $vacationCopy->text('from') }}<input type="date" name="start_date" required class="input input-bordered w-full"></label>
                <label>{{ $vacationCopy->text('through') }}<input type="date" name="end_date" required class="input input-bordered w-full"></label>
            </div>
            <label class="block">{{ $vacationCopy->text('reason') }}<textarea name="reason" required maxlength="2000" class="textarea textarea-bordered w-full"></textarea></label>
            <button type="submit" class="btn btn-primary">{{ $vacationCopy->text('submit') }}</button>
        </form>
    </div></section>
    <nav class="flex flex-wrap gap-4 my-4" aria-label="{{ $vacationCopy->text('title') }}">
        <a class="link link-primary" href="vacation.php?scope=own&amp;state=all">{{ $vacationCopy->text('own') }}</a>
        @if ($vacation['can_review'])<a class="link link-primary" href="vacation.php?scope=review&amp;state=pending">{{ $vacationCopy->text('review') }}</a>@endif
    </nav>
    <form method="get" action="vacation.php" class="flex flex-wrap gap-3 items-end my-4">
        <input type="hidden" name="scope" value="{{ $vacation['scope'] }}">
        <label>{{ $vacationCopy->text('state') }}<select name="state" class="select select-bordered block">
            <option value="all" @selected($vacation['state']==='all')>{{ $vacationCopy->text('all') }}</option>
            @foreach (['pending','approved','rejected','withdrawn','cancel_pending','cancelled'] as $state)<option value="{{ $state }}" @selected($vacation['state']===$state)>{{ $vacationCopy->state($state) }}</option>@endforeach
        </select></label>
        <button class="btn btn-outline" type="submit">{{ $vacationCopy->text('filter') }}</button>
    </form>
    @if ($vacation['rows']===[])<p role="status">{{ $vacationCopy->text('empty') }}</p>@endif
    @foreach ($vacation['rows'] as $request)
    @php($base='vacation.php?'.http_build_query(['scope'=>$vacation['scope'],'state'=>$vacation['state'],'page'=>$vacation['page']]))
    <section class="card bg-base-100 shadow-xl my-4" id="vacation-{{ $request['id'] }}"><div class="card-body p-4 sm:p-6">
        <h2 class="card-title">#{{ $request['id'] }} · {{ $request['username'] }}</h2>
        <p>{{ $request['start_date'] }} – {{ $request['end_date'] }} · {{ $vacationCopy->state($request['state']) }} · v{{ $request['revision'] }}</p>
        @php($actions=[])
        @if ($vacation['scope']==='own' && $request['state']==='pending')@php($actions=['withdraw'])@endif
        @if ($vacation['scope']==='own' && $request['state']==='approved')@php($actions=['request_cancel'])@endif
        @if ($vacation['scope']==='review' && $request['state']==='pending')
            @php($actions=['reject'])
            <a class="link link-primary" href="{{ $base.'&preview='.$request['id'].'#vacation-'.$request['id'] }}">{{ $vacationCopy->text('preview') }}</a>
            @if ($vacation['approval_request_id']===$request['id'] && $vacation['approval']!==null)
                <div class="border border-base-300 rounded-lg p-4 my-3">
                    <x-chrono-help><p class="text-sm">{{ $vacationCopy->text('preview_note') }}</p></x-chrono-help>
                    <p>{{ $vacationCopy->text('credit_days') }}: {{ $vacation['approval']['credit_days'] }}</p>
                    <ul>@foreach ($vacation['approval']['days'] as $day)<li>{{ $day['day'] }} · {{ $day['minutes'] }} {{ $vacationCopy->text('minutes') }}</li>@endforeach</ul>
                    @if ($vacation['approval']['approvable'])@php($actions=['approve','reject'])
                    @else<p class="text-warning">{{ $vacationCopy->text('no_credit') }}</p>@endif
                </div>
            @endif
        @endif
        @if ($vacation['scope']==='review' && $request['state']==='cancel_pending')@php($actions=['approve_cancel','reject_cancel'])@endif
        @if (in_array($request['state'],['approved','cancel_pending'],true))<x-chrono-help><p class="text-sm opacity-70">{{ $vacationCopy->text('cancel_note') }}</p></x-chrono-help>@endif
        @foreach ($actions as $action)
        <form method="post" action="{{ $base }}" class="space-y-2 border-t border-base-200 pt-3 mt-3">
            @csrf
            <input type="hidden" name="action" value="{{ $action }}"><input type="hidden" name="request_id" value="{{ $request['id'] }}"><input type="hidden" name="revision" value="{{ $request['revision'] }}"><input type="hidden" name="command_id" value="{{ bin2hex(random_bytes(16)) }}">
            @if ($action==='approve')<input type="hidden" name="plan_hash" value="{{ $vacation['approval']['plan_hash'] }}">@endif
            <label class="block">{{ $vacationCopy->text('reason') }}<textarea name="reason" required maxlength="2000" class="textarea textarea-bordered w-full"></textarea></label>
            <button class="btn {{ in_array($action,['reject','approve_cancel'],true)?'btn-outline':'btn-primary' }}" type="submit">{{ $vacationCopy->action($action) }}</button>
        </form>
        @endforeach
        <details class="mt-4"><summary class="cursor-pointer font-semibold">{{ $vacationCopy->text('history') }}</summary><ol class="space-y-2 mt-2">
            @foreach ($request['history'] as $event)<li class="text-sm">v{{ $event['revision'] }} · {{ $vacationCopy->action($event['action']) }} · #{{ $event['actor_id'] }} · <time datetime="{{ gmdate('Y-m-d\TH:i:s\Z',$event['occurred_at']) }}">{{ gmdate('Y-m-d H:i:s',$event['occurred_at']) }} UTC</time><p class="break-words">{{ $event['reason'] }}</p></li>@endforeach
        </ol>@if ($request['history_limited'])<p class="text-xs opacity-70">{{ $vacationCopy->text('history_limit') }}</p>@endif</details>
    </div></section>
    @endforeach
    <nav class="flex flex-wrap items-center gap-4 my-6" aria-label="{{ $vacationCopy->text('page') }}">
        <span>{{ $vacationCopy->text('page') }} {{ $vacation['page'] }} / {{ $vacation['pages'] }} · {{ $vacation['total'] }}</span>
        @foreach (array_unique([1,max(1,$vacation['page']-1),min($vacation['pages'],$vacation['page']+1),$vacation['pages']]) as $number)<a class="link" href="{{ 'vacation.php?'.http_build_query(['scope'=>$vacation['scope'],'state'=>$vacation['state'],'page'=>$number]) }}">{{ $number }}</a>@endforeach
    </nav>
    @endif
</main>
@endsection
