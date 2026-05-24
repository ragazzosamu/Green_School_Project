// Equivalente React di backend/src/resources/views/station-detail.blade.php
import { useEffect, useState, useCallback } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import NavBar from '../components/NavBar';
import apiClient from '../api/client';
import './StationDetailPage.css';

function classeStato(punto) {
  if (punto.stato_hardware !== 'online') return 'stato-offline';
  return Number(punto.libera) === 1 ? 'stato-libera' : 'stato-occupata';
}

function badgeLabel(punto) {
  if (punto.stato_hardware !== 'online') return { cls: 'offline', txt: 'Offline', dot: 'gray' };
  return Number(punto.libera) === 1
    ? { cls: 'libera', txt: 'Disponibile', dot: 'green' }
    : { cls: 'occupata', txt: 'In uso', dot: 'red' };
}

export default function StationDetailPage() {
  const { id }   = useParams();
  const navigate = useNavigate();

  const [stazione, setStazione] = useState(null);
  const [loading,  setLoading]  = useState(true);
  const [err,      setErr]      = useState('');

  const [showCode, setShowCode] = useState(false);
  const [codice,   setCodice]   = useState('');
  const [sending,  setSending]  = useState(false);
  const [codeErr,  setCodeErr]  = useState('');

  const load = useCallback(async () => {
    setLoading(true);
    setErr('');
    try {
      const { data } = await apiClient.get(`/station/${id}`);
      setStazione(data.data ?? data);
    } catch (e) {
      if (e.response?.status === 404) setErr('Stazione non trovata.');
      else setErr('Impossibile caricare la stazione.');
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => { load(); }, [load]);

  function apriCodice() {
    setCodice('');
    setCodeErr('');
    setShowCode(true);
  }

  async function inviaCodice() {
    const c = codice.trim();
    if (!/^\d{6}$/.test(c)) {
      setCodeErr('Inserisci esattamente 6 cifre.');
      return;
    }
    setSending(true);
    setCodeErr('');
    try {
      const res = await apiClient.post(`/${encodeURIComponent(id)}/verifica-codice`, { codice: c });
      // L'API risponde 202 con id_stazione (codice valido, in attesa cavo)
      if (res.status === 202) {
        const idStazione = res.data?.id_stazione ?? stazione?.id_stazione ?? id;
        navigate(
          `/react/profilo?attesa_stazione=${encodeURIComponent(idStazione)}`,
          { replace: true },
        );
      } else {
        setCodeErr(res.data?.message ?? 'Risposta inattesa dal server.');
      }
    } catch (e) {
      const m = e.response?.data?.error
        ?? e.response?.data?.message
        ?? 'Errore di rete.';
      setCodeErr(m);
    } finally {
      setSending(false);
    }
  }

  // ── Conteggi per i chip in alto (stesso criterio del Blade) ──────────────
  const punti = stazione?.punti_ricarica ?? [];
  const totali  = punti.length;
  const libere  = punti.filter((p) => p.stato_hardware === 'online' && Number(p.libera) === 1).length;
  const inUso   = punti.filter((p) => p.stato_hardware === 'online' && Number(p.libera) === 0).length;

  return (
    <div className="sd-page">
      <NavBar />

      <main className="sd-main">
        <Link to="/react/map" className="back-link">← Torna alla mappa</Link>

        {loading && (
          <div className="state-box"><p className="state-sub">Caricamento…</p></div>
        )}

        {err && !loading && (
          <div className="state-box">
            <div className="state-icon">⚠️</div>
            <p className="state-title">{err}</p>
            <button className="scegli-btn" onClick={load} style={{ marginTop: 12 }}>Riprova</button>
          </div>
        )}

        {stazione && !loading && !err && (
          <div className="station-card">
            <div className="station-header">
              <div className="station-meta">
                <p className="station-eyebrow">Dettaglio stazione</p>
                <h1 className="station-name">{stazione.nome}</h1>
                <p className="station-address">{stazione.indirizzo}</p>
              </div>

              <div className="station-chips">
                <div className="stat-chip">
                  <div className="stat-chip-val">{totali}</div>
                  <div className="stat-chip-label">Totale</div>
                </div>
                <div className="stat-chip">
                  <div className="stat-chip-val green">{libere}</div>
                  <div className="stat-chip-label">Libere</div>
                </div>
                <div className="stat-chip">
                  <div className="stat-chip-val red">{inUso}</div>
                  <div className="stat-chip-label">In uso</div>
                </div>
              </div>
            </div>

            <div className="prese-section">
              <div className="prese-grid">
                {punti.map((p) => {
                  const b = badgeLabel(p);
                  const isOnline = p.stato_hardware === 'online';
                  const isLibera = Number(p.libera) === 1;
                  return (
                    <div key={p.id_punto} className={`presa-card ${classeStato(p)}`}>
                      <div className="presa-info">
                        <div className="presa-name-row">
                          <span className={`status-dot ${b.dot}`} />
                          <span className="presa-name">{p.identificativo_fisico}</span>
                          <span className={`presa-badge ${b.cls}`}>{b.txt}</span>
                        </div>
                        <p className="presa-power">{p.potenza_max_kw} kW</p>
                        <p className="presa-id">{p.id_punto}</p>
                      </div>
                      {isOnline && isLibera && (
                        <button className="scegli-btn" onClick={apriCodice}>Scegli →</button>
                      )}
                    </div>
                  );
                })}
                {punti.length === 0 && (
                  <p className="state-sub">Nessuna presa configurata per questa stazione.</p>
                )}
              </div>
            </div>
          </div>
        )}

        {showCode && (
          <div className="codice-overlay" onClick={(e) => { if (e.target === e.currentTarget) setShowCode(false); }}>
            <div className="codice-card">
              <div className="codice-header">
                <div className="codice-icon-box">#</div>
                <div>
                  <p className="codice-title">Inserisci codice monouso</p>
                  <p className="codice-subtitle">6 cifre mostrate sul display della colonnina (un codice per stazione, valido per 60s)</p>
                </div>
              </div>

              <div className="codice-input-wrap">
                <input
                  className="codice-input"
                  maxLength={6}
                  inputMode="numeric"
                  autoComplete="one-time-code"
                  placeholder="------"
                  value={codice}
                  onChange={(e) => setCodice(e.target.value.replace(/\D/g, ''))}
                  autoFocus
                />
              </div>

              {codeErr && <p className="codice-error">{codeErr}</p>}

              <button className="scegli-btn codice-confirm" onClick={inviaCodice} disabled={sending}>
                {sending ? 'Verifica…' : 'Conferma'}
              </button>
              <button className="codice-cancel-btn" onClick={() => setShowCode(false)}>Annulla</button>
            </div>
          </div>
        )}
      </main>
    </div>
  );
}
