@extends('chrono.layouts.app')

@section('content')
<main class="container mx-auto px-4 py-8 chrono-page">
    <h1 class="text-2xl font-bold mb-6">Praxis-Terminals</h1>
    <div class="alert alert-info mb-6" role="note">Nur lesende Geräteübersicht mit Standort und Kontaktstatus. Koppeln, Sperren, Modulfreigabe, Standortverwaltung und persönliche Terminal-Zugänge sind in dieser Laravel-Ansicht nicht verbunden.</div>
    <section class="card bg-base-100 shadow-xl p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">Geräte</h2>
        <p class="opacity-70 mb-4">Freigabe und letzter Kontakt sind unabhängig. Kontakt gilt nach {{ $terminalDevices->contactMaxAge }} Sekunden als veraltet; aktueller Kontakt garantiert keine Erreichbarkeit. Stand beim Laden der Seite.</p>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Gerät</th><th>Standort</th><th>Freigabe</th><th>Kontaktstatus</th><th>Letzter Kontakt</th></tr></thead>
                <tbody>
                @foreach ($terminalDevices->devices as $device)
                    <tr>
                        <td>{{ $device['name'] }} #{{ $device['id'] }}</td>
                        <td>{{ $device['site'] }}</td>
                        <td>{{ $device['permission_label'] }}</td>
                        <td><span class="{{ in_array($device['contact_state'], ['stale', 'invalid'], true) ? 'text-warning font-semibold' : '' }}">{{ $device['contact_label'] }}</span></td>
                        <td>{{ $device['last_seen_label'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</main>
@endsection
