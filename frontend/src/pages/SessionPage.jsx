import { useEffect, useState, useCallback } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import { useSessionChannel } from '../hooks/useSessionChannel';
import apiClient from '../api/client';
import './SessionPage.css';

function fmtKwh(val) {
  const n = parseFloat(val) || 0;
  return n.toFixed(3);
}

function fmtDuration(startIso) {
  if (!startIso) return '—';
  const diff = Math.floor((Date.now() - new Date(startIso).getTime()) / 1000);
  const h = Math.floor(diff / 3600);
  const m = Math.floor((diff % 3600) / 60);
  const s = diff % 60;
  if (h > 0) return `${h}h ${m.toString().padStart(2, '0')}m`;
  return `${m.toString().padStart(2, '0')}m ${s.toString().padStart(2, '0')}s`;
}

function StatCard({ icon, label, value, unit, accent }) {
  return (
    <div className={`stat-card${accent ? ' stat-card--accent' : ''}`}>
      <div className="stat-icon">{icon}</div>
      <div className="stat-body">
        <p className="stat-label">{label}</p>
        <p className="stat-value">
          {value}
          {unit && <span className="stat-unit"> {unit}</span>}
        </p>
      </div>
    </div>
  );
}

export default function SessionPage() {
  const { user, token, logout } = useAuth();
  const navigate = useNavigate();

  const [rawSession, setRawSession] = useState(null);
  const [loading, setLoading]       = useState(true);
  const [fetchError, setFetchError] = useState('');
  const [stopping, setStopping]     = useState(false);
  const [stopError, setStopError]   = useState('');
  const [elapsed, setElapsed]       = useState('—');

  // Hook real-time
  const { session, setSession } = useSessionChannel(
    user?.id_utente,
    token,
    rawSession,
  );

  // Fetch sessione attiva
  const loadSession = useCallback(async () => {
    setLoading(true);
    setFetchError('');
    try {
      const { data } = await apiClient.get('/me/sessione-attiva');
      // L'API risponde sempre 200: { attiva: false } oppure { attiva: true, id_sessione, ... }
      if (!data.attiva) {
        setRawSession(null);
        setFetchError('Nessuna sessione attiva al momento.');
      } else {
        // Normalizza i campi: l'API usa kwh_erogati, il componente usa kwh
        setRawSession({
          ...data,
          kwh: data.kwh_erogati ?? 0,
        });
      }
    } catch (err) {
      setRawSession(null);
      setFetchError('Impossibile caricare la sessione. Riprova.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadSession(); }, [loadSession]);

  // Timer elapsed
  useEffect(() => {
    if (!session?.data_inizio) return;
    const id = setInterval(() => setElapsed(fmtDuration(session.data_inizio)), 1000);
    setElapsed(fmtDuration(session.data_inizio));
    return () => clearInterval(id);
  }, [session?.data_inizio]);

  async function handleStop() {
    if (!session?.id_sessione) return;
    setStopping(true);
    setStopError('');
    try {
      await apiClient.post(`/session/${session.id_sessione}/stop`);
      navigate('/react/map');
    } catch (err) {
      setStopError(
        err.response?.data?.message ?? 'Errore durante l\'interruzione. Riprova.',
      );
    } finally {
      setStopping(false);
    }
  }

  async function handleLogout() {
    await logout();
    window.location.href = '/react/login';
  }

  return (
    <div className="session-page">
      {/* Header */}
      <header className="sp-header">
        <Link to="/react/map" className="logo">
          <div className="logo-mark">🌱</div>
          <span className="logo-text">GreenSchool</span>
        </Link>
        <nav className="sp-nav">
          <Link to="/react/map"      className="nav-link">🗺️ Mappa</Link>
          <Link to="/react/profilo"  className="nav-link">👤 Profilo</Link>
          {user?.ruolo === 'admin' && (
            <Link to="/react/admin" className="nav-link" style={{ color: '#7C3AED' }}>⚙️ Admin</Link>
          )}
          <button onClick={handleLogout} className="logout-btn">Esci</button>
        </nav>
      </header>

      <main className="sp-main">
        <div className="page-header">
          <div>
            <p className="page-eyebrow">Ricarica in corso</p>
            <h1 className="page-title">Sessione attiva</h1>
          </div>
          {session && (
            <div className="live-pill">
              <span className="live-dot" />
              Live
            </div>
          )}
        </div>

        {/* Stati */}
        {loading && (
          <div className="sp-state">
            <span className="sp-spinner" />
            <p>Caricamento sessione…</p>
          </div>
        )}

        {!loading && fetchError && (
          <div className="sp-empty-card">
            <div className="sp-empty-icon">⚡</div>
            <p className="sp-empty-title">{fetchError}</p>
            <p className="sp-empty-sub">
              Avvia una sessione scansionando il badge NFC oppure usando il codice monouso
              mostrato dalla colonnina.
            </p>
            <Link to="/react/map" className="sp-cta-btn">Vai alla mappa →</Link>
          </div>
        )}

        {!loading && session && (
          <>
            {/* Info stazione */}
            <div className="station-info-bar">
              <span className="sib-icon">📍</span>
              <div>
                <p className="sib-name">
                  {session.stazione?.nome ?? session.id_stazione ?? '—'}
                </p>
                {session.stazione?.indirizzo && (
                  <p className="sib-address">{session.stazione.indirizzo}</p>
                )}
              </div>
              <div className="sib-right">
                <p className="sib-meta">Punto {session.id_punto ?? '—'}</p>
                <p className="sib-meta">Avvio: {session.metodo_avvio ?? '—'}</p>
              </div>
            </div>

            {/* Stats grid */}
            <div className="stats-grid">
              <StatCard
                icon="⚡"
                label="Energia erogata"
                value={fmtKwh(session.kwh)}
                unit="kWh"
                accent
              />
              <StatCard
                icon="⏱️"
                label="Durata"
                value={elapsed}
              />
              <StatCard
                icon="🔋"
                label="Potenza istantanea"
                value={session.potenza_kw ?? '—'}
                unit={session.potenza_kw ? 'kW' : ''}
              />
              <StatCard
                icon="⚡"
                label="Tensione"
                value={session.voltaggio ? `${session.voltaggio} V` : '—'}
              />
            </div>

            {/* Grafico kWh (progress bar visuale) */}
            <div className="kwh-bar-card">
              <div className="kwh-bar-header">
                <span className="kwh-bar-label">Progressione energia</span>
                <span className="kwh-bar-value">{fmtKwh(session.kwh)} kWh</span>
              </div>
              <div className="kwh-bar-track">
                <div
                  className="kwh-bar-fill"
                  style={{
                    width: `${Math.min(100, (parseFloat(session.kwh) || 0) * 10)}%`,
                  }}
                />
              </div>
              <p className="kwh-bar-hint">
                I dati si aggiornano in tempo reale ogni ~5 secondi via WebSocket
              </p>
            </div>

            {/* Stop */}
            {stopError && (
              <p className="stop-error" role="alert">{stopError}</p>
            )}
            <div className="stop-wrap">
              <button
                className="stop-btn"
                onClick={handleStop}
                disabled={stopping}
              >
                {stopping ? 'Interruzione…' : '⏹ Termina sessione'}
              </button>
              <p className="stop-hint">
                La sessione verrà terminata e i kWh registrati nel tuo profilo.
              </p>
            </div>
          </>
        )}
      </main>
    </div>
  );
}