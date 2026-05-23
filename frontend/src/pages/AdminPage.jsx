import { useEffect, useState, useCallback } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import apiClient from '../api/client';
import './AdminPage.css';

// ── Helpers ───────────────────────────────────────────────────────────────────

function fmt(n, dec = 1) {
  return (parseFloat(n) || 0).toFixed(dec);
}

function fmtDate(iso) {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString('it-IT', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  });
}

function fmtDuration(start, end) {
  if (!start || !end) return '—';
  const sec = Math.floor((new Date(end) - new Date(start)) / 1000);
  const h = Math.floor(sec / 3600);
  const m = Math.floor((sec % 3600) / 60);
  return `${h}h ${m}m`;
}

// ── Componenti UI condivisi ───────────────────────────────────────────────────

function Alert({ type, children, onClose }) {
  return (
    <div className={`a-alert a-alert--${type}`}>
      <span>{type === 'success' ? '✓' : '✗'} {children}</span>
      {onClose && <button className="a-alert-close" onClick={onClose}>×</button>}
    </div>
  );
}

function Spinner() {
  return <div className="a-spinner" />;
}

function Pagination({ meta, onPage }) {
  if (!meta || meta.last_page <= 1) return null;
  return (
    <div className="a-pagination">
      <button
        className="a-btn a-btn--outline a-btn--sm"
        disabled={meta.current_page === 1}
        onClick={() => onPage(meta.current_page - 1)}
      >← Prec</button>
      <span className="a-pagination-info">
        Pagina {meta.current_page} di {meta.last_page}
      </span>
      <button
        className="a-btn a-btn--outline a-btn--sm"
        disabled={meta.current_page === meta.last_page}
        onClick={() => onPage(meta.current_page + 1)}
      >Succ →</button>
    </div>
  );
}

function StatCard({ label, value, sub, color }) {
  return (
    <div className="a-stat-card">
      <p className="a-stat-label">{label}</p>
      <p className={`a-stat-value${color ? ` a-stat-value--${color}` : ''}`}>{value}</p>
      {sub && <p className="a-stat-sub">{sub}</p>}
    </div>
  );
}

// ── SEZIONE: Dashboard ────────────────────────────────────────────────────────

function Dashboard() {
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError]     = useState('');

  useEffect(() => {
    apiClient.get('/admin/dashboard')
      .then(r => setData(r.data))
      .catch((err) => {
        const msg = err.response?.status === 403
          ? 'Accesso negato: ruolo admin non riconosciuto dal server.'
          : 'Impossibile caricare la dashboard. Verifica che il backend sia raggiungibile.';
        setError(msg);
      })
      .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="a-loading"><Spinner /><p>Caricamento dashboard…</p></div>;
  if (error)   return <div className="a-loading"><Alert type="error">{error}</Alert></div>;
  if (!data)   return <div className="a-loading"><Alert type="error">Impossibile caricare la dashboard.</Alert></div>;

  const { stats, sessioni_settimana, top_utenti, ultime_sessioni } = data;

  return (
    <div>
      {/* Stat cards */}
      <div className="a-stats-grid">
        <StatCard label="Utenti registrati" value={stats.utenti_totali} sub="account attivi" color="purple" />
        <StatCard label="Sessioni oggi" value={stats.sessioni_oggi} sub={`${stats.sessioni_attive} in corso ora`} color="green" />
        <StatCard label="kWh totali erogati" value={fmt(stats.kwh_totali)} sub={`da ${stats.sessioni_totali} sessioni`} />
        <StatCard label="Revenue totale" value={`€ ${fmt(stats.revenue_totale, 2)}`} sub="sessioni fatturate" color="green" />
      </div>

      {/* Stato stazioni */}
      <div className="a-station-status">
        <div className="a-stat-card a-stat-card--inline">
          <span className="a-status-dot a-status-dot--green" />
          <div>
            <p className="a-stat-label">Stazioni online</p>
            <p className="a-stat-value a-stat-value--green">{stats.stazioni_online}</p>
          </div>
        </div>
        <div className="a-stat-card a-stat-card--inline">
          <span className="a-status-dot a-status-dot--red" />
          <div>
            <p className="a-stat-label">Offline / guasto</p>
            <p className="a-stat-value a-stat-value--red">{stats.stazioni_offline}</p>
          </div>
        </div>
      </div>

      <div className="a-dash-grid">
        {/* Sessioni ultimi 7 giorni */}
        <div className="a-card">
          <div className="a-card-header">
            <span className="a-card-title">Sessioni ultimi 7 giorni</span>
          </div>
          <div className="a-card-body">
            {sessioni_settimana.length === 0 ? (
              <p className="a-empty">Nessuna sessione questa settimana.</p>
            ) : (
              <div className="a-bar-chart">
                {sessioni_settimana.map(g => {
                  const maxTot = Math.max(...sessioni_settimana.map(x => x.totale), 1);
                  return (
                    <div key={g.giorno} className="a-bar-col">
                      <span className="a-bar-val">{g.totale}</span>
                      <div
                        className="a-bar"
                        style={{ height: `${(g.totale / maxTot) * 80}px` }}
                        title={`${g.giorno}: ${g.totale} sessioni, ${fmt(g.kwh)} kWh`}
                      />
                      <span className="a-bar-label">
                        {new Date(g.giorno).toLocaleDateString('it-IT', { day: '2-digit', month: '2-digit' })}
                      </span>
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Top utenti */}
        <div className="a-card">
          <div className="a-card-header">
            <span className="a-card-title">Utenti più attivi</span>
          </div>
          <table className="a-table">
            <thead>
              <tr>
                <th>Utente</th>
                <th>Sessioni</th>
                <th>kWh</th>
              </tr>
            </thead>
            <tbody>
              {top_utenti.length === 0 ? (
                <tr><td colSpan={3} className="a-table-empty">Nessun dato.</td></tr>
              ) : top_utenti.map((u, i) => (
                <tr key={i}>
                  <td>
                    <p className="a-fw600">{u.nome} {u.cognome}</p>
                    <p className="a-sub-text">{u.email}</p>
                  </td>
                  <td><span className="a-pill a-pill--purple">{u.sessioni}</span></td>
                  <td className="a-green a-fw600">{fmt(u.kwh)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {/* Ultime sessioni */}
      <div className="a-card">
        <div className="a-card-header">
          <span className="a-card-title">Ultime 10 sessioni</span>
        </div>
        <table className="a-table">
          <thead>
            <tr>
              <th>Utente</th>
              <th>Punto</th>
              <th>Inizio</th>
              <th>kWh</th>
              <th>Stato</th>
            </tr>
          </thead>
          <tbody>
            {ultime_sessioni.map((s, i) => (
              <tr key={i}>
                <td>
                  <p className="a-fw600">{s.nome} {s.cognome}</p>
                  <p className="a-sub-text">{s.email}</p>
                </td>
                <td className="a-mono">{s.punto_id ?? '—'}</td>
                <td className="a-sub-text">{fmtDate(s.data_inizio)}</td>
                <td className="a-green a-fw600">{s.quantita_kwh ? fmt(s.quantita_kwh, 2) : '—'}</td>
                <td>
                  {s.data_fine
                    ? <span className="a-pill a-pill--green">Conclusa</span>
                    : <span className="a-pill a-pill--yellow">In corso</span>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// ── SEZIONE: Utenti ───────────────────────────────────────────────────────────

function Utenti({ onSelect }) {
  const [data, setData]     = useState(null);
  const [loading, setLoading] = useState(true);
  const [cerca, setCerca]   = useState('');
  const [page, setPage]     = useState(1);

  const load = useCallback(() => {
    setLoading(true);
    apiClient.get('/admin/utenti', { params: { cerca, page } })
      .then(r => setData(r.data))
      .finally(() => setLoading(false));
  }, [cerca, page]);

  useEffect(() => { load(); }, [load]);

  function handleSearch(e) {
    e.preventDefault();
    setPage(1);
    load();
  }

  return (
    <div>
      <div className="a-page-header">
        <div>
          <h2 className="a-page-title">Utenti</h2>
          {data && <p className="a-page-sub">{data.total} utenti registrati</p>}
        </div>
      </div>

      {/* Ricerca */}
      <form className="a-search-bar" onSubmit={handleSearch}>
        <input
          className="a-input"
          type="text"
          placeholder="Cerca per nome, cognome o email…"
          value={cerca}
          onChange={e => setCerca(e.target.value)}
          style={{ maxWidth: 340 }}
        />
        <button type="submit" className="a-btn a-btn--primary">Cerca</button>
        {cerca && (
          <button type="button" className="a-btn a-btn--outline" onClick={() => { setCerca(''); setPage(1); }}>
            Cancella
          </button>
        )}
      </form>

      <div className="a-card">
        {loading ? (
          <div className="a-loading"><Spinner /></div>
        ) : (
          <>
            <table className="a-table">
              <thead>
                <tr>
                  <th>Utente</th>
                  <th>Email</th>
                  <th>Cellulare</th>
                  <th>Sessioni</th>
                  <th>kWh</th>
                  <th>Stato</th>
                  <th>Azioni</th>
                </tr>
              </thead>
              <tbody>
                {data?.data?.length === 0 ? (
                  <tr><td colSpan={7} className="a-table-empty">Nessun utente trovato.</td></tr>
                ) : data?.data?.map(u => (
                  <tr key={u.id_utente}>
                    <td>
                      <p className="a-fw600">{u.nome} {u.cognome}</p>
                      <p className="a-sub-text">{u.tipo_account}</p>
                    </td>
                    <td className="a-sub-text">{u.email}</td>
                    <td className="a-sub-text">{u.cellulare}</td>
                    <td><span className="a-pill a-pill--purple">{u.sessioni_tot}</span></td>
                    <td className="a-green a-fw600">{fmt(u.kwh_tot)}</td>
                    <td>
                      {u.attivo
                        ? <span className="a-pill a-pill--green">Attivo</span>
                        : <span className="a-pill a-pill--red">Disattivato</span>}
                    </td>
                    <td>
                      <button className="a-btn a-btn--outline a-btn--sm" onClick={() => onSelect(u.id_utente)}>
                        Dettaglio
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <Pagination meta={data} onPage={p => setPage(p)} />
          </>
        )}
      </div>
    </div>
  );
}

// ── SEZIONE: Dettaglio Utente ─────────────────────────────────────────────────

function DettaglioUtente({ userId, onBack }) {
  const [data, setData]         = useState(null);
  const [loading, setLoading]   = useState(true);
  const [msg, setMsg]           = useState(null);
  const [err, setErr]           = useState(null);
  const [form, setForm]         = useState(null);
  const [pwForm, setPwForm]     = useState({ nuova_password: '' });
  const [savingForm, setSavingForm]   = useState(false);
  const [savingPw, setSavingPw]       = useState(false);
  const [savingToggle, setSavingToggle] = useState(false);
  const [page, setPage]         = useState(1);

  const load = useCallback(() => {
    setLoading(true);
    apiClient.get(`/admin/utenti/${userId}`, { params: { page } })
      .then(r => {
        setData(r.data);
        if (!form) setForm({
          nome:         r.data.utente.nome,
          cognome:      r.data.utente.cognome,
          email:        r.data.utente.email,
          cellulare:    r.data.utente.cellulare,
          tipo_account: r.data.utente.tipo_account,
          attivo:       Boolean(r.data.utente.attivo),
        });
      })
      .finally(() => setLoading(false));
  }, [userId, page]);

  useEffect(() => { load(); }, [load]);

  function flash(m, isErr = false) {
    if (isErr) setErr(m); else setMsg(m);
    setTimeout(() => { setMsg(null); setErr(null); }, 4000);
  }

  async function handleSaveForm(e) {
    e.preventDefault();
    setSavingForm(true);
    try {
      const { data: r } = await apiClient.put(`/admin/utenti/${userId}`, form);
      flash(r.message);
      load();
    } catch (e) {
      flash(e.response?.data?.message ?? 'Errore durante il salvataggio.', true);
    } finally { setSavingForm(false); }
  }

  async function handleToggle() {
    setSavingToggle(true);
    try {
      const { data: r } = await apiClient.post(`/admin/utenti/${userId}/toggle`);
      flash(r.message);
      load();
    } catch (e) {
      flash(e.response?.data?.message ?? 'Errore.', true);
    } finally { setSavingToggle(false); }
  }

  async function handleResetPw(e) {
    e.preventDefault();
    setSavingPw(true);
    try {
      const { data: r } = await apiClient.post(`/admin/utenti/${userId}/reset`, pwForm);
      flash(r.message);
      setPwForm({ nuova_password: '' });
    } catch (e) {
      flash(e.response?.data?.message ?? 'Errore durante il reset.', true);
    } finally { setSavingPw(false); }
  }

  if (loading && !data) return <div className="a-loading"><Spinner /></div>;
  if (!data) return <Alert type="error">Utente non trovato.</Alert>;

  const { utente, sessioni, profilo, badge, stats } = data;

  return (
    <div>
      <button className="a-back-link" onClick={onBack}>← Torna agli utenti</button>

      {msg && <Alert type="success" onClose={() => setMsg(null)}>{msg}</Alert>}
      {err && <Alert type="error" onClose={() => setErr(null)}>{err}</Alert>}

      <div className="a-detail-grid">
        {/* Colonna sinistra */}
        <div className="a-detail-left">

          {/* Info */}
          <div className="a-card">
            <div className="a-card-header">
              <span className="a-card-title">Informazioni</span>
              {utente.attivo
                ? <span className="a-pill a-pill--green">Attivo</span>
                : <span className="a-pill a-pill--red">Disattivato</span>}
            </div>
            <div className="a-card-body">
              <div className="a-user-avatar">👤</div>
              <p className="a-user-name">{utente.nome} {utente.cognome}</p>
              <p className="a-sub-text">{utente.email}</p>
              <p className="a-sub-text">{utente.cellulare}</p>

              <div className="a-mini-grid">
                <div className="a-mini-card">
                  <p className="a-mini-label">Sessioni</p>
                  <p className="a-mini-value a-mini-value--purple">{stats.sessioni_totali}</p>
                </div>
                <div className="a-mini-card">
                  <p className="a-mini-label">kWh</p>
                  <p className="a-mini-value a-mini-value--green">{fmt(stats.kwh_totali)}</p>
                </div>
                <div className="a-mini-card a-mini-card--full">
                  <p className="a-mini-label">Spesa totale</p>
                  <p className="a-mini-value">€ {fmt(stats.spesa_totale, 2)}</p>
                </div>
              </div>

              {profilo && (
                <div className="a-gamification-box">
                  <p className="a-gamification-label">Gamification</p>
                  <p className="a-sub-text">XP: <strong>{profilo.xp_totali}</strong> · Livello {profilo.livello ?? Math.floor(Math.sqrt(profilo.xp_totali / 100))}</p>
                  <p className="a-sub-text">CO₂ risparmiata: <strong>{fmt(profilo.co2_risparmiata_kg, 1)} kg</strong></p>
                  <p className="a-sub-text">Streak: <strong>{profilo.streak_giorni} gg</strong></p>
                </div>
              )}
            </div>
          </div>

          {/* Badge */}
          {badge?.length > 0 && (
            <div className="a-card">
              <div className="a-card-header"><span className="a-card-title">Badge sbloccati</span></div>
              <div className="a-card-body">
                {badge.map((b, i) => (
                  <div key={i} className="a-badge-row">
                    <span>{b.icona_emoji}</span>
                    <span className="a-fw600">{b.nome}</span>
                    <span className="a-sub-text" style={{ marginLeft: 'auto' }}>
                      {new Date(b.data_sblocco).toLocaleDateString('it-IT')}
                    </span>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Azioni rapide */}
          <div className="a-card">
            <div className="a-card-header"><span className="a-card-title">Azioni rapide</span></div>
            <div className="a-card-body">
              <button
                className={`a-btn a-btn--full ${utente.attivo ? 'a-btn--danger' : 'a-btn--outline'}`}
                onClick={handleToggle}
                disabled={savingToggle}
              >
                {savingToggle ? 'Aggiornamento…' : utente.attivo ? '🔒 Disattiva account' : '✅ Riattiva account'}
              </button>
            </div>
          </div>

          {/* Reset password */}
          <div className="a-card">
            <div className="a-card-header"><span className="a-card-title">Reset password</span></div>
            <form className="a-card-body" onSubmit={handleResetPw}>
              <div className="a-form-group">
                <label className="a-label">Nuova password</label>
                <input
                  type="password"
                  className="a-input"
                  placeholder="Min. 8 caratteri"
                  minLength={8}
                  required
                  value={pwForm.nuova_password}
                  onChange={e => setPwForm({ nuova_password: e.target.value })}
                />
              </div>
              <button type="submit" className="a-btn a-btn--primary a-btn--full" disabled={savingPw}>
                {savingPw ? 'Aggiornamento…' : 'Aggiorna password'}
              </button>
            </form>
          </div>
        </div>

        {/* Colonna destra */}
        <div className="a-detail-right">

          {/* Modifica dati */}
          <div className="a-card">
            <div className="a-card-header"><span className="a-card-title">Modifica dati</span></div>
            {form && (
              <form className="a-card-body" onSubmit={handleSaveForm}>
                <div className="a-form-grid">
                  <div className="a-form-group">
                    <label className="a-label">Nome</label>
                    <input className="a-input" value={form.nome} onChange={e => setForm(f => ({ ...f, nome: e.target.value }))} required />
                  </div>
                  <div className="a-form-group">
                    <label className="a-label">Cognome</label>
                    <input className="a-input" value={form.cognome} onChange={e => setForm(f => ({ ...f, cognome: e.target.value }))} required />
                  </div>
                  <div className="a-form-group">
                    <label className="a-label">Email</label>
                    <input className="a-input" type="email" value={form.email} onChange={e => setForm(f => ({ ...f, email: e.target.value }))} required />
                  </div>
                  <div className="a-form-group">
                    <label className="a-label">Cellulare</label>
                    <input className="a-input" value={form.cellulare} onChange={e => setForm(f => ({ ...f, cellulare: e.target.value }))} required />
                  </div>
                  <div className="a-form-group">
                    <label className="a-label">Tipo account</label>
                    <select className="a-input" value={form.tipo_account} onChange={e => setForm(f => ({ ...f, tipo_account: e.target.value }))}>
                      {['completo', 'badge_anonimo', 'ospite'].map(t => (
                        <option key={t} value={t}>{t.charAt(0).toUpperCase() + t.slice(1)}</option>
                      ))}
                    </select>
                  </div>
                  <div className="a-form-group a-form-group--check">
                    <label className="a-check-label">
                      <input
                        type="checkbox"
                        checked={form.attivo}
                        onChange={e => setForm(f => ({ ...f, attivo: e.target.checked }))}
                      />
                      Account attivo
                    </label>
                  </div>
                </div>
                <button type="submit" className="a-btn a-btn--primary" disabled={savingForm}>
                  {savingForm ? 'Salvataggio…' : 'Salva modifiche'}
                </button>
              </form>
            )}
          </div>

          {/* Storico sessioni */}
          <div className="a-card">
            <div className="a-card-header">
              <span className="a-card-title">Storico sessioni</span>
              <span className="a-sub-text">ultime {sessioni?.data?.length ?? 0}</span>
            </div>
            <table className="a-table">
              <thead>
                <tr><th>Data inizio</th><th>Durata</th><th>kWh</th><th>Costo</th><th>Stato</th></tr>
              </thead>
              <tbody>
                {sessioni?.data?.length === 0 ? (
                  <tr><td colSpan={5} className="a-table-empty">Nessuna sessione.</td></tr>
                ) : sessioni?.data?.map((s, i) => (
                  <tr key={i}>
                    <td className="a-sub-text">{fmtDate(s.data_inizio)}</td>
                    <td className="a-sub-text">{fmtDuration(s.data_inizio, s.data_fine)}</td>
                    <td>{s.quantita_kwh ? fmt(s.quantita_kwh, 2) : '—'}</td>
                    <td>{s.costo_totale ? `€ ${fmt(s.costo_totale, 2)}` : '—'}</td>
                    <td>
                      {s.data_fine
                        ? <span className="a-pill a-pill--green">Conclusa</span>
                        : <span className="a-pill a-pill--yellow">In corso</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <Pagination meta={sessioni} onPage={p => setPage(p)} />
          </div>
        </div>
      </div>
    </div>
  );
}

// ── SEZIONE: Sessioni ─────────────────────────────────────────────────────────

function Sessioni() {
  const [data, setData]     = useState(null);
  const [loading, setLoading] = useState(true);
  const [cerca, setCerca]   = useState('');
  const [stato, setStato]   = useState('');
  const [page, setPage]     = useState(1);

  const load = useCallback(() => {
    setLoading(true);
    apiClient.get('/admin/sessioni', { params: { cerca, stato, page } })
      .then(r => setData(r.data))
      .finally(() => setLoading(false));
  }, [cerca, stato, page]);

  useEffect(() => { load(); }, [load]);

  function handleSearch(e) {
    e.preventDefault();
    setPage(1);
    load();
  }

  // Download CSV — fa una GET con window.open per far scaricare il file
  function downloadCsv(azione, extra = {}) {
    const token  = localStorage.getItem('sanctum_token');
    const params = new URLSearchParams({ azione, ...extra }).toString();
    // Fetch blob manuale perché axios non gestisce bene i file download
    fetch(`/api/admin/report/csv?${params}`, {
      headers: { Authorization: `Bearer ${token}` },
    })
      .then(r => r.blob())
      .then(blob => {
        const url = URL.createObjectURL(blob);
        const a   = document.createElement('a');
        a.href = url;
        a.download = azione === 'utente' ? `report_utente.csv` : `report_giornaliero.csv`;
        a.click();
        URL.revokeObjectURL(url);
      });
  }

  return (
    <div>
      <div className="a-page-header">
        <div>
          <h2 className="a-page-title">Sessioni di ricarica</h2>
          {data && <p className="a-page-sub">{data.sessioni?.total} sessioni totali</p>}
        </div>
      </div>

      {/* Export CSV */}
      <div className="a-export-box">
        <p className="a-export-title">📊 Area Esportazione Report CSV</p>
        <div className="a-export-row">
          <div className="a-form-group">
            <label className="a-label">Filtra per Utente</label>
            <select className="a-input" style={{ minWidth: 200 }} id="csv-utente-select">
              <option value="">-- Seleziona Utente --</option>
              {data?.lista_utenti?.map(u => (
                <option key={u.id_utente} value={u.id_utente}>{u.cognome} {u.nome}</option>
              ))}
            </select>
          </div>
          <button
            className="a-btn a-btn--outline a-btn--purple"
            onClick={() => {
              const sel = document.getElementById('csv-utente-select');
              if (sel?.value) downloadCsv('utente', { utente_id: sel.value });
            }}
          >📥 Scarica Report Utente</button>

          <div className="a-export-divider" />

          <div className="a-form-group">
            <label className="a-label">Scegli il Giorno</label>
            <input type="date" className="a-input" id="csv-date-input" defaultValue={new Date().toISOString().slice(0, 10)} style={{ maxWidth: 160 }} />
          </div>
          <button
            className="a-btn a-btn--primary"
            style={{ background: 'var(--accent)', borderColor: 'var(--accent)' }}
            onClick={() => {
              const inp = document.getElementById('csv-date-input');
              downloadCsv('data', { data_report: inp?.value ?? '' });
            }}
          >📥 Scarica Report Giornaliero</button>
        </div>
      </div>

      {/* Filtri */}
      <form className="a-search-bar" onSubmit={handleSearch}>
        <input
          className="a-input"
          type="text"
          placeholder="Cerca per email, nome…"
          value={cerca}
          onChange={e => setCerca(e.target.value)}
          style={{ maxWidth: 300 }}
        />
        <select className="a-input" style={{ maxWidth: 160 }} value={stato} onChange={e => setStato(e.target.value)}>
          <option value="">Tutti gli stati</option>
          <option value="attiva">In corso</option>
          <option value="conclusa">Concluse</option>
        </select>
        <button type="submit" className="a-btn a-btn--primary">Filtra</button>
        {(cerca || stato) && (
          <button type="button" className="a-btn a-btn--outline" onClick={() => { setCerca(''); setStato(''); setPage(1); }}>
            Reset
          </button>
        )}
      </form>

      <div className="a-card">
        {loading ? (
          <div className="a-loading"><Spinner /></div>
        ) : (
          <>
            <table className="a-table">
              <thead>
                <tr>
                  <th>Utente</th><th>Punto</th><th>Inizio</th><th>Fine</th>
                  <th>Durata</th><th>kWh</th><th>Costo</th><th>Stato</th>
                </tr>
              </thead>
              <tbody>
                {data?.sessioni?.data?.length === 0 ? (
                  <tr><td colSpan={8} className="a-table-empty">Nessuna sessione trovata.</td></tr>
                ) : data?.sessioni?.data?.map((s, i) => (
                  <tr key={i}>
                    <td>
                      <p className="a-fw600">{s.nome} {s.cognome}</p>
                      <p className="a-sub-text">{s.email}</p>
                    </td>
                    <td className="a-mono">{s.id_punto ?? '—'}</td>
                    <td className="a-sub-text">{fmtDate(s.data_inizio)}</td>
                    <td className="a-sub-text">{s.data_fine ? fmtDate(s.data_fine) : '—'}</td>
                    <td className="a-sub-text">{fmtDuration(s.data_inizio, s.data_fine)}</td>
                    <td className="a-green a-fw600">{s.quantita_kwh ? fmt(s.quantita_kwh, 2) : '—'}</td>
                    <td>{s.costo_totale ? `€ ${fmt(s.costo_totale, 2)}` : '—'}</td>
                    <td>
                      {s.data_fine
                        ? <span className="a-pill a-pill--green">Conclusa</span>
                        : <span className="a-pill a-pill--yellow">In corso</span>}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <Pagination meta={data?.sessioni} onPage={p => setPage(p)} />
          </>
        )}
      </div>
    </div>
  );
}

// ── SEZIONE: Stazioni ─────────────────────────────────────────────────────────

function Stazioni({ onSetup }) {
  const [stazioni, setStazioni] = useState(null);
  const [loading, setLoading]   = useState(true);
  const [msg, setMsg]           = useState(null);
  const [err, setErr]           = useState(null);
  const [toggling, setToggling] = useState(null);

  const load = () => {
    setLoading(true);
    apiClient.get('/admin/stazioni')
      .then(r => setStazioni(r.data))
      .finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []);

  function flash(m, isErr = false) {
    if (isErr) setErr(m); else setMsg(m);
    setTimeout(() => { setMsg(null); setErr(null); }, 4000);
  }

  async function handleToggle(id) {
    setToggling(id);
    try {
      const { data: r } = await apiClient.post(`/admin/stazioni/${encodeURIComponent(id)}/toggle`);
      flash(r.message);
      load();
    } catch (e) {
      flash(e.response?.data?.message ?? 'Errore durante il toggle.', true);
    } finally { setToggling(null); }
  }

  return (
    <div>
      <div className="a-page-header">
        <div>
          <h2 className="a-page-title">Stazioni di ricarica</h2>
          {stazioni && <p className="a-page-sub">{stazioni.length} stazioni nel sistema</p>}
        </div>
      </div>

      {msg && <Alert type="success" onClose={() => setMsg(null)}>{msg}</Alert>}
      {err && <Alert type="error" onClose={() => setErr(null)}>{err}</Alert>}

      <div className="a-card">
        {loading ? (
          <div className="a-loading"><Spinner /></div>
        ) : (
          <table className="a-table">
            <thead>
              <tr>
                <th>Stazione</th><th>Indirizzo</th><th style={{ textAlign: 'center' }}>Totali</th>
                <th style={{ textAlign: 'center' }}>Liberi</th><th style={{ textAlign: 'center' }}>Online</th>
                <th>Stato</th><th>Azione</th>
              </tr>
            </thead>
            <tbody>
              {stazioni?.length === 0 ? (
                <tr><td colSpan={7} className="a-table-empty">Nessuna stazione trovata.</td></tr>
              ) : stazioni?.map(s => (
                <tr key={s.id_stazione}>
                  <td>
                    <p className="a-fw600">{s.nome ?? '— da configurare —'}</p>
                    <p className="a-mono a-sub-text">MAC {s.id_stazione}</p>
                    {s.stato_setup === 'in_setup' && (
                      <span className="a-pill a-pill--yellow" style={{ marginTop: 4 }}>In setup</span>
                    )}
                  </td>
                  <td className="a-sub-text">{s.indirizzo ?? '—'}</td>
                  <td style={{ textAlign: 'center' }}>{s.punti_totali}</td>
                  <td style={{ textAlign: 'center' }}>
                    <span className={`a-pill ${s.punti_liberi > 0 ? 'a-pill--green' : 'a-pill--red'}`}>
                      {s.punti_liberi}
                    </span>
                  </td>
                  <td style={{ textAlign: 'center' }}>{s.punti_online}</td>
                  <td>
                    {s.in_manutenzione
                      ? <span className="a-pill a-pill--yellow">manutenzione</span>
                      : s.online
                        ? <span className="a-pill a-pill--green">online</span>
                        : <span className="a-pill a-pill--red">offline</span>}
                  </td>
                  <td>
                    {s.stato_setup === 'in_setup' ? (
                      <button className="a-btn a-btn--primary a-btn--sm" onClick={() => onSetup(s.id_stazione)}>
                        Completa setup
                      </button>
                    ) : (
                      <button
                        className={`a-btn a-btn--sm ${s.in_manutenzione ? 'a-btn--outline' : 'a-btn--danger'}`}
                        onClick={() => handleToggle(s.id_stazione)}
                        disabled={toggling === s.id_stazione}
                      >
                        {toggling === s.id_stazione ? '…' : s.in_manutenzione ? 'Riporta online' : 'Manutenzione'}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
}

// ── SEZIONE: Setup Stazione ───────────────────────────────────────────────────

function SetupStazione({ stationId, onBack }) {
  const [data, setData]       = useState(null);
  const [loading, setLoading] = useState(true);
  const [form, setForm]       = useState(null);
  const [puntiForm, setPuntiForm] = useState({});
  const [saving, setSaving]   = useState(false);
  const [msg, setMsg]         = useState(null);
  const [err, setErr]         = useState(null);

  useEffect(() => {
    apiClient.get(`/admin/stazioni/${encodeURIComponent(stationId)}/setup`)
      .then(r => {
        setData(r.data);
        setForm({
          nome:       r.data.stazione.nome ?? '',
          indirizzo:  r.data.stazione.indirizzo ?? '',
          latitudine: r.data.stazione.latitudine ?? '',
          longitudine: r.data.stazione.longitudine ?? '',
          tipo_area:  r.data.stazione.tipo_area ?? 'pubblico',
        });
        const pf = {};
        r.data.punti.forEach(p => {
          pf[p.id_punto] = {
            tipo_veicolo:    p.tipo_veicolo ?? 'auto',
            tipo_connettore: p.tipo_connettore ?? '',
            potenza_max_kw:  p.potenza_max_kw ?? '',
          };
        });
        setPuntiForm(pf);
      })
      .finally(() => setLoading(false));
  }, [stationId]);

  async function handleSubmit(e) {
    e.preventDefault();
    setSaving(true);
    setErr(null);
    try {
      const { data: r } = await apiClient.post(
        `/admin/stazioni/${encodeURIComponent(stationId)}/setup`,
        { ...form, punti: puntiForm }
      );
      setMsg(r.message);
      setTimeout(() => onBack(), 2000);
    } catch (e) {
      setErr(e.response?.data?.message ?? 'Errore durante il salvataggio.');
    } finally { setSaving(false); }
  }

  if (loading) return <div className="a-loading"><Spinner /></div>;
  if (!data)   return <Alert type="error">Stazione non trovata.</Alert>;

  const { stazione, punti } = data;

  return (
    <div>
      <button className="a-back-link" onClick={onBack}>← Torna alle stazioni</button>

      <div className="a-setup-header">
        <h2 className="a-page-title">Configura stazione</h2>
        <div className="a-setup-meta">
          <span>MAC: <code className="a-code">{stazione.id_stazione}</code></span>
          <span className={`a-pill ${stazione.stato_setup === 'attiva' ? 'a-pill--green' : 'a-pill--yellow'}`}>
            {stazione.stato_setup}
          </span>
          <span>Punti dichiarati dall'hardware: <strong>{punti.length}</strong></span>
        </div>
      </div>

      {msg && <Alert type="success">{msg}</Alert>}
      {err && <Alert type="error" onClose={() => setErr(null)}>{err}</Alert>}

      <form onSubmit={handleSubmit}>
        {/* Dati stazione */}
        <div className="a-card" style={{ marginBottom: '1rem' }}>
          <div className="a-card-header"><span className="a-card-title">Dati stazione</span></div>
          <div className="a-card-body">
            <div className="a-form-grid">
              <div className="a-form-group a-form-group--full">
                <label className="a-label">Nome stazione</label>
                <input className="a-input" required value={form.nome} onChange={e => setForm(f => ({ ...f, nome: e.target.value }))} />
              </div>
              <div className="a-form-group a-form-group--full">
                <label className="a-label">Indirizzo</label>
                <input className="a-input" value={form.indirizzo} onChange={e => setForm(f => ({ ...f, indirizzo: e.target.value }))} />
              </div>
              <div className="a-form-group">
                <label className="a-label">Latitudine</label>
                <input className="a-input" type="number" step="any" required value={form.latitudine} onChange={e => setForm(f => ({ ...f, latitudine: e.target.value }))} />
              </div>
              <div className="a-form-group">
                <label className="a-label">Longitudine</label>
                <input className="a-input" type="number" step="any" required value={form.longitudine} onChange={e => setForm(f => ({ ...f, longitudine: e.target.value }))} />
              </div>
              <div className="a-form-group">
                <label className="a-label">Tipo area</label>
                <select className="a-input" value={form.tipo_area} onChange={e => setForm(f => ({ ...f, tipo_area: e.target.value }))}>
                  <option value="pubblico">Pubblico</option>
                  <option value="privato">Privato</option>
                  <option value="aziendale">Aziendale</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        {/* Punti ricarica */}
        <div className="a-card" style={{ marginBottom: '1rem' }}>
          <div className="a-card-header"><span className="a-card-title">Punti di ricarica</span></div>
          <div className="a-card-body">
            <p className="a-sub-text" style={{ marginBottom: '0.75rem' }}>
              Configura i metadati per ciascuno dei {punti.length} punti registrati dall'hardware.
              Il numero di prese è deciso dall'hardware e non può essere modificato.
            </p>
            <div className="a-punti-table">
              <div className="a-punti-header">
                <span>Punto</span><span>Tipo veicolo</span><span>Connettore</span><span>Potenza max (kW)</span>
              </div>
              {punti.map(p => (
                <div key={p.id_punto} className="a-punti-row">
                  <div className="a-punti-id">
                    <span>{p.id_punto}</span>
                    <span className="a-sub-text" style={{ fontSize: '0.62rem' }}>PUNTO</span>
                  </div>
                  <div>
                    <select
                      className="a-input"
                      value={puntiForm[p.id_punto]?.tipo_veicolo ?? 'auto'}
                      onChange={e => setPuntiForm(f => ({ ...f, [p.id_punto]: { ...f[p.id_punto], tipo_veicolo: e.target.value } }))}
                    >
                      <option value="auto">Auto</option>
                      <option value="bici">Bici</option>
                      <option value="monopattino">Monopattino</option>
                    </select>
                  </div>
                  <div>
                    <input
                      className="a-input"
                      placeholder="es. Type2"
                      value={puntiForm[p.id_punto]?.tipo_connettore ?? ''}
                      onChange={e => setPuntiForm(f => ({ ...f, [p.id_punto]: { ...f[p.id_punto], tipo_connettore: e.target.value } }))}
                    />
                  </div>
                  <div>
                    <input
                      className="a-input"
                      type="number"
                      step="0.1"
                      min="0"
                      placeholder="es. 22"
                      value={puntiForm[p.id_punto]?.potenza_max_kw ?? ''}
                      onChange={e => setPuntiForm(f => ({ ...f, [p.id_punto]: { ...f[p.id_punto], potenza_max_kw: e.target.value } }))}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div className="a-form-actions">
          <button type="button" className="a-btn a-btn--outline" onClick={onBack}>Annulla</button>
          <button type="submit" className="a-btn a-btn--primary" disabled={saving}>
            {saving ? 'Salvataggio…' : 'Configura e attiva stazione'}
          </button>
        </div>
      </form>
    </div>
  );
}

// ── MAIN: AdminPage ───────────────────────────────────────────────────────────

export default function AdminPage() {
  const { user, logout } = useAuth();
  const navigate         = useNavigate();
  const location         = useLocation();

  // Sezione attiva: 'dashboard' | 'utenti' | 'sessioni' | 'stazioni'
  const [section, setSection]       = useState('dashboard');
  // Per drill-down
  const [selectedUser, setSelectedUser]     = useState(null);
  const [selectedStation, setSelectedStation] = useState(null);

  // Redirect se non admin (solo dopo che user è stato caricato)
  useEffect(() => {
    if (user === null) return;           // ancora in caricamento — aspetta
    if (user.ruolo !== 'admin') navigate('/react/map', { replace: true });
  }, [user, navigate]);

  // Schermata di attesa mentre l'utente viene caricato da localStorage
  if (!user) {
    return (
      <div style={{ display:'flex', alignItems:'center', justifyContent:'center', height:'100vh', background:'#F7F6F2' }}>
        <Spinner />
      </div>
    );
  }

  async function handleLogout() {
    await logout();
    window.location.href = '/react/login';
  }

  function goSection(s) {
    setSection(s);
    setSelectedUser(null);
    setSelectedStation(null);
  }

  function renderContent() {
    if (selectedStation) {
      return <SetupStazione stationId={selectedStation} onBack={() => { setSelectedStation(null); }} />;
    }
    if (selectedUser) {
      return <DettaglioUtente userId={selectedUser} onBack={() => setSelectedUser(null)} />;
    }
    switch (section) {
      case 'dashboard': return <Dashboard />;
      case 'utenti':    return <Utenti onSelect={id => setSelectedUser(id)} />;
      case 'sessioni':  return <Sessioni />;
      case 'stazioni':  return <Stazioni onSetup={id => setSelectedStation(id)} />;
      default:          return <Dashboard />;
    }
  }

  function pageTitle() {
    if (selectedStation) return 'Setup stazione';
    if (selectedUser)    return 'Dettaglio utente';
    return { dashboard: 'Dashboard', utenti: 'Gestione Utenti', sessioni: 'Sessioni', stazioni: 'Stazioni' }[section] ?? 'Admin';
  }

  const navItems = [
    { id: 'dashboard', icon: '📊', label: 'Dashboard' },
    { id: 'utenti',    icon: '👥', label: 'Utenti' },
    { id: 'sessioni',  icon: '⚡', label: 'Sessioni' },
    { id: 'stazioni',  icon: '🗺', label: 'Stazioni' },
  ];

  return (
    <div className="admin-layout">
      {/* Sidebar */}
      <aside className="admin-sidebar">
        <div className="a-sidebar-logo">
          <div className="a-sidebar-logo-row">
            <div className="a-sidebar-mark">⚙</div>
            <span className="a-sidebar-name">GreenSchool</span>
          </div>
          <span className="a-sidebar-badge">Pannello Admin</span>
        </div>

        <nav className="a-sidebar-nav">
          <span className="a-sidebar-section">Panoramica</span>
          {navItems.slice(0, 1).map(item => (
            <button
              key={item.id}
              className={`a-sidebar-link ${section === item.id && !selectedUser && !selectedStation ? 'active' : ''}`}
              onClick={() => goSection(item.id)}
            >
              <span className="a-sidebar-icon">{item.icon}</span>{item.label}
            </button>
          ))}
          <span className="a-sidebar-section">Gestione</span>
          {navItems.slice(1).map(item => (
            <button
              key={item.id}
              className={`a-sidebar-link ${section === item.id && !selectedUser && !selectedStation ? 'active' : ''}`}
              onClick={() => goSection(item.id)}
            >
              <span className="a-sidebar-icon">{item.icon}</span>{item.label}
            </button>
          ))}
        </nav>

        <div className="a-sidebar-footer">
          <div className="a-sidebar-user">
            <div className="a-sidebar-avatar">👤</div>
            <div>
              <p className="a-sidebar-user-name">{user?.nome} {user?.cognome}</p>
              <p className="a-sidebar-user-role">Amministratore</p>
            </div>
          </div>
          <button className="a-sidebar-back-btn" onClick={() => navigate('/react/map')}>
            ← Torna al sito
          </button>
          <button className="a-sidebar-logout" onClick={handleLogout}>Esci</button>
        </div>
      </aside>

      {/* Main */}
      <div className="admin-main">
        <div className="a-topbar">
          <span className="a-topbar-title">{pageTitle()}</span>
          <span className="a-topbar-date">
            {new Date().toLocaleDateString('it-IT', { day: '2-digit', month: 'long', year: 'numeric' })}
          </span>
        </div>
        <div className="admin-content">
          {renderContent()}
        </div>
      </div>
    </div>
  );
}