@extends('admin.layout')
@section('page-title', 'Dashboard')

@section('content')

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    @media(max-width:900px) { .stats-grid { grid-template-columns: repeat(2,1fr); } }
    @media(max-width:500px) { .stats-grid { grid-template-columns: 1fr; } }

    .stat-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 1.25rem 1.5rem;
        box-shadow: var(--shadow-sm);
    }
    .stat-label { font-size:0.65rem; font-weight:700; letter-spacing:0.09em; text-transform:uppercase; color:var(--text-3); margin-bottom:6px; }
    .stat-value { font-family:'DM Serif Display',Georgia,serif; font-size:2rem; color:var(--text); line-height:1; letter-spacing:-0.02em; }
    .stat-value.purple { color:var(--purple); }
    .stat-value.green  { color:var(--accent); }
    .stat-value.red    { color:var(--red); }
    .stat-sub { font-size:0.7rem; color:var(--text-3); margin-top:4px; }

    .dash-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.25rem;
        margin-bottom: 1.25rem;
    }
    @media(max-width:900px) { .dash-grid { grid-template-columns: 1fr; } }

    .chart-wrap { padding: 1.25rem 1.5rem 1.5rem; }
    canvas { max-height: 200px; }
</style>

{{-- Stat cards --}}
<div class="stats-grid">
    <div class="stat-card">
        <p class="stat-label">Utenti registrati</p>
        <p class="stat-value purple">{{ $stats['utenti_totali'] }}</p>
        <p class="stat-sub">account attivi</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">Sessioni oggi</p>
        <p class="stat-value green">{{ $stats['sessioni_oggi'] }}</p>
        <p class="stat-sub">{{ $stats['sessioni_attive'] }} in corso ora</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">kWh totali erogati</p>
        <p class="stat-value">{{ number_format($stats['kwh_totali'], 1) }}</p>
        <p class="stat-sub">da {{ $stats['sessioni_totali'] }} sessioni</p>
    </div>
    <div class="stat-card">
        <p class="stat-label">Revenue totale</p>
        <p class="stat-value green">€ {{ number_format($stats['revenue_totale'], 2) }}</p>
        <p class="stat-sub">sessioni fatturate</p>
    </div>
</div>

{{-- Stato stazioni --}}
<div style="display:flex;gap:1rem;margin-bottom:1.5rem;">
    <div class="stat-card" style="flex:1;display:flex;align-items:center;gap:1rem;">
        <span style="font-size:2rem;">🟢</span>
        <div>
            <p class="stat-label">Stazioni online</p>
            <p class="stat-value green" style="font-size:1.5rem;">{{ $stats['stazioni_online'] }}</p>
        </div>
    </div>
    <div class="stat-card" style="flex:1;display:flex;align-items:center;gap:1rem;">
        <span style="font-size:2rem;">🔴</span>
        <div>
            <p class="stat-label">Offline / guasto</p>
            <p class="stat-value red" style="font-size:1.5rem;">{{ $stats['stazioni_offline'] }}</p>
        </div>
    </div>
</div>

<div class="dash-grid">
    {{-- Grafico sessioni settimana --}}
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Sessioni ultimi 7 giorni</span>
        </div>
        <div class="chart-wrap">
            <canvas id="chartSessioni"></canvas>
        </div>
    </div>

    {{-- Top utenti --}}
    <div class="admin-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Utenti più attivi</span>
            <a href="/admin/utenti" class="btn btn-outline btn-sm">Tutti →</a>
        </div>
        <table class="admin-table">
            <thead><tr>
                <th>Utente</th>
                <th>Sessioni</th>
                <th>kWh</th>
            </tr></thead>
            <tbody>
            @foreach($topUtenti as $u)
            <tr>
                <td>
                    <p style="font-weight:600;color:var(--text);">{{ $u->nome }} {{ $u->cognome }}</p>
                    <p style="font-size:0.7rem;color:var(--text-3);">{{ $u->email }}</p>
                </td>
                <td><span class="badge-pill badge-purple">{{ $u->sessioni }}</span></td>
                <td style="color:var(--accent);font-weight:600;">{{ number_format($u->kwh, 1) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Ultime sessioni --}}
<div class="admin-card">
    <div class="admin-card-header">
        <span class="admin-card-title">Ultime sessioni</span>
        <a href="/admin/sessioni" class="btn btn-outline btn-sm">Vedi tutte →</a>
    </div>
    <table class="admin-table">
        <thead><tr>
            <th>Utente</th>
            <th>Punto</th>
            <th>Inizio</th>
            <th>Fine</th>
            <th>kWh</th>
            <th>Costo</th>
            <th>Stato</th>
        </tr></thead>
        <tbody>
        @foreach($ultimeSessioni as $s)
        <tr>
            <td>
                <p style="font-weight:600;color:var(--text);">{{ $s->nome }} {{ $s->cognome }}</p>
                <p style="font-size:0.7rem;color:var(--text-3);">{{ $s->email }}</p>
            </td>
            <td style="font-size:0.75rem;font-family:monospace;">{{ $s->id_punto }}</td>
            <td style="font-size:0.75rem;">{{ \Carbon\Carbon::parse($s->data_inizio)->format('d/m H:i') }}</td>
            <td style="font-size:0.75rem;">{{ $s->data_fine ? \Carbon\Carbon::parse($s->data_fine)->format('d/m H:i') : '—' }}</td>
            <td>{{ $s->quantita_kwh ? number_format($s->quantita_kwh, 2) : '—' }}</td>
            <td>{{ $s->costo_totale ? '€ '.number_format($s->costo_totale, 2) : '—' }}</td>
            <td>
                @if($s->data_fine)
                    <span class="badge-pill badge-green">Conclusa</span>
                @else
                    <span class="badge-pill badge-yellow">In corso</span>
                @endif
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const labels = @json($sessioniSettimana->pluck('giorno')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m')));
const dataSessioni = @json($sessioniSettimana->pluck('totale'));
const dataKwh = @json($sessioniSettimana->pluck('kwh'));

new Chart(document.getElementById('chartSessioni'), {
    type: 'bar',
    data: {
        labels,
        datasets: [
            {
                label: 'Sessioni',
                data: dataSessioni,
                backgroundColor: 'rgba(124,58,237,0.15)',
                borderColor: 'rgba(124,58,237,0.8)',
                borderWidth: 2,
                borderRadius: 6,
                yAxisID: 'y',
            },
            {
                label: 'kWh',
                data: dataKwh,
                type: 'line',
                borderColor: '#2A6B4A',
                backgroundColor: 'transparent',
                borderWidth: 2,
                pointRadius: 3,
                tension: 0.3,
                yAxisID: 'y1',
            }
        ]
    },
    options: {
        responsive: true,
        interaction: { mode: 'index' },
        plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } },
        scales: {
            y:  { position: 'left',  beginAtZero: true, ticks: { font: { size: 11 } } },
            y1: { position: 'right', beginAtZero: true, ticks: { font: { size: 11 } }, grid: { drawOnChartArea: false } },
        }
    }
});
</script>

@endsection