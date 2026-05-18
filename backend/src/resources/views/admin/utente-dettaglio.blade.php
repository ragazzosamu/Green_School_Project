@extends('admin.layout')
@section('page-title', 'Dettaglio Utente')

@section('content')

<div style="margin-bottom:1.25rem;">
    <a href="/admin/utenti" style="font-size:0.78rem;color:var(--text-3);text-decoration:none;">← Torna agli utenti</a>
</div>

<div style="display:grid;grid-template-columns:340px 1fr;gap:1.25rem;align-items:start;">

    {{-- Colonna sinistra: info + azioni --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        {{-- Info utente --}}
        <div class="admin-card">
            <div class="admin-card-header">
                <span class="admin-card-title">Informazioni</span>
                @if($utente->attivo)
                    <span class="badge-pill badge-green">Attivo</span>
                @else
                    <span class="badge-pill badge-red">Disattivato</span>
                @endif
            </div>
            <div style="padding:1.25rem 1.5rem;">
                <div style="width:52px;height:52px;border-radius:12px;background:var(--purple-bg);border:1px solid var(--purple-border);display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:1rem;">👤</div>
                <p style="font-family:'DM Serif Display',Georgia,serif;font-size:1.3rem;color:var(--text);margin-bottom:2px;">{{ $utente->nome }} {{ $utente->cognome }}</p>
                <p style="font-size:0.78rem;color:var(--text-3);">{{ $utente->email }}</p>
                <p style="font-size:0.78rem;color:var(--text-3);">{{ $utente->cellulare }}</p>

                <div style="margin-top:1rem;display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:0.75rem;">
                        <p style="font-size:0.6rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-3);margin-bottom:3px;">Sessioni</p>
                        <p style="font-family:'DM Serif Display',Georgia,serif;font-size:1.4rem;color:var(--purple);">{{ $stats['sessioni_totali'] }}</p>
                    </div>
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:0.75rem;">
                        <p style="font-size:0.6rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-3);margin-bottom:3px;">kWh</p>
                        <p style="font-family:'DM Serif Display',Georgia,serif;font-size:1.4rem;color:var(--accent);">{{ number_format($stats['kwh_totali'],1) }}</p>
                    </div>
                    <div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:0.75rem;grid-column:span 2;">
                        <p style="font-size:0.6rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-3);margin-bottom:3px;">Spesa totale</p>
                        <p style="font-family:'DM Serif Display',Georgia,serif;font-size:1.4rem;color:var(--text);">€ {{ number_format($stats['spesa_totale'],2) }}</p>
                    </div>
                </div>

                @if($profilo)
                <div style="margin-top:0.75rem;background:var(--purple-bg);border:1px solid var(--purple-border);border-radius:10px;padding:0.75rem;">
                    <p style="font-size:0.6rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--purple);margin-bottom:6px;">Gamification</p>
                    <p style="font-size:0.8rem;color:var(--text);">XP: <strong>{{ $profilo->xp_totali }}</strong> · Livello {{ $profilo->livello ?? floor(sqrt($profilo->xp_totali/100)) }}</p>
                    <p style="font-size:0.8rem;color:var(--text);">CO₂ risparmiata: <strong>{{ number_format($profilo->co2_risparmiata_kg,1) }} kg</strong></p>
                    <p style="font-size:0.8rem;color:var(--text);">Streak: <strong>{{ $profilo->streak_giorni }} gg</strong></p>
                </div>
                @endif
            </div>
        </div>

        {{-- Badge --}}
        @if($badge->count())
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Badge sbloccati</span></div>
            <div style="padding:1rem 1.5rem;display:flex;flex-direction:column;gap:0.5rem;">
                @foreach($badge as $b)
                <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;">
                    <span>{{ $b->icona_emoji }}</span>
                    <span style="font-weight:600;color:var(--text);">{{ $b->nome }}</span>
                    <span style="color:var(--text-3);font-size:0.7rem;margin-left:auto;">{{ \Carbon\Carbon::parse($b->data_sblocco)->format('d/m/Y') }}</span>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Azioni --}}
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Azioni rapide</span></div>
            <div style="padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:0.75rem;">
                {{-- Toggle attivo --}}
                <form method="POST" action="/admin/utenti/{{ $utente->id_utente }}/toggle">
                    @csrf
                    <button type="submit" class="btn {{ $utente->attivo ? 'btn-danger' : 'btn-outline' }}" style="width:100%;justify-content:center;">
                        {{ $utente->attivo ? '🔒 Disattiva account' : '✅ Riattiva account' }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Reset password --}}
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Reset password</span></div>
            <form method="POST" action="/admin/utenti/{{ $utente->id_utente }}/reset" style="padding:1.25rem 1.5rem;">
                @csrf
                <div class="form-group">
                    <label class="form-label">Nuova password</label>
                    <input type="password" name="nuova_password" class="form-input" placeholder="Min. 8 caratteri" required minlength="8">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Aggiorna password</button>
            </form>
        </div>
    </div>

    {{-- Colonna destra: modifica + sessioni --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        {{-- Modifica dati --}}
        <div class="admin-card">
            <div class="admin-card-header"><span class="admin-card-title">Modifica dati</span></div>
            <form method="POST" action="/admin/utenti/{{ $utente->id_utente }}" style="padding:1.25rem 1.5rem;">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" value="{{ $utente->nome }}" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cognome</label>
                        <input type="text" name="cognome" value="{{ $utente->cognome }}" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" value="{{ $utente->email }}" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Cellulare</label>
                        <input type="text" name="cellulare" value="{{ $utente->cellulare }}" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo account</label>
                        <select name="tipo_account" class="form-input">
                            @foreach(['completo','badge_anonimo','ospite'] as $tipo)
                            <option value="{{ $tipo }}" {{ $utente->tipo_account === $tipo ? 'selected' : '' }}>{{ ucfirst($tipo) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.82rem;color:var(--text-2);">
                            <input type="hidden" name="attivo" value="0">
                            <input type="checkbox" name="attivo" value="1" {{ $utente->attivo ? 'checked' : '' }} style="width:16px;height:16px;accent-color:var(--purple);">
                            Account attivo
                        </label>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">Salva modifiche</button>
            </form>
        </div>

        {{-- Storico sessioni --}}
        <div class="admin-card">
            <div class="admin-card-header">
                <span class="admin-card-title">Storico sessioni</span>
                <span style="font-size:0.75rem;color:var(--text-3);">ultime {{ $sessioni->count() }}</span>
            </div>
            <table class="admin-table">
                <thead><tr>
                    <th>Data inizio</th>
                    <th>Durata</th>
                    <th>kWh</th>
                    <th>Costo</th>
                    <th>Stato</th>
                </tr></thead>
                <tbody>
                @forelse($sessioni as $s)
                <tr>
                    <td style="font-size:0.75rem;">{{ \Carbon\Carbon::parse($s->data_inizio)->format('d/m/Y H:i') }}</td>
                    <td style="font-size:0.75rem;">
                        @if($s->data_fine)
                            @php
                                $sec = strtotime($s->data_fine) - strtotime($s->data_inizio);
                                $h = floor($sec/3600); $m = floor(($sec%3600)/60);
                            @endphp
                            {{ $h }}h {{ $m }}m
                        @else
                            In corso…
                        @endif
                    </td>
                    <td>{{ $s->quantita_kwh ? number_format($s->quantita_kwh,2) : '—' }}</td>
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
                <tr><td colspan="5" style="text-align:center;color:var(--text-3);padding:1.5rem;">Nessuna sessione.</td></tr>
                @endforelse
                </tbody>
            </table>
            @if($sessioni->hasPages())
            <div style="padding:0.75rem 1.5rem;border-top:1px solid var(--border);display:flex;gap:0.5rem;">
                @if(!$sessioni->onFirstPage())<a href="{{ $sessioni->previousPageUrl() }}" class="btn btn-outline btn-sm">← Prec</a>@endif
                @if($sessioni->hasMorePages())<a href="{{ $sessioni->nextPageUrl() }}" class="btn btn-outline btn-sm">Succ →</a>@endif
            </div>
            @endif
        </div>
    </div>
</div>

@endsection