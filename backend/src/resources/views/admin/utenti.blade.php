@extends('admin.layout')
@section('page-title', 'Gestione Utenti')

@section('content')

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
    <div>
        <h2 style="font-family:'DM Serif Display',Georgia,serif;font-size:1.3rem;color:var(--text);">Utenti</h2>
        <p style="font-size:0.78rem;color:var(--text-3);margin-top:2px;">{{ $utenti->total() }} utenti registrati</p>
    </div>
</div>

{{-- Ricerca --}}
<form method="GET" action="/admin/utenti" style="margin-bottom:1.25rem;display:flex;gap:0.75rem;">
    <input type="text" name="cerca" value="{{ request('cerca') }}"
           placeholder="Cerca per nome, cognome o email…"
           class="form-input" style="max-width:340px;">
    <button type="submit" class="btn btn-primary">Cerca</button>
    @if(request('cerca'))
        <a href="/admin/utenti" class="btn btn-outline">Cancella</a>
    @endif
</form>

<div class="admin-card">
    <table class="admin-table">
        <thead><tr>
            <th>Utente</th>
            <th>Email</th>
            <th>Cellulare</th>
            <th>Sessioni</th>
            <th>kWh</th>
            <th>Stato</th>
            <th>Azioni</th>
        </tr></thead>
        <tbody>
        @forelse($utenti as $u)
        <tr>
            <td>
                <p style="font-weight:600;color:var(--text);">{{ $u->nome }} {{ $u->cognome }}</p>
                <p style="font-size:0.68rem;color:var(--text-3);">{{ $u->tipo_account }}</p>
            </td>
            <td style="font-size:0.78rem;">{{ $u->email }}</td>
            <td style="font-size:0.78rem;">{{ $u->cellulare }}</td>
            <td><span class="badge-pill badge-purple">{{ $sessioni[$u->id_utente]->tot ?? 0 }}</span></td>
            <td style="color:var(--accent);font-weight:600;font-size:0.8rem;">
                {{ number_format($sessioni[$u->id_utente]->kwh ?? 0, 1) }}
            </td>
            <td>
                @if($u->attivo)
                    <span class="badge-pill badge-green">Attivo</span>
                @else
                    <span class="badge-pill badge-red">Disattivato</span>
                @endif
            </td>
            <td>
                <a href="/admin/utenti/{{ $u->id_utente }}" class="btn btn-outline btn-sm">Dettaglio</a>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-3);padding:2rem;">Nessun utente trovato.</td></tr>
        @endforelse
        </tbody>
    </table>

    {{-- Paginazione --}}
    @if($utenti->hasPages())
    <div style="padding:1rem 1.5rem;display:flex;gap:0.5rem;align-items:center;border-top:1px solid var(--border);">
        @if($utenti->onFirstPage())
            <span class="btn btn-outline btn-sm" style="opacity:0.4;cursor:default;">← Prec</span>
        @else
            <a href="{{ $utenti->previousPageUrl() }}" class="btn btn-outline btn-sm">← Prec</a>
        @endif
        <span style="font-size:0.75rem;color:var(--text-3);">Pagina {{ $utenti->currentPage() }} di {{ $utenti->lastPage() }}</span>
        @if($utenti->hasMorePages())
            <a href="{{ $utenti->nextPageUrl() }}" class="btn btn-outline btn-sm">Succ →</a>
        @else
            <span class="btn btn-outline btn-sm" style="opacity:0.4;cursor:default;">Succ →</span>
        @endif
    </div>
    @endif
</div>

@endsection