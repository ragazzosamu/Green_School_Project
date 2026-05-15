@extends('layouts.app')

@section('content')

<style>
    @import url('https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600&display=swap');

    :root {
        --bg:         #F7F6F2;
        --surface:    #FFFFFF;
        --surface2:   #F2F1ED;
        --border:     #E4E2DA;
        --text:       #1A1916;
        --text-2:     #6B6860;
        --text-3:     #A8A69E;
        --accent:     #2A6B4A;
        --accent-bg:  #EBF5EF;
        --accent-mid: #4A9B6F;
        --blue:       #2563EB;
        --blue-bg:    #EFF6FF;
        --orange:     #EA580C;
        --orange-bg:  #FFF7ED;
        --shadow-sm:  0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
        --shadow-md:  0 4px 16px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
    }

    /* ── PAGE HEADER ── */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 1.75rem;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .page-eyebrow {
        font-size: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 5px;
    }

    .page-title {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.75rem;
        color: var(--text);
        letter-spacing: -0.03em;
        line-height: 1;
    }

    /* ── ANNO SELECTOR ── */
    .anno-selector {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 100px;
        padding: 6px 14px;
        box-shadow: var(--shadow-sm);
    }

    .anno-label {
        font-size: 0.73rem;
        color: var(--text-3);
        font-weight: 500;
    }

    .anno-select {
        background: none;
        border: none;
        outline: none;
        font-family: 'DM Sans', sans-serif;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--accent);
        cursor: pointer;
        padding: 0 4px;
    }

    /* ── LAYOUT ── */
    .school-layout {
        display: grid;
        grid-template-columns: 1fr 300px;
        gap: 1.25rem;
        align-items: start;
    }

    @media (max-width: 960px) {
        .school-layout { grid-template-columns: 1fr; }
        .sidebar-col { order: -1; }
    }

    /* ── CARD BASE ── */
    .gs-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 18px;
        box-shadow: var(--shadow-md);
        overflow: hidden;
    }

    .card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .card-title {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.05rem;
        color: var(--text);
        letter-spacing: -0.02em;
    }

    .card-sub {
        font-size: 0.73rem;
        color: var(--text-3);
        margin-top: 2px;
    }

    /* ── LEGENDA GRAFICO ── */
    .chart-legend {
        display: flex;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 0.73rem;
        font-weight: 500;
        color: var(--text-2);
    }

    .legend-dot {
        width: 10px; height: 10px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    /* ── CHART WRAPPER ── */
    .chart-wrap {
        padding: 1.5rem;
        position: relative;
    }

    .chart-canvas-wrap {
        position: relative;
        height: 300px;
    }

    /* ── KPI STRIP ── */
    .kpi-strip {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        border-top: 1px solid var(--border);
    }

    @media (max-width: 600px) {
        .kpi-strip { grid-template-columns: 1fr 1fr; }
    }

    .kpi-item {
        padding: 1rem 1.25rem;
        border-right: 1px solid var(--border);
        text-align: center;
    }

    .kpi-item:last-child { border-right: none; }

    .kpi-label {
        font-size: 0.62rem;
        font-weight: 600;
        letter-spacing: 0.09em;
        text-transform: uppercase;
        color: var(--text-3);
        margin-bottom: 5px;
    }

    .kpi-value {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.5rem;
        color: var(--text);
        letter-spacing: -0.02em;
        line-height: 1;
    }

    .kpi-value.green  { color: var(--accent); }
    .kpi-value.blue   { color: var(--blue); }
    .kpi-value.orange { color: var(--orange); }

    .kpi-unit {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.7rem;
        color: var(--text-3);
        font-weight: 400;
        margin-left: 2px;
    }

    /* ── CO2 CARD ── */
    .co2-chart-wrap {
        padding: 1.5rem;
    }

    .co2-canvas-wrap {
        position: relative;
        height: 160px;
    }

    /* ── SIDEBAR: Profilo scuola ── */
    .sidebar-col {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .profile-hero {
        background: linear-gradient(160deg, #1A3D2A 0%, #2A6B4A 100%);
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .profile-hero::before {
        content: '';
        position: absolute;
        top: -40px; right: -40px;
        width: 160px; height: 160px;
        border-radius: 50%;
        background: rgba(255,255,255,0.04);
        pointer-events: none;
    }

    .profile-icon {
        width: 44px; height: 44px;
        background: rgba(255,255,255,0.12);
        border: 1px solid rgba(255,255,255,0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        margin-bottom: 0.9rem;
        position: relative;
        z-index: 1;
    }

    .profile-name {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.15rem;
        color: #fff;
        letter-spacing: -0.02em;
        line-height: 1.2;
        margin-bottom: 3px;
        position: relative;
        z-index: 1;
    }

    .profile-class {
        font-size: 0.72rem;
        color: rgba(255,255,255,0.5);
        font-weight: 500;
        position: relative;
        z-index: 1;
        letter-spacing: 0.04em;
    }

    .profile-stats {
        padding: 1rem 1.5rem;
    }

    .profile-stat-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 8px 0;
        border-bottom: 1px solid var(--border);
        font-size: 0.8rem;
    }

    .profile-stat-row:last-child { border-bottom: none; }

    .profile-stat-label { color: var(--text-3); }
    .profile-stat-val   { font-weight: 600; color: var(--text); }
    .profile-stat-val.green { color: var(--accent); }

    /* Interventi badge */
    .interventi-wrap {
        padding: 0 1.5rem 1.25rem;
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
    }

    .intervento-chip {
        font-size: 0.65rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 100px;
        letter-spacing: 0.04em;
    }

    .intervento-chip.done {
        background: var(--accent-bg);
        color: var(--accent);
        border: 1px solid #C5E0D0;
    }

    .intervento-chip.not {
        background: var(--surface2);
        color: var(--text-3);
        border: 1px solid var(--border);
        text-decoration: line-through;
    }

    /* ── STATI ── */
    .state-box {
        padding: 4rem 2rem;
        text-align: center;
    }

    .state-icon { font-size: 2.5rem; margin-bottom: 1rem; }

    .state-title {
        font-family: 'DM Serif Display', Georgia, serif;
        font-size: 1.2rem;
        color: var(--text);
        margin-bottom: 6px;
    }

    .state-sub { font-size: 0.8rem; color: var(--text-3); line-height: 1.55; }

    /* Skeleton */
    .sk {
        background: linear-gradient(90deg, #F2F1ED 25%, #E8E6E0 50%, #F2F1ED 75%);
        background-size: 200% 100%;
        animation: shimmer 1.4s infinite;
        border-radius: 6px;
    }

    @keyframes shimmer {
        0%   { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* ── DESCRIZIONE SCUOLA ── */
    .desc-section {
        padding: 1rem 1.5rem 1.25rem;
        border-top: 1px solid var(--border);
    }

    .desc-text {
        font-size: 0.78rem;
        color: var(--text-3);
        line-height: 1.65;
    }
</style>

<div class="page-header">
    <div>
        <p class="page-eyebrow">Energia & Sostenibilità</p>
        <h1 class="page-title" id="page-title">La mia Scuola</h1>
    </div>
    <div class="anno-selector">
        <span class="anno-label">Anno</span>
        <select class="anno-select" id="anno-select" onchange="loadConsumption(this.value)">
            <option value="{{ date('Y') }}">{{ date('Y') }}</option>
        </select>
    </div>
</div>

<div class="school-layout">

    {{-- ── COLONNA PRINCIPALE ── --}}
    <div style="display:flex;flex-direction:column;gap:1.25rem;">

        {{-- Grafico consumi --}}
        <div class="gs-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Consumi mensili</p>
                    <p class="card-sub">Elettrico · Termico · Produzione fotovoltaica</p>
                </div>
                <div class="chart-legend">
                    <span class="legend-item">
                        <span class="legend-dot" style="background:#2563EB;"></span>Elettrico
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot" style="background:#EA580C;"></span>Termico
                    </span>
                    <span class="legend-item">
                        <span class="legend-dot" style="background:#2A6B4A;"></span>Fotovoltaico
                    </span>
                </div>
            </div>

            {{-- Skeleton --}}
            <div id="chart-loading" class="chart-wrap">
                <div class="sk" style="height:300px;border-radius:10px;"></div>
            </div>

            {{-- Stato errore --}}
            <div id="chart-error" style="display:none;">
                <div class="state-box">
                    <div class="state-icon">⚠️</div>
                    <p class="state-title">Dati non disponibili</p>
                    <p class="state-sub">L'endpoint <code>/api/school/consumption</code> non è raggiungibile.<br>Verifica che il task 3.7 sia completato e il seeder eseguito.</p>
                </div>
            </div>

            {{-- Grafico reale --}}
            <div id="chart-content" style="display:none;">
                <div class="chart-wrap">
                    <div class="chart-canvas-wrap">
                        <canvas id="consumiChart"></canvas>
                    </div>
                </div>

                {{-- KPI annuali --}}
                <div class="kpi-strip" id="kpi-strip">
                    <div class="kpi-item">
                        <p class="kpi-label">Elettrico totale</p>
                        <p class="kpi-value blue" id="kpi-elettrico">—<span class="kpi-unit">kWh</span></p>
                    </div>
                    <div class="kpi-item">
                        <p class="kpi-label">Termico totale</p>
                        <p class="kpi-value orange" id="kpi-termico">—<span class="kpi-unit">kWh</span></p>
                    </div>
                    <div class="kpi-item">
                        <p class="kpi-label">Produzione FV</p>
                        <p class="kpi-value green" id="kpi-fv">—<span class="kpi-unit">kWh</span></p>
                    </div>
                    <div class="kpi-item">
                        <p class="kpi-label">CO₂ emessa</p>
                        <p class="kpi-value" id="kpi-co2">—<span class="kpi-unit">kg</span></p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Grafico CO2 --}}
        <div class="gs-card">
            <div class="card-header">
                <div>
                    <p class="card-title">Emissioni CO₂ mensili</p>
                    <p class="card-sub">kg di CO₂ emessa — già scontata la produzione fotovoltaica</p>
                </div>
            </div>

            <div id="co2-loading" class="co2-chart-wrap">
                <div class="sk" style="height:160px;border-radius:10px;"></div>
            </div>

            <div id="co2-content" style="display:none;" class="co2-chart-wrap">
                <div class="co2-canvas-wrap">
                    <canvas id="co2Chart"></canvas>
                </div>
            </div>
        </div>

    </div>

    {{-- ── SIDEBAR ── --}}
    <div class="sidebar-col">

        {{-- Profilo scuola --}}
        <div class="gs-card">

            {{-- Skeleton --}}
            <div id="profile-loading">
                <div style="background:linear-gradient(160deg,#1A3D2A,#2A6B4A);padding:1.5rem;height:120px;"></div>
                <div style="padding:1rem 1.5rem;display:flex;flex-direction:column;gap:10px;">
                    <div class="sk" style="height:14px;width:80%;"></div>
                    <div class="sk" style="height:14px;width:60%;"></div>
                    <div class="sk" style="height:14px;width:70%;"></div>
                    <div class="sk" style="height:14px;width:50%;"></div>
                </div>
            </div>

            {{-- Errore profilo --}}
            <div id="profile-error" style="display:none;">
                <div class="state-box" style="padding:2rem;">
                    <div class="state-icon" style="font-size:1.8rem;">🏫</div>
                    <p class="state-title" style="font-size:1rem;">Profilo non disponibile</p>
                    <p class="state-sub">Esegui il seeder <code>ScuolaSeeder</code>.</p>
                </div>
            </div>

            {{-- Contenuto reale --}}
            <div id="profile-content" style="display:none;">
                <div class="profile-hero">
                    <div class="profile-icon">🏫</div>
                    <p class="profile-name" id="school-name">—</p>
                    <p class="profile-class" id="school-class">—</p>
                </div>
                <div class="profile-stats">
                    <div class="profile-stat-row">
                        <span class="profile-stat-label">Anno costruzione</span>
                        <span class="profile-stat-val" id="school-anno">—</span>
                    </div>
                    <div class="profile-stat-row">
                        <span class="profile-stat-label">Superficie</span>
                        <span class="profile-stat-val" id="school-sup">—</span>
                    </div>
                    <div class="profile-stat-row">
                        <span class="profile-stat-label">Fotovoltaico</span>
                        <span class="profile-stat-val green" id="school-fv">—</span>
                    </div>
                </div>
                <div class="interventi-wrap" id="interventi-wrap"></div>
                <div class="desc-section" id="desc-section" style="display:none;">
                    <p class="desc-text" id="school-desc"></p>
                </div>
            </div>

        </div>

        {{-- Info metodologia ── --}}
        <div class="gs-card" style="padding:1.25rem 1.5rem;">
            <p style="font-size:0.68rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--text-3);margin-bottom:0.9rem;">Metodologia CO₂</p>
            <div style="display:flex;flex-direction:column;gap:6px;">
                <div style="display:flex;justify-content:space-between;font-size:0.78rem;padding:6px 0;border-bottom:1px solid var(--border);">
                    <span style="color:var(--text-2);">Elettrico (rete)</span>
                    <span style="font-weight:600;color:var(--blue);">0,31 kg/kWh</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.78rem;padding:6px 0;border-bottom:1px solid var(--border);">
                    <span style="color:var(--text-2);">Gas naturale</span>
                    <span style="font-weight:600;color:var(--orange);">0,20 kg/kWh</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:0.78rem;padding:6px 0;">
                    <span style="color:var(--text-2);">Fotovoltaico</span>
                    <span style="font-weight:600;color:var(--accent);">0 kg/kWh ✓</span>
                </div>
            </div>
            <p style="font-size:0.68rem;color:var(--text-3);margin-top:0.9rem;line-height:1.55;">Fattori medi Italia — fonte ISPRA 2024.</p>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<script>
    const API_TOKEN = "{{ session('api_token') }}";
    const MESI_LABELS = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];

    let consumiChartInstance = null;
    let co2ChartInstance     = null;

    // ── UTILITIES ──
    function fmt(val) {
        if (val === null || val === undefined) return '—';
        return new Intl.NumberFormat('it-IT', { maximumFractionDigits: 0 }).format(val);
    }

    function setChartState(state) {
        // state: 'loading' | 'error' | 'content'
        document.getElementById('chart-loading').style.display  = state === 'loading'  ? '' : 'none';
        document.getElementById('chart-error').style.display    = state === 'error'    ? '' : 'none';
        document.getElementById('chart-content').style.display  = state === 'content'  ? '' : 'none';
        document.getElementById('co2-loading').style.display    = state === 'loading'  ? '' : 'none';
        document.getElementById('co2-content').style.display    = state === 'content'  ? '' : 'none';
    }

    function setProfileState(state) {
        document.getElementById('profile-loading').style.display = state === 'loading' ? '' : 'none';
        document.getElementById('profile-error').style.display   = state === 'error'   ? '' : 'none';
        document.getElementById('profile-content').style.display = state === 'content' ? '' : 'none';
    }

    // ── PROFILO SCUOLA ──
    async function loadProfile() {
        setProfileState('loading');
        try {
            const res = await fetch('/api/school/profile', {
                headers: { 'Authorization': 'Bearer ' + API_TOKEN, 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            const s = json.data;

            document.getElementById('page-title').textContent   = s.denominazione ?? 'La mia Scuola';
            document.getElementById('school-name').textContent  = s.denominazione ?? '—';
            document.getElementById('school-class').textContent = 'Classe energetica ' + (s.classe_energetica ?? '—');
            document.getElementById('school-anno').textContent  = s.anno_costruzione ?? '—';
            document.getElementById('school-sup').textContent   = s.superficie_mq ? fmt(s.superficie_mq) + ' m²' : '—';
            document.getElementById('school-fv').textContent    = s.fotovoltaico_kwp ? s.fotovoltaico_kwp + ' kWp' : 'Non installato';

            // Interventi efficientamento
            const wrap = document.getElementById('interventi-wrap');
            wrap.innerHTML = '';
            const labels = {
                'cappotto_termico':     'Cappotto termico',
                'sostituzione_infissi': 'Infissi',
                'led_relamping':        'LED relamping',
            };
            const interventi = s.interventi_efficientamento ?? {};
            const interventiObj = typeof interventi === 'string' ? JSON.parse(interventi) : interventi;
            Object.entries(interventiObj).forEach(([key, done]) => {
                const chip = document.createElement('span');
                chip.className = 'intervento-chip ' + (done ? 'done' : 'not');
                chip.textContent = (done ? '✓ ' : '') + (labels[key] ?? key);
                wrap.appendChild(chip);
            });

            // Descrizione
            if (s.descrizione) {
                document.getElementById('school-desc').textContent = s.descrizione;
                document.getElementById('desc-section').style.display = '';
            }

            setProfileState('content');
        } catch (err) {
            console.warn('Profilo scuola non disponibile:', err.message);
            setProfileState('error');
        }
    }

    // ── CONSUMI ──
    async function loadConsumption(anno) {
        setChartState('loading');
        try {
            const res = await fetch('/api/school/consumption?anno=' + anno, {
                headers: { 'Authorization': 'Bearer ' + API_TOKEN, 'Accept': 'application/json' }
            });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            const mesi = json.data; // array di 12

            // Aggiorna selettore anni
            if (json.anni_disponibili && json.anni_disponibili.length > 0) {
                const sel = document.getElementById('anno-select');
                const currentVal = sel.value;
                sel.innerHTML = '';
                json.anni_disponibili.forEach(a => {
                    const opt = document.createElement('option');
                    opt.value = a;
                    opt.textContent = a;
                    if (String(a) === String(currentVal)) opt.selected = true;
                    sel.appendChild(opt);
                });
            }

            const elettrico = mesi.map(m => m.consumo_elettrico_kwh);
            const termico   = mesi.map(m => m.consumo_termico_kwh);
            const fv        = mesi.map(m => m.produzione_fv_kwh);
            const co2       = mesi.map(m => m.co2_emessa_kg);

            // KPI
            const sumOrNull = arr => arr.some(v => v !== null)
                ? arr.reduce((acc, v) => acc + (v ?? 0), 0)
                : null;

            document.getElementById('kpi-elettrico').innerHTML = fmt(sumOrNull(elettrico)) + '<span class="kpi-unit">kWh</span>';
            document.getElementById('kpi-termico').innerHTML   = fmt(sumOrNull(termico))   + '<span class="kpi-unit">kWh</span>';
            document.getElementById('kpi-fv').innerHTML        = fmt(sumOrNull(fv))        + '<span class="kpi-unit">kWh</span>';
            document.getElementById('kpi-co2').innerHTML       = fmt(sumOrNull(co2))       + '<span class="kpi-unit">kg</span>';

            // Distruggi vecchi grafici se esistono
            if (consumiChartInstance) consumiChartInstance.destroy();
            if (co2ChartInstance)     co2ChartInstance.destroy();

            // ── GRAFICO CONSUMI ──
            const ctxConsumi = document.getElementById('consumiChart').getContext('2d');
            consumiChartInstance = new Chart(ctxConsumi, {
                type: 'line',
                data: {
                    labels: MESI_LABELS,
                    datasets: [
                        {
                            label: 'Elettrico (kWh)',
                            data: elettrico,
                            borderColor: '#2563EB',
                            backgroundColor: 'rgba(37,99,235,0.06)',
                            borderWidth: 2.5,
                            pointRadius: 4,
                            pointBackgroundColor: '#2563EB',
                            pointHoverRadius: 6,
                            tension: 0.4,
                            fill: true,
                            spanGaps: true,
                        },
                        {
                            label: 'Termico (kWh)',
                            data: termico,
                            borderColor: '#EA580C',
                            backgroundColor: 'rgba(234,88,12,0.06)',
                            borderWidth: 2.5,
                            pointRadius: 4,
                            pointBackgroundColor: '#EA580C',
                            pointHoverRadius: 6,
                            tension: 0.4,
                            fill: true,
                            spanGaps: true,
                        },
                        {
                            label: 'Produzione FV (kWh)',
                            data: fv,
                            borderColor: '#2A6B4A',
                            backgroundColor: 'rgba(42,107,74,0.08)',
                            borderWidth: 2.5,
                            pointRadius: 4,
                            pointBackgroundColor: '#2A6B4A',
                            pointHoverRadius: 6,
                            tension: 0.4,
                            fill: true,
                            spanGaps: true,
                        },
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#FFFFFF',
                            borderColor: '#E4E2DA',
                            borderWidth: 1,
                            titleColor: '#1A1916',
                            bodyColor: '#6B6860',
                            padding: 12,
                            titleFont: { family: "'DM Sans', sans-serif", weight: '600', size: 13 },
                            bodyFont: { family: "'DM Sans', sans-serif", size: 12 },
                            callbacks: {
                                label: ctx => {
                                    if (ctx.parsed.y === null) return ctx.dataset.label + ': N/D';
                                    return ctx.dataset.label + ': ' + fmt(ctx.parsed.y) + ' kWh';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: { font: { family: "'DM Sans', sans-serif", size: 11 }, color: '#A8A69E' }
                        },
                        y: {
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: {
                                font: { family: "'DM Sans', sans-serif", size: 11 },
                                color: '#A8A69E',
                                callback: v => fmt(v) + ' kWh'
                            }
                        }
                    }
                }
            });

            // ── GRAFICO CO2 ──
            const ctxCo2 = document.getElementById('co2Chart').getContext('2d');
            co2ChartInstance = new Chart(ctxCo2, {
                type: 'bar',
                data: {
                    labels: MESI_LABELS,
                    datasets: [{
                        label: 'CO₂ emessa (kg)',
                        data: co2,
                        backgroundColor: co2.map(v =>
                            v === null ? 'rgba(0,0,0,0.05)' :
                            v < 2000   ? 'rgba(42,107,74,0.7)' :
                            v < 4000   ? 'rgba(234,88,12,0.7)' :
                                         'rgba(220,38,38,0.7)'
                        ),
                        borderRadius: 6,
                        borderSkipped: false,
                        spanGaps: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#FFFFFF',
                            borderColor: '#E4E2DA',
                            borderWidth: 1,
                            titleColor: '#1A1916',
                            bodyColor: '#6B6860',
                            padding: 12,
                            titleFont: { family: "'DM Sans', sans-serif", weight: '600', size: 13 },
                            bodyFont: { family: "'DM Sans', sans-serif", size: 12 },
                            callbacks: {
                                label: ctx => ctx.parsed.y === null
                                    ? 'N/D'
                                    : fmt(ctx.parsed.y) + ' kg CO₂'
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: "'DM Sans', sans-serif", size: 11 }, color: '#A8A69E' }
                        },
                        y: {
                            grid: { color: 'rgba(0,0,0,0.04)' },
                            ticks: {
                                font: { family: "'DM Sans', sans-serif", size: 11 },
                                color: '#A8A69E',
                                callback: v => fmt(v) + ' kg'
                            }
                        }
                    }
                }
            });

            setChartState('content');

        } catch (err) {
            console.warn('Consumi scuola non disponibili:', err.message);
            setChartState('error');
        }
    }

    // ── INIT ──
    const annoCorrente = new Date().getFullYear();
    loadProfile();
    loadConsumption(annoCorrente);
</script>

@endsection