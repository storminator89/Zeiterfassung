@props(['name' => 'clock', 'size' => 20, 'label' => null])
@php
    $available = ['clock', 'calendar', 'calendar-stats', 'calendar-check', 'calendar-off', 'list-details', 'chart-bar', 'history', 'users', 'user', 'settings', 'device-desktop', 'building', 'briefcase', 'plane', 'pencil', 'file-export', 'search', 'plus', 'filter', 'chevron-right', 'chevron-down', 'arrow-left', 'arrow-right', 'login', 'logout', 'coffee', 'check', 'info-circle', 'shield-lock'];
    $iconName = is_string($name) && in_array($name, $available, true) ? $name : 'clock';
    $iconSize = in_array((int) $size, [16, 18, 20, 24, 28, 32], true) ? (int) $size : 20;
@endphp
<svg {{ $attributes->class(['chrono-icon'])->merge(['width' => $iconSize, 'height' => $iconSize]) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false" @if ($label === null) aria-hidden="true" @else role="img" aria-label="{{ $label }}" @endif><use href="assets/vendor/tabler-icons/3.50.0/icons.svg#{{ $iconName }}"></use></svg>
