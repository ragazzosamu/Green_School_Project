// Equivalente React di backend/src/resources/views/classifica.blade.php
import { useEffect, useState, useCallback } from 'react';
import { useAuth } from '../context/AuthContext';
import NavBar from '../components/NavBar';
import apiClient from '../api/client';
import './ClassificaPage.css';

function initials(nome, cognome) {
  return ((nome?.[0] ?? '') + (cognome?.[0] ?? '')).toUpperCase();
}

export default function ClassificaPage() {
  const { user } = useAuth();

  const [users,   setUsers]   = useState([]);
  const [state,   setState]   = useState('loading'); // loading | error | empty | content
  const [spinning, setSpinning] = useState(false);

  const myId = user?.id_utente;

  const load = useCallback(async () => {
    setState('loading');
    setSpinning(true);
    try {
      const { data } = await apiClient.get('/gamification/leaderboard');
      const arr = Array.isArray(data) ? data : (data.data ?? []);
      if (arr.length === 0) {
        setState('empty');
      } else {
        setUsers(arr);
        setState('content');
      }
    } catch {
      setState('error');
    } finally {
      setSpinning(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  // Podio: ordine visivo 2° - 1° - 3°
  const top3 = users.slice(0, 3);
  const podioSlots = [top3[1], top3[0], top3[2]];
  const podioPos   = ['pos-2', 'pos-1', 'pos-3'];
  const podioRanks = ['2°', '1°', '3°'];

  const rest = users.slice(3);
  const myEntry = users.find((u) => u.id_utente === myId);
  const myPos   = myEntry ? users.indexOf(myEntry) + 1 : null;

  return (
    <div className="cl-page">
      <NavBar />

      <main className="cl-main">
        <div className="page-header">
          <div>
            <p className="page-eyebrow">Gamification</p>
            <h1 className="page-title">Classifica</h1>
          </div>
          <button className={`refresh-btn ${spinning ? 'spinning' : ''}`} onClick={load} disabled={spinning}>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
              <polyline points="23 4 23 10 17 10" />
              <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
            </svg>
            Aggiorna
          </button>
        </div>

        <div className="leaderboard-layout">
          {/* Colonna principale */}
          <div>
            <div className="gs-card">
              {state === 'loading' && (
                <div className="state-box">
                  <p className="state-sub">Caricamento classifica…</p>
                </div>
              )}

              {state === 'error' && (
                <div className="state-box">
                  <div className="state-icon">⚠️</div>
                  <p className="state-title">Classifica non disponibile</p>
                  <p className="state-sub">L'endpoint <code>/api/gamification/leaderboard</code> non risponde.</p>
                </div>
              )}

              {state === 'empty' && (
                <div className="state-box">
                  <div className="state-icon">🏆</div>
                  <p className="state-title">Nessun dato ancora</p>
                  <p className="state-sub">Completa la tua prima sessione di ricarica per entrare in classifica!</p>
                </div>
              )}

              {state === 'content' && (
                <>
                  {/* Podio */}
                  <div className="podio-section">
                    <p className="podio-label">Top 3 questa settimana</p>
                    <div className="podio-row">
                      {podioSlots.map((u, vi) => {
                        const cls   = podioPos[vi];
                        const rank  = podioRanks[vi];
                        const real  = vi === 1 ? 1 : (vi === 0 ? 2 : 3);
                        if (!u) {
                          return (
                            <div key={vi} className="podio-slot empty" style={{ animationDelay: `${vi * 0.08}s` }}>
                              <div className={`podio-avatar ${cls}`} style={{ fontSize: '1.2rem', color: 'rgba(255,255,255,0.2)' }}>—</div>
                              <span className="podio-name">—</span>
                              <span className={`podio-xp ${cls}`} style={{ opacity: 0.2 }}>{rank}</span>
                            </div>
                          );
                        }
                        const isMe = u.id_utente === myId;
                        return (
                          <div key={u.id_utente} className="podio-slot" style={{ animationDelay: `${vi * 0.08}s` }}>
                            <div className={`podio-avatar ${cls}`}>
                              {real === 1 && <span className="podio-crown">👑</span>}
                              {initials(u.nome, u.cognome)}
                            </div>
                            <span className="podio-name">{u.nome} {u.cognome}{isMe ? ' (Tu)' : ''}</span>
                            <span className={`podio-xp ${cls}`}>{u.xp_totali} <small>XP</small></span>
                            <span className={`podio-rank ${cls}`}>{rank}</span>
                          </div>
                        );
                      })}
                    </div>
                  </div>

                  {/* Lista dal 4° in poi */}
                  <div className="rank-list">
                    {rest.length === 0 ? (
                      <div className="rank-empty">Solo i primi 3 — aggiungi altri utenti!</div>
                    ) : rest.map((u, i) => {
                      const pos  = i + 4;
                      const isMe = u.id_utente === myId;
                      return (
                        <div key={u.id_utente}
                             className={`rank-item ${isMe ? 'is-me' : ''}`}
                             style={{ animationDelay: `${i * 0.04 + 0.1}s` }}>
                          <span className="rank-pos">{pos}</span>
                          <div className="rank-avatar">{initials(u.nome, u.cognome)}</div>
                          <div className="rank-info">
                            <span className="rank-name">
                              {u.nome} {u.cognome}
                              {isMe && <span className="rank-you-badge">Tu</span>}
                            </span>
                            <span className="rank-meta">
                              {u.sessioni_totali ?? '—'} sessioni · {u.co2_risparmiata_kg ?? '—'} kg CO₂
                            </span>
                          </div>
                          <span className="rank-xp">
                            {u.xp_totali} <span className="rank-xp-unit">XP</span>
                          </span>
                        </div>
                      );
                    })}
                  </div>
                </>
              )}
            </div>
          </div>

          {/* Sidebar */}
          <aside className="sidebar-col">
            <div className="my-rank-card">
              <div className="my-rank-top">
                <div className="my-rank-circle">
                  <span className="my-rank-num">{myPos ?? '—'}{myPos ? '°' : ''}</span>
                  <span className="my-rank-of">posto</span>
                </div>
                <div>
                  <p className="my-rank-label">La tua posizione</p>
                  <p className="my-rank-name">{user?.nome} {user?.cognome}</p>
                  <p className="my-rank-xp">{myEntry ? `${myEntry.xp_totali} XP totali` : '— XP totali'}</p>
                </div>
              </div>
              <div className="my-rank-bottom">
                <div className="my-rank-stat">
                  <span className="my-rank-stat-label">Sessioni totali</span>
                  <span className="my-rank-stat-val">{myEntry?.sessioni_totali ?? '—'}</span>
                </div>
                <div className="my-rank-stat">
                  <span className="my-rank-stat-label">CO₂ risparmiata</span>
                  <span className="my-rank-stat-val green">{myEntry?.co2_risparmiata_kg ?? '—'} kg</span>
                </div>
                <div className="my-rank-stat">
                  <span className="my-rank-stat-label">Livello</span>
                  <span className="my-rank-stat-val">{myEntry?.livello ?? '—'}</span>
                </div>
              </div>
            </div>

            <div className="rules-card">
              <p className="rules-title">Come si guadagnano XP</p>
              <div className="rule-row"><span className="rule-label">Sessione completata</span><span className="rule-xp">+50 XP</span></div>
              <div className="rule-row"><span className="rule-label">Prima ricarica</span><span className="rule-xp">+100 XP</span></div>
              <div className="rule-row"><span className="rule-label">Streak 7 giorni</span><span className="rule-xp">+200 XP</span></div>
              <div className="rule-row"><span className="rule-label">Badge sbloccato</span><span className="rule-xp">+50 XP</span></div>
              <div className="rule-row"><span className="rule-label">kWh erogati (×1)</span><span className="rule-xp">+5 XP/kWh</span></div>
            </div>
          </aside>
        </div>
      </main>
    </div>
  );
}
