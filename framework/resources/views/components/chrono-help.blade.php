@props(['label' => 'Info'])
<details {{ $attributes->class(['chrono-help']) }}>
    <summary aria-label="{{ $label }}" title="{{ $label }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 10v7M12 6v1"/></svg></summary>
    <div class="chrono-help-panel">{{ $slot }}</div>
</details>
