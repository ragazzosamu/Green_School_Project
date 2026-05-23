// Equivalente React di backend/src/resources/views/scuola.blade.php
// Chart.js viene caricato dinamicamente da CDN per non aggiungere
// dipendenze al bundle (stesso approccio del Blade).
import { useEffect, useRef, useState, useCallback } from 'react';
import NavBar from '../components/NavBar';
import apiClient from '../api/client';
import './ScuolaPage.css';

const MESI_LABELS = ['Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic'];
const CHART_CDN   = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js';

function fmt(val) {
  if (val === null || val === undefined) return '—';
  return new Intl.NumberFormat('it-IT', { maximumFractionDigits: 0 }).format(val);
}

function sumOrNull(arr) {
  return arr.some((v) => v !== null) ? arr.reduce((a, v) => a + (v ?? 0), 0) : null;
}

function loadChartJs() {
  if (window.Chart) return Promise.resolve(window.Chart);
  return new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${CHART_CDN}"]`);
    if (existing) {
      existing.addEventListener('load',  () => resolve(window.Chart));
      existing.addEventListener('error', reject);
      return;
    }
    const s = document.createElement('script');
    s.src = CHART_CDN;
    s.onload  = () => resolve(window.Chart);
    s.onerror = reject;
    document.head.appendChild(s);
  });
}

export default function ScuolaPage() {
  const [profile,        setProfile]        = useState(null);
  const [profileState,   setProfileState]   = useState('loading');
  const [mesi,           setMesi]           = useState([]);
  const [anniDisp,       setAnniDisp]       = useState([new Date().getFullYear()]);
  const [anno,           setAnno]           = useState(new Date().getFullYear());
  const [chartState,     setChartState]     = useState('loading');

  const consumiCanvasRef = useRef(null);
  const co2CanvasRef     = useRef(null);
  const consumiChartRef  = useRef(null);
  const co2ChartRef      = useRef(null);

  // ── Profilo scuola ──────────────────────────────────────────────────────
  const loadProfile = useCallback(async () => {
    setProfileState('loading');
    try {
      const { data } = await apiClient.get('/school/profile');
      setProfile(data.data ?? data);
      setProfileState('content');
    } catch {
      setProfileState('error');
    }
  }, []);

  // ── Consumi ─────────────────────────────────────────────────────────────
  const loadConsumption = useCallback(async (year) => {
    setChartState('loading');
    try {
      const { data } = await apiClient.get('/school/consumption', { params: { anno: year } });
      const arr = data.data ?? [];
      if (Array.isArray(data.anni_disponibili) && data.anni_disponibili.length > 0) {
        setAnniDisp(data.anni_disponibili);
      }
      setMesi(arr);
      setChartState('content');
    } catch {
      setChartState('error');
    }
  }, []);

  useEffect(() => { loadProfile(); }, [loadProfile]);
  useEffect(() => { loadConsumption(anno); }, [anno, loadConsumption]);

  // ── Render Chart.js quando mesi cambia ──────────────────────────────────
  useEffect(() => {
    if (chartState !== 'content' || mesi.length === 0) return;

    let cancelled = false;
    loadChartJs().then((Chart) => {
      if (cancelled || !Chart) return;

      const elettrico = mesi.map((m) => m.consumo_elettrico_kwh);
      const termico   = mesi.map((m) => m.consumo_termico_kwh);
      const fv        = mesi.map((m) => m.produzione_fv_kwh);
      const co2       = mesi.map((m) => m.co2_emessa_kg);

      consumiChartRef.current?.destroy();
      co2ChartRef.current?.destroy();

      if (consumiCanvasRef.current) {
        consumiChartRef.current = new Chart(consumiCanvasRef.current.getContext('2d'), {
          type: 'line',
          data: {
            labels: MESI_LABELS,
            datasets: [
              {
                label: 'Elettrico (kWh)', data: elettrico,
                borderColor: '#2563EB', backgroundColor: 'rgba(37,99,235,0.06)',
                borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#2563EB',
                tension: 0.4, fill: true, spanGaps: true,
              },
              {
                label: 'Termico (kWh)', data: termico,
                borderColor: '#EA580C', backgroundColor: 'rgba(234,88,12,0.06)',
                borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#EA580C',
                tension: 0.4, fill: true, spanGaps: true,
              },
              {
                label: 'Produzione FV (kWh)', data: fv,
                borderColor: '#2A6B4A', backgroundColor: 'rgba(42,107,74,0.08)',
                borderWidth: 2.5, pointRadius: 4, pointBackgroundColor: '#2A6B4A',
                tension: 0.4, fill: true, spanGaps: true,
              },
            ],
          },
          options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: '#FFFFFF', borderColor: '#E4E2DA', borderWidth: 1,
                titleColor: '#1A1916', bodyColor: '#6B6860', padding: 12,
                callbacks: {
                  label: (ctx) => ctx.parsed.y === null
                    ? `${ctx.dataset.label}: N/D`
                    : `${ctx.dataset.label}: ${fmt(ctx.parsed.y)} kWh`,
                },
              },
            },
            scales: {
              x: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#A8A69E' } },
              y: {
                grid: { color: 'rgba(0,0,0,0.04)' },
                ticks: { color: '#A8A69E', callback: (v) => `${fmt(v)} kWh` },
              },
            },
          },
        });
      }

      if (co2CanvasRef.current) {
        co2ChartRef.current = new Chart(co2CanvasRef.current.getContext('2d'), {
          type: 'bar',
          data: {
            labels: MESI_LABELS,
            datasets: [{
              label: 'CO₂ emessa (kg)', data: co2,
              backgroundColor: co2.map((v) =>
                v === null ? 'rgba(0,0,0,0.05)' :
                v < 2000   ? 'rgba(42,107,74,0.7)' :
                v < 4000   ? 'rgba(234,88,12,0.7)' :
                             'rgba(220,38,38,0.7)'),
              borderRadius: 6, borderSkipped: false,
            }],
          },
          options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
              legend: { display: false },
              tooltip: {
                backgroundColor: '#FFFFFF', borderColor: '#E4E2DA', borderWidth: 1,
                titleColor: '#1A1916', bodyColor: '#6B6860', padding: 12,
                callbacks: {
                  label: (ctx) => ctx.parsed.y === null ? 'N/D' : `${fmt(ctx.parsed.y)} kg CO₂`,
                },
              },
            },
            scales: {
              x: { grid: { display: false }, ticks: { color: '#A8A69E' } },
              y: { grid: { color: 'rgba(0,0,0,0.04)' }, ticks: { color: '#A8A69E', callback: (v) => `${fmt(v)} kg` } },
            },
          },
        });
      }
    });

    return () => {
      cancelled = true;
      consumiChartRef.current?.destroy();
      co2ChartRef.current?.destroy();
      consumiChartRef.current = null;
      co2ChartRef.current     = null;
    };
  }, [mesi, chartState]);

  const elettrico = mesi.map((m) => m.consumo_elettrico_kwh);
  const termico   = mesi.map((m) => m.consumo_termico_kwh);
  const fv        = mesi.map((m) => m.produzione_fv_kwh);
  const co2       = mesi.map((m) => m.co2_emessa_kg);

  // Interventi labels (stesso mapping del Blade)
  const interventoLabels = {
    cappotto_termico:     'Cappotto termico',
    sostituzione_infissi: 'Infissi',
    led_relamping:        'LED relamping',
  };
  let interventi = profile?.interventi_efficientamento ?? {};
  if (typeof interventi === 'string') {
    try { interventi = JSON.parse(interventi); } catch { interventi = {}; }
  }

  return (
    <div className="sc-page">
      <NavBar />

      <main className="sc-main">
        <div className="page-header">
          <div>
            <p className="page-eyebrow">Energia & Sostenibilità</p>
            <h1 className="page-title">{profile?.denominazione ?? 'La mia Scuola'}</h1>
          </div>
          <div className="anno-selector">
            <span className="anno-label">Anno</span>
            <select className="anno-select" value={anno} onChange={(e) => setAnno(Number(e.target.value))}>
              {anniDisp.map((a) => <option key={a} value={a}>{a}</option>)}
            </select>
          </div>
        </div>

        <div className="school-layout">
          {/* Colonna principale: grafici */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.25rem' }}>
            <div className="gs-card">
              <div className="card-header">
                <div>
                  <p className="card-title">Consumi mensili</p>
                  <p className="card-sub">Elettrico · Termico · Produzione fotovoltaica</p>
                </div>
                <div className="chart-legend">
                  <span className="legend-item"><span className="legend-dot" style={{ background: '#2563EB' }} />Elettrico</span>
                  <span className="legend-item"><span className="legend-dot" style={{ background: '#EA580C' }} />Termico</span>
                  <span className="legend-item"><span className="legend-dot" style={{ background: '#2A6B4A' }} />Fotovoltaico</span>
                </div>
              </div>

              {chartState === 'loading' && (
                <div className="chart-wrap"><div className="sk" style={{ height: 300, borderRadius: 10 }} /></div>
              )}

              {chartState === 'error' && (
                <div className="state-box">
                  <div className="state-icon">⚠️</div>
                  <p className="state-title">Dati non disponibili</p>
                  <p className="state-sub">L'endpoint <code>/api/school/consumption</code> non risponde.</p>
                </div>
              )}

              {chartState === 'content' && (
                <>
                  <div className="chart-wrap">
                    <div className="chart-canvas-wrap"><canvas ref={consumiCanvasRef} /></div>
                  </div>

                  <div className="kpi-strip">
                    <div className="kpi-item">
                      <p className="kpi-label">Elettrico totale</p>
                      <p className="kpi-value blue">{fmt(sumOrNull(elettrico))}<span className="kpi-unit">kWh</span></p>
                    </div>
                    <div className="kpi-item">
                      <p className="kpi-label">Termico totale</p>
                      <p className="kpi-value orange">{fmt(sumOrNull(termico))}<span className="kpi-unit">kWh</span></p>
                    </div>
                    <div className="kpi-item">
                      <p className="kpi-label">Produzione FV</p>
                      <p className="kpi-value green">{fmt(sumOrNull(fv))}<span className="kpi-unit">kWh</span></p>
                    </div>
                    <div className="kpi-item">
                      <p className="kpi-label">CO₂ emessa</p>
                      <p className="kpi-value">{fmt(sumOrNull(co2))}<span className="kpi-unit">kg</span></p>
                    </div>
                  </div>
                </>
              )}
            </div>

            <div className="gs-card">
              <div className="card-header">
                <div>
                  <p className="card-title">Emissioni CO₂ mensili</p>
                  <p className="card-sub">kg di CO₂ emessa — già scontata la produzione fotovoltaica</p>
                </div>
              </div>
              {chartState === 'loading' && (
                <div className="co2-chart-wrap"><div className="sk" style={{ height: 160, borderRadius: 10 }} /></div>
              )}
              {chartState === 'content' && (
                <div className="co2-chart-wrap">
                  <div className="co2-canvas-wrap"><canvas ref={co2CanvasRef} /></div>
                </div>
              )}
            </div>
          </div>

          {/* Sidebar */}
          <aside className="sidebar-col">
            <div className="gs-card">
              {profileState === 'loading' && (
                <>
                  <div style={{ background: 'linear-gradient(160deg,#1A3D2A,#2A6B4A)', padding: '1.5rem', height: 120 }} />
                  <div style={{ padding: '1rem 1.5rem', display: 'flex', flexDirection: 'column', gap: 10 }}>
                    <div className="sk" style={{ height: 14, width: '80%' }} />
                    <div className="sk" style={{ height: 14, width: '60%' }} />
                  </div>
                </>
              )}

              {profileState === 'error' && (
                <div className="state-box" style={{ padding: '2rem' }}>
                  <div className="state-icon" style={{ fontSize: '1.8rem' }}>🏫</div>
                  <p className="state-title" style={{ fontSize: '1rem' }}>Profilo non disponibile</p>
                  <p className="state-sub">Esegui il seeder <code>ScuolaSeeder</code>.</p>
                </div>
              )}

              {profileState === 'content' && profile && (
                <>
                  <div className="profile-hero">
                    <div className="profile-icon">🏫</div>
                    <p className="profile-name">{profile.denominazione ?? '—'}</p>
                    <p className="profile-class">Classe energetica {profile.classe_energetica ?? '—'}</p>
                  </div>
                  <div className="profile-stats">
                    <div className="profile-stat-row">
                      <span className="profile-stat-label">Anno costruzione</span>
                      <span className="profile-stat-val">{profile.anno_costruzione ?? '—'}</span>
                    </div>
                    <div className="profile-stat-row">
                      <span className="profile-stat-label">Superficie</span>
                      <span className="profile-stat-val">{profile.superficie_mq ? `${fmt(profile.superficie_mq)} m²` : '—'}</span>
                    </div>
                    <div className="profile-stat-row">
                      <span className="profile-stat-label">Fotovoltaico</span>
                      <span className="profile-stat-val green">{profile.fotovoltaico_kwp ? `${profile.fotovoltaico_kwp} kWp` : 'Non installato'}</span>
                    </div>
                  </div>

                  <div className="interventi-wrap">
                    {Object.entries(interventi).map(([k, done]) => (
                      <span key={k} className={`intervento-chip ${done ? 'done' : 'not'}`}>
                        {done ? '✓ ' : ''}{interventoLabels[k] ?? k}
                      </span>
                    ))}
                  </div>

                  {profile.descrizione && (
                    <div className="desc-section">
                      <p className="desc-text">{profile.descrizione}</p>
                    </div>
                  )}
                </>
              )}
            </div>

            <div className="gs-card" style={{ padding: '1.25rem 1.5rem' }}>
              <p className="rules-title">Metodologia CO₂</p>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                <div className="rule-row">
                  <span className="rule-label">Elettrico (rete)</span>
                  <span className="rule-xp" style={{ color: '#2563EB' }}>0,31 kg/kWh</span>
                </div>
                <div className="rule-row">
                  <span className="rule-label">Gas naturale</span>
                  <span className="rule-xp" style={{ color: '#EA580C' }}>0,20 kg/kWh</span>
                </div>
                <div className="rule-row" style={{ borderBottom: 'none' }}>
                  <span className="rule-label">Fotovoltaico</span>
                  <span className="rule-xp">0 kg/kWh ✓</span>
                </div>
              </div>
              <p style={{ fontSize: '0.68rem', color: '#A8A69E', marginTop: '0.9rem', lineHeight: 1.55 }}>
                Fattori medi Italia — fonte ISPRA 2024.
              </p>
            </div>
          </aside>
        </div>
      </main>
    </div>
  );
}
