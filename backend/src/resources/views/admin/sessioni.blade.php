@extends('admin.layout')
@section('page-title', 'Sessioni')

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
    <div>
        <h2 style="font-family:'DM Serif Display',Georgia,serif;font-size:1.3rem;color:var(--text);">Sessioni di ricarica</h2>
        <p style="font-size:0.78rem;color:var(--text-3);margin-top:2px;">{{ $sessioni->total() }} sessioni totali</p>
    </div>
</div>

<form method="GET" action="/admin/sessioni" style="margin-bottom:1.25rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
    <input type="text" name="cerca" value="{{ request('cerca') }}"
           placeholder="Cerca per email, nome o ID sessione…"
           class="form-input" style="max-width:300px;">
    <select name="stato" class="form-input" style="max-width:160px;">
        <option value="">Tutti gli stati</option>
        <option value="attiva"   {{ request('stato') === 'attiva'   ? 'selected' : '' }}>In corso</option>
        <option value="conclusa" {{ request('stato') === 'conclusa' ? 'selected' : '' }}>Concluse</option>
    </select>
    <button type="submit" class="btn btn-primary">Filtra</button>
    @if(request()->hasAny(['cerca','stato']))
        <a href="/admin/sessioni" class="btn btn-outline">Reset</a>
    @endif
</form>

<div class="admin-card">
    <table class="admin-table">
        <thead><tr>
            <th>Utente</th>
            <th>Punto</th>
            <th>Inizio</th>
            <th>Fine</th>
            <th>Durata</th>
            <th>kWh</th>
            <th>Costo</th>
            <th>Stato</th>
        </tr></thead>
        <tbody>
        @forelse($sessioni as $s)
        <tr>
            <td>
                <p style="font-weight:600;color:var(--text);font-size:0.8rem;">{{ $s->nome }} {{ $s->cognome }}</p>
                <p style="font-size:0.68rem;color:var(--text-3);">{{ $s->email }}</p>
            </td>
            <td style="font-size:0.72rem;font-family:monospace;color:var(--text-2);">{{ $s->id_punto ?? '—' }}</td>
            <td style="font-size:0.75rem;">{{ \Carbon\Carbon::parse($s->data_inizio)->format('d/m/Y H:i') }}</td>
            <td style="font-size:0.75rem;">{{ $s->data_fine ? \Carbon\Carbon::parse($s->data_fine)->format('d/m/Y H:i') : '—' }}</td>
            <td style="font-size:0.75rem;">
                @if($s->data_fine)
                    @php $sec = strtotime($s->data_fine) - strtotime($s->data_inizio); @endphp
                    {{ floor($sec/3600) }}h {{ floor(($sec%3600)/60) }}m
                @else
                    <span style="color:var(--gold);">In corso</span>
                @endif
            </td>
            <td style="font-weight:600;color:var(--accent);">{{ $s->quantita_kwh ? number_format($s->quantita_kwh,2) : '—' }}</td>
            <td>{{ $s->costo_totale ? '€ '.number_format($s->costo_totale,2) : '—' }}</td>
            <td>
                @if($s->data_fine)
                    <span class="badge-pill badge-green">Conclusa</span>
                @else
                    <span class="badge-pill badge-yellow">In corso</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;color:var(--text-3);padding:2rem;">Nessuna sessione trovata.</td></tr>
        @endforelse
        </tbody>
    </table>

    @if($sessioni->hasPages())
    <div style="padding:1rem 1.5rem;display:flex;gap:0.5rem;align-items:center;border-top:1px solid var(--border);">
        @if($sessioni->onFirstPage())
            <span class="btn btn-outline btn-sm" style="opacity:0.4;">← Prec</span>
        @else
            <a href="{{ $sessioni->previousPageUrl() }}" class="btn btn-outline btn-sm">← Prec</a>
        @endif
        <span style="font-size:0.75rem;color:var(--text-3);">Pagina {{ $sessioni->currentPage() }} di {{ $sessioni->lastPage() }}</span>
        @if($sessioni->hasMorePages())
            <a href="{{ $sessioni->nextPageUrl() }}" class="btn btn-outline btn-sm">Succ →</a>
        @else
            <span class="btn btn-outline btn-sm" style="opacity:0.4;">Succ →</span>
        @endif
    </div>
    @endif
</div>

@endsection