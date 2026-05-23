import { useEffect, useState, useRef } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import NavBar from '../components/NavBar';
import apiClient from '../api/client';
import './ProfilePage.css';

/* ── Helpers ── */
function fmtKwh(val) {
  const n = parseFloat(val) || 0;
  return n >= 1000
    ? `${(n / 1000).toFixed(2)} MWh`
    : `${n.toFixed(2)} kWh`;
}

function xpToNextLevel(currentXp, level) {
  // Soglie lineari: ogni livello richiede 100 XP aggiuntivi
  const needed = level * 100;
  return { needed, progress: Math.min(1, (currentXp % 100) / 100) };
}

/* ── Componenti interni ── */
function XpBar({ xp, livello }) {
  const { progress } = xpToNextLevel(xp, livello);
  return (
    <div className="xp-bar-wrap">
      <div className="xp-bar-track">
        <div className="xp-bar-fill" style={{ width: `${progress * 100}%` }} />
      </div>
      <div className="xp-bar-labels">
        <span>Livello {livello}</span>
        <span>{xp} XP totali</span>
      </div>
    </div>
  );
}

function BadgeCard({ badge, earned }) {
  return (
    <div className={`badge-card${earned ? ' badge-card--earned' : ' badge-card--locked'}`}>
      <div className="badge-icon">
        {earned ? (badge.icona ?? '🏅') : '🔒'}
      </div>
      <div className="badge-info">
        <p className="badge-name">{badge.nome}</p>
        <p className="badge-desc">{badge.descrizione ?? ''}</p>
        {earned && badge.data_ottenimento && (
          <p className="badge-date">
            {new Date(badge.data_ottenimento).toLocaleDateString('it-IT')}
          </p>
        )}
      </div>
    </div>
  );
}

function LeaderboardRow({ entry, index, isMe }) {
  const medals = ['🥇', '🥈', '🥉'];
  return (
    <div className={`lb-row${isMe ? ' lb-row--me' : ''}`}>
      <span className="lb-rank">
        {index < 3 ? medals[index] : `#${index + 1}`}
      </span>
      <span className="lb-name">
        {entry.nome ?? entry.email ?? `Utente ${index + 1}`}
        {isMe && <span className="lb-you">Tu</span>}
      </span>
      <span className="lb-xp">{entry.xp_totali ?? entry.punti_totali ?? 0} XP</span>
    </div>
  );
}

function SessionRow({ session }) {
  const kwh = parseFloat(session.quantita_kwh) || 0;
  const date = session.data_inizio
    ? new Date(session.data_inizio).toLocaleDateString('it-IT', {
        day: '2-digit', month: 'short', year: 'numeric',
      })
    : '—';
  const duration = session.data_inizio && session.data_fine
    ? (() => {
        const diff = Math.floor(
          (new Date(session.data_fine) - new Date(session.data_inizio)) / 60000,
        );
        return diff < 60 ? `${diff} min` : `${Math.floor(diff / 60)}h ${diff % 60}m`;
      })()
    : '—';

  return (
    <div className="session-row">
      <div className="session-row-left">
        <p className="session-row-date">{date}</p>
        <p className="session-row-station">
          {session.stazione?.nome ?? session.id_stazione ?? '—'}
        </p>
      </div>
      <div className="session-row-right">
        <p className="session-row-kwh">{fmtKwh(kwh)}</p>
        <p className="session-row-dur">{duration}</p>
      </div>
    </div>
  );
}

/* ── Pagina principale ── */
const TABS = ['Profilo', 'Badge', 'Classifica', 'Storico'];

export default function ProfilePage() {
  const { user } = useAuth();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  // Banner "in attesa del cavo": arriva da /react/stazione/:id dopo verifica
  // codice (?attesa_stazione=XXX). Polliamo i secondi residui dal server
  // (Redis) ogni secondo finche' la chiave esiste.
  const attesaStazione = searchParams.get('attesa_stazione');
  const [attesaSecondi, setAttesaSecondi] = useState(null);
  const pollAttesaRef = useRef(null);

  useEffect(() => {
    if (!attesaStazione) {
      setAttesaSecondi(null);
      return;
    }
    let alive = true;

    async function tick() {
      try {
        const { data } = await apiClient.get('/me/attesa-cavo', {
          params: { id_stazione: attesaStazione },
        });
        if (!alive) return;
        if (data.attesa) {
          setAttesaSecondi(data.secondi_residui);
        } else {
          setAttesaSecondi(null);
          clearInterval(pollAttesaRef.current);
        }
      } catch {
        if (!alive) return;
        setAttesaSecondi(null);
        clearInterval(pollAttesaRef.current);
      }
    }

    tick();
    pollAttesaRef.current = setInterval(tick, 1000);
    return () => {
      alive = false;
      clearInterval(pollAttesaRef.current);
    };
  }, [attesaStazione]);

  const [tab, setTab]               = useState('Profilo');
  const [profile, setProfile]       = useState(null);
  const [badges, setBadges]         = useState([]);
  const [leaderboard, setLeaderboard] = useState([]);
  const [sessions, setSessions]     = useState([]);
  const [loading, setLoading]       = useState({});
  const [errors, setErrors]         = useState({});
  // Traccia quali tab hanno già fatto fetch (evita loop su array vuoto dopo errore)
  const [fetched, setFetched]       = useState({});

  function setLoad(key, val) { setLoading((p) => ({ ...p, [key]: val })); }
  function setErr(key, val)  { setErrors((p) => ({ ...p, [key]: val })); }

  /* Fetch profilo gamification */
  useEffect(() => {
    setLoad('profile', true);
    apiClient.get('/gamification/profile')
      .then(({ data }) => setProfile(data.data ?? data))
      .catch(() => setErr('profile', 'Impossibile caricare il profilo.'))
      .finally(() => setLoad('profile', false));
  }, []);

  /* Fetch badge (catalogo + guadagnati) */
  useEffect(() => {
    if (tab !== 'Badge' || fetched.badges) return;
    setFetched((p) => ({ ...p, badges: true }));
    setLoad('badges', true);
    apiClient.get('/gamification/badges')
      .then(({ data }) => setBadges(Array.isArray(data.data ?? data) ? (data.data ?? data) : []))
      .catch(() => setErr('badges', 'Impossibile caricare i badge. Controlla la connessione.'))
      .finally(() => setLoad('badges', false));
  }, [tab, fetched.badges]);

  /* Fetch classifica */
  useEffect(() => {
    if (tab !== 'Classifica' || fetched.lb) return;
    setFetched((p) => ({ ...p, lb: true }));
    setLoad('lb', true);
    apiClient.get('/gamification/leaderboard')
      .then(({ data }) => setLeaderboard(Array.isArray(data.data ?? data) ? (data.data ?? data) : []))
      .catch(() => setErr('lb', 'Impossibile caricare la classifica. Controlla la connessione.'))
      .finally(() => setLoad('lb', false));
  }, [tab, fetched.lb]);

  /* Fetch storico sessioni */
  useEffect(() => {
    if (tab !== 'Storico' || fetched.sessions) return;
    setFetched((p) => ({ ...p, sessions: true }));
    setLoad('sessions', true);
    apiClient.get('/gamification/sessioni')
      .then(({ data }) => setSessions(Array.isArray(data.data ?? data) ? (data.data ?? data) : []))
      .catch(() => setErr('sessions', 'Impossibile caricare lo storico. Controlla la connessione.'))
      .finally(() => setLoad('sessions', false));
  }, [tab, fetched.sessions]);

  /* Badge: catalogo completo con earned flag */
  const earnedIds = new Set(
    badges
      .filter((b) => b.data_ottenimento || b.earned)
      .map((b) => b.id_badge ?? b.id),
  );
  // Se l'API restituisce solo i badge guadagnati, li mostriamo tutti come earned
  const allBadges = badges;

  return (
    <div className="profile-page">
      <NavBar />

      <main className="pp-main">
        {attesaSecondi !== null && (
          <div className="attesa-banner" role="status">
            <span className="attesa-spinner" />
            <div className="attesa-text">
              <p className="attesa-title">In attesa del cavo…</p>
              <p className="attesa-sub">
                Collega il cavo a una presa libera della stazione entro <strong>{attesaSecondi}s</strong>.
              </p>
            </div>
          </div>
        )}

        {/* Hero profilo */}
        <div className="profile-hero">
          <div className="profile-avatar">
            {(user?.nome ?? user?.email ?? 'U')[0].toUpperCase()}
          </div>
          <div className="profile-hero-info">
            <h1 className="profile-name">{user?.nome ?? user?.email ?? 'Utente'}</h1>
            <p className="profile-email">{user?.email ?? ''}</p>
            {profile && (
              <XpBar xp={profile.xp_totali ?? profile.punti ?? 0} livello={profile.livello ?? 1} />
            )}
          </div>
          {profile && (
            <div className="profile-stats">
              <div className="pstat">
                <p className="pstat-value">{profile.xp_totali ?? profile.punti ?? 0}</p>
                <p className="pstat-label">XP totali</p>
              </div>
              <div className="pstat-sep" />
              <div className="pstat">
                <p className="pstat-value">{fmtKwh(profile.kwh_totali ?? 0)}</p>
                <p className="pstat-label">Energia</p>
              </div>
              <div className="pstat-sep" />
              <div className="pstat">
                <p className="pstat-value">{profile.sessioni_totali ?? 0}</p>
                <p className="pstat-label">Sessioni</p>
              </div>
            </div>
          )}
        </div>

        {/* Tabs */}
        <div className="tabs-bar">
          {TABS.map((t) => (
            <button
              key={t}
              className={`tab-btn${tab === t ? ' tab-btn--active' : ''}`}
              onClick={() => setTab(t)}
            >
              {t}
            </button>
          ))}
        </div>

        {/* ── Tab: Profilo ── */}
        {tab === 'Profilo' && (
          <div className="tab-content">
            {loading.profile && <Spinner />}
            {errors.profile && <ErrorMsg>{errors.profile}</ErrorMsg>}
            {profile && !loading.profile && (
              <div className="profile-detail-grid">
                <InfoCard icon="🏆" title="Livello" value={`Livello ${profile.livello ?? 1}`} />
                <InfoCard icon="⚡" title="XP questa settimana" value={`${profile.xp_settimana ?? 0} XP`} />
                <InfoCard icon="🔋" title="kWh totali" value={fmtKwh(profile.kwh_totali ?? 0)} />
                <InfoCard icon="📅" title="Sessioni totali" value={`${profile.sessioni_totali ?? 0}`} />
                <InfoCard icon="🌿" title="CO₂ risparmiata" value={`${((parseFloat(profile.kwh_totali) || 0) * 0.233).toFixed(1)} kg`} />
                <InfoCard icon="🏅" title="Badge guadagnati" value={`${profile.badge_count ?? '—'}`} />
              </div>
            )}
          </div>
        )}

        {/* ── Tab: Badge ── */}
        {tab === 'Badge' && (
          <div className="tab-content">
            {loading.badges && <Spinner />}
            {errors.badges && <ErrorMsg>{errors.badges}</ErrorMsg>}
            {!loading.badges && allBadges.length === 0 && !errors.badges && (
              <EmptyState icon="🏅" text="Nessun badge ancora. Completa sessioni per sbloccarli!" />
            )}
            <div className="badges-grid">
              {allBadges.map((b, i) => (
                <BadgeCard
                  key={b.id_badge ?? b.id ?? i}
                  badge={b}
                  earned={!!(b.data_ottenimento || b.earned || earnedIds.has(b.id_badge ?? b.id))}
                />
              ))}
            </div>
          </div>
        )}

        {/* ── Tab: Classifica ── */}
        {tab === 'Classifica' && (
          <div className="tab-content">
            {loading.lb && <Spinner />}
            {errors.lb && <ErrorMsg>{errors.lb}</ErrorMsg>}
            {!loading.lb && leaderboard.length === 0 && !errors.lb && (
              <EmptyState icon="🏆" text="Classifica non ancora disponibile." />
            )}
            <div className="leaderboard-list">
              {leaderboard.map((entry, i) => (
                <LeaderboardRow
                  key={entry.id_utente ?? entry.id ?? i}
                  entry={entry}
                  index={i}
                  isMe={entry.id_utente === user?.id_utente}
                />
              ))}
            </div>
          </div>
        )}

        {/* ── Tab: Storico ── */}
        {tab === 'Storico' && (
          <div className="tab-content">
            {loading.sessions && <Spinner />}
            {errors.sessions && <ErrorMsg>{errors.sessions}</ErrorMsg>}
            {!loading.sessions && sessions.length === 0 && !errors.sessions && (
              <EmptyState icon="📋" text="Nessuna sessione completata finora." />
            )}
            <div className="sessions-list">
              {sessions.map((s, i) => (
                <SessionRow key={s.id_sessione ?? i} session={s} />
              ))}
            </div>
          </div>
        )}
      </main>
    </div>
  );
}

/* ── Micro-componenti ── */
function Spinner() {
  return (
    <div className="pp-state">
      <span className="pp-spinner" />
    </div>
  );
}

function ErrorMsg({ children }) {
  return <p className="pp-error">{children}</p>;
}

function EmptyState({ icon, text }) {
  return (
    <div className="pp-empty">
      <span className="pp-empty-icon">{icon}</span>
      <p>{text}</p>
    </div>
  );
}

function InfoCard({ icon, title, value }) {
  return (
    <div className="info-card">
      <span className="info-card-icon">{icon}</span>
      <div>
        <p className="info-card-title">{title}</p>
        <p className="info-card-value">{value}</p>
      </div>
    </div>
  );
}