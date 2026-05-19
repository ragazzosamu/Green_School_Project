@extends('admin.layout')
@section('page-title', 'Setup stazione')

@section('content')

<style>
    /* ── stili locali del form setup ───────────────────────────── */
    .setup-header { margin-bottom: 1.25rem; }
    .setup-header h2 {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.4rem;
        color: var(--text);
        margin-bottom: 6px;
    }
    .setup-header .meta {
        display: flex; flex-wrap: wrap; gap: 0.6rem 1.2rem;
        font-size: 0.78rem; color: var(--text-2);
    }
    .setup-header .meta code {
        font-family: ui-monospace, SFMono-Regular, monospace;
        font-size: 0.78rem;
        background: var(--surface2);
        padding: 1px 6px;
        border-radius: 6px;
    }

    .form-card { margin-bottom: 1rem; }
    .form-section { padding: 1.25rem 1.5rem; }
    .form-section + .form-section { border-top: 1px solid var(--border); }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.9rem 1.25rem;
    }
    .form-grid .col-2 { grid-column: 1 / -1; }

    .form-field { display: flex; flex-direction: column; gap: 4px; }
    .form-field > label {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--text-3);
    }
    .form-field > input,
    .form-field > select {
        font: inherit;
        font-size: 0.9rem;
        color: var(--text);
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 9px;
        padding: 8px 11px;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .form-field > input:focus,
    .form-field > select:focus {
        outline: none;
        border-color: var(--purple);
        box-shadow: 0 0 0 3px var(--purple-bg);
    }

    .punti-info {
        font-size: 0.78rem;
        color: var(--text-2);
        margin-bottom: 0.7rem;
        line-height: 1.5;
    }

    .punti-grid {
        border: 1px solid var(--border);
        border-radius: 10px;
        overflow: hidden;
    }
    .punto-row {
        display: grid;
        grid-template-columns: 90px 1fr 1.4fr 1fr;
        align-items: stretch;
    }
    .punto-row + .punto-row { border-top: 1px solid var(--border); }
    .punto-row > .cell {
        padding: 0.7rem 0.9rem;
        display: flex;
        flex-direction: column;
        gap: 4px;
        justify-content: center;
    }
    .punto-row > .cell + .cell { border-left: 1px solid var(--border); }
    .punto-row .cell-id {
        background: var(--surface2);
        font-weight: 700;
        color: var(--text);
        font-size: 0.9rem;
        justify-content: center;
    }
    .punto-row .cell-id .sub {
        font-size: 0.62rem;
        font-weight: 600;
        color: var(--text-3);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .punto-row .cell > label {
        font-size: 0.62rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--text-3);
    }
    .punto-row .cell input,
    .punto-row .cell select {
        font: inherit;
        font-size: 0.85rem;
        color: var(--text);
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 7px;
        padding: 6px 9px;
        transition: border-color 0.15s, box-shadow 0.15s;
    }
    .punto-row .cell input:focus,
    .punto-row .cell select:focus {
        outline: none;
        border-color: var(--purple);
        box-shadow: 0 0 0 3px var(--purple-bg);
    }

    .form-actions {
        padding: 1rem 1.5rem;
        background: var(--surface2);
        display: flex;
        gap: 0.6rem;
        justify-content: flex-end;
    }
</style>

<div class="setup-header">
    <h2>Configura stazione</h2>
    <div class="meta">
        <span>MAC: <code>{{ $stazione->id_stazione }}</code></span>
        <span>
            Stato:
            <span class="badge-pill {{ $stazione->stato_setup === 'attiva' ? 'badge-green' : 'badge-yellow' }}">
                {{ $stazione->stato_setup }}
            </span>
        </span>
        <span>Punti dichiarati dall'hardware: <strong>{{ $punti->count() }}</strong></span>
    </div>
</div>

@if ($errors->any())
    <div class="alert-error" style="margin-bottom: 1rem;">
        <ul style="margin: 0; padding-left: 1.1rem;">
            @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
        </ul>
    </div>
@endif

<form method="POST" action="/admin/stazioni/{{ $stazione->id_stazione }}/setup">
    @csrf

    {{-- Dati stazione --}}
    <div class="admin-card form-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Dati stazione</span>
        </div>
        <div class="form-section">
            <div class="form-grid">
                <div class="form-field">
                    <label for="f-nome">Nome</label>
                    <input id="f-nome" name="nome" required maxlength="100"
                           value="{{ old('nome', $stazione->nome) }}"
                           placeholder="Es. Cortile principale">
                </div>

                <div class="form-field">
                    <label for="f-tipoarea">Tipo area</label>
                    <select id="f-tipoarea" name="tipo_area" required>
                        @foreach (['pubblico','privato','aziendale'] as $t)
                            <option value="{{ $t }}" @selected(old('tipo_area', $stazione->tipo_area) === $t)>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-field col-2">
                    <label for="f-indirizzo">Indirizzo</label>
                    <input id="f-indirizzo" name="indirizzo" maxlength="255"
                           value="{{ old('indirizzo', $stazione->indirizzo) }}"
                           placeholder="Via, civico, citta'">
                </div>

                <div class="form-field">
                    <label for="f-lat">Latitudine</label>
                    <input id="f-lat" name="latitudine" type="number" step="0.00000001" min="-90" max="90" required
                           value="{{ old('latitudine', $stazione->latitudine) }}"
                           placeholder="45.67000000">
                </div>

                <div class="form-field">
                    <label for="f-lng">Longitudine</label>
                    <input id="f-lng" name="longitudine" type="number" step="0.00000001" min="-180" max="180" required
                           value="{{ old('longitudine', $stazione->longitudine) }}"
                           placeholder="11.93000000">
                </div>
            </div>
        </div>
    </div>

    {{-- Punti di ricarica --}}
    <div class="admin-card form-card">
        <div class="admin-card-header">
            <span class="admin-card-title">Punti di ricarica</span>
            <span style="font-size: 0.7rem; color: var(--text-3);">
                {{ $punti->count() }} prese dichiarate dall'hardware
            </span>
        </div>
        <div class="form-section">
            <p class="punti-info">
                Gli ID dei punti (1, 2, …) sono stati dichiarati dalla colonnina al momento della
                registrazione e <strong>non sono modificabili</strong> da qui. Completa solo i
                metadati di ogni presa.
            </p>

            <div class="punti-grid">
                @foreach ($punti as $p)
                    @php
                        $old = old("punti.{$p->id_punto}", [
                            'tipo_veicolo'    => $p->tipo_veicolo,
                            'tipo_connettore' => $p->tipo_connettore,
                            'potenza_max_kw'  => $p->potenza_max_kw,
                        ]);
                    @endphp
                    <div class="punto-row">
                        <div class="cell cell-id">
                            <span>#{{ $p->id_punto }}</span>
                            <span class="sub">Presa</span>
                        </div>
                        <div class="cell">
                            <label>Veicolo</label>
                            <select name="punti[{{ $p->id_punto }}][tipo_veicolo]" required>
                                @foreach (['auto','bici','monopattino'] as $v)
                                    <option value="{{ $v }}" @selected(($old['tipo_veicolo'] ?? 'auto') === $v)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="cell">
                            <label>Connettore</label>
                            <input name="punti[{{ $p->id_punto }}][tipo_connettore]" maxlength="30"
                                   value="{{ $old['tipo_connettore'] ?? '' }}"
                                   placeholder="Es. Type 2, Schuko">
                        </div>
                        <div class="cell">
                            <label>Potenza max (kW)</label>
                            <input name="punti[{{ $p->id_punto }}][potenza_max_kw]" type="number" step="0.01" min="0"
                                   value="{{ $old['potenza_max_kw'] ?? '' }}"
                                   placeholder="7.4">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="form-actions">
            <a href="/admin/stazioni" class="btn btn-outline">Annulla</a>
            <button type="submit" class="btn btn-primary">Salva e attiva</button>
        </div>
    </div>
</form>

@endsection
