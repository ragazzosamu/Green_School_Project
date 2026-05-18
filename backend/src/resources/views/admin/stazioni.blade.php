@extends('admin.layout')
@section('page-title', 'Stazioni')

@section('content')

<div style="margin-bottom:1.25rem;">
    <h2 style="font-family:'DM Serif Display',Georgia,serif;font-size:1.3rem;color:var(--text);">Stazioni di ricarica</h2>
    <p style="font-size:0.78rem;color:var(--text-3);margin-top:2px;">{{ $stazioni->count() }} stazioni nel sistema</p>
</div>

<div class="admin-card">
    <table class="admin-table">
        <thead><tr>
            <th>Stazione</th>
            <th>Indirizzo</th>
            <th>Punti totali</th>
            <th>Punti liberi</th>
            <th>Punti online</th>
            <th>Stato</th>
            <th>Azione</th>
        </tr></thead>
        <tbody>
        @forelse($stazioni as $s)
        <tr>
            <td>
                <p style="font-weight:600;color:var(--text);">{{ $s->nome }}</p>
                <p style="font-size:0.68rem;color:var(--text-3);font-family:monospace;">{{ $s->id_stazione }}</p>
            </td>
            <td style="font-size:0.78rem;color:var(--text-2);">{{ $s->indirizzo ?? '—' }}</td>
            <td style="text-align:center;">{{ $s->punti_totali }}</td>
            <td style="text-align:center;">
                <span class="badge-pill {{ $s->punti_liberi > 0 ? 'badge-green' : 'badge-red' }}">
                    {{ $s->punti_liberi }}
                </span>
            </td>
            <td style="text-align:center;">{{ $s->punti_online }}</td>
            <td>
                @php
                    $stato = $s->stato_hardware;
                    $cls = match($stato) {
                        'online'                    => 'badge-green',
                        'offline'                   => 'badge-red',
                        'guasto'                    => 'badge-red',
                        'manutenzione_programmata'  => 'badge-yellow',
                        default                     => 'badge-gray',
                    };
                @endphp
                <span class="badge-pill {{ $cls }}">{{ str_replace('_', ' ', $stato) }}</span>
            </td>
            <td>
                <form method="POST" action="/admin/stazioni/{{ $s->id_stazione }}/toggle">
                    @csrf
                    <button type="submit" class="btn btn-sm {{ $stato === 'manutenzione_programmata' ? 'btn-outline' : 'btn-danger' }}">
                        {{ $stato === 'manutenzione_programmata' ? '✅ Riporta online' : '🔧 Manutenzione' }}
                    </button>
                </form>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-3);padding:2rem;">Nessuna stazione trovata.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@endsection