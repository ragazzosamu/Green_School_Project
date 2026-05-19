@extends('admin.layout')
@section('page-title', 'Sessioni')

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
    <div>
        <h2 style="font-family:'DM Serif Display',Georgia,serif;font-size:1.3rem;color:var(--text);">Sessioni di ricarica</h2>
        <p style="font-size:0.78rem;color:var(--text-3);margin-top:2px;">{{ $sessioni->total() }} sessioni totali</p>
    </div>
</div>
{{-- NUOVO PANNELLO ESTRATTORE REPORT (PER UTENTE O PER DATA) --}}
<div style="background:var(--surface); border:1px solid var(--border); border-radius:14px; padding:1.25rem; margin-bottom:1.5rem; box-shadow:var(--shadow-sm);">
    <p style="font-size:0.65rem; font-weight:700; letter-spacing:0.09em; text-transform:uppercase; color:var(--text-3); margin-bottom:10px;">📊 Area Esportazione Report CSV</p>
    
    <form method="GET" action="{{ route('admin.report.csv') }}" style="display:flex; gap:1rem; flex-wrap:wrap; align-items:center;">
        
        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size:0.7rem; color:var(--text-2); font-weight:600;">Filtra per Utente:</label>
            <select name="utente_id" class="form-input" style="max-width:280px; min-width:200px; font-size:0.75rem; padding:0.4rem 0.6rem;">
                <option value="">-- Seleziona Utente --</option>
                @foreach($listaUtenti as $u)
                    <option value="{{ $u->id_utente }}">{{ $u->cognome }} {{ $u->nome }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" name="azione" value="utente" class="btn btn-outline" style="font-size:0.75rem; padding:0.5rem 0.8rem; margin-top:1rem; border-color:var(--purple); color:var(--purple);">
            📥 Scarica Report Utente
        </button>

        <div style="border-left:1px solid var(--border); height:30px; margin: 0 0.5rem; margin-top:1rem;"></div>

        <div style="display:flex; flex-direction:column; gap:4px;">
            <label style="font-size:0.7rem; color:var(--text-2); font-weight:600;">Scegli il Giorno:</label>
            <input type="date" name="data_report" value="{{ date('Y-m-d') }}" class="form-input" style="max-width:160px; font-size:0.75rem; padding:0.4rem 0.6rem;">
        </div>

        <button type="submit" name="azione" value="data" class="btn btn-primary" style="font-size:0.75rem; padding:0.5rem 0.8rem; margin-top:1rem; background-color:var(--accent); border-color:var(--accent);">
            📥 Scarica Report Giornaliero
        </button>
    </form>
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