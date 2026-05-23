import { useState } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './LoginPage.css';

export default function LoginPage() {
  const { login } = useAuth();
  const navigate  = useNavigate();
  const location  = useLocation();
  const from      = location.state?.from?.pathname ?? '/react/map';

  const [email,    setEmail]    = useState('');
  const [password, setPassword] = useState('');
  const [error,    setError]    = useState('');
  const [loading,  setLoading]  = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setLoading(true);
    try {
      await login({ email, password });
      navigate(from, { replace: true });
    } catch (err) {
      const apiErrors = err.response?.data?.errors;
      const data      = err.response?.data;
      if (apiErrors?.email) {
        setError(Array.isArray(apiErrors.email) ? apiErrors.email[0] : apiErrors.email);
      } else if (apiErrors?.password) {
        setError(Array.isArray(apiErrors.password) ? apiErrors.password[0] : apiErrors.password);
      } else if (data?.message) {
        // Se l'API espone i tentativi rimasti, lo aggiungiamo al messaggio
        // (stessa UX del Blade: "Tentativi rimanenti: 3").
        const tail = typeof data.tentativi_rimasti === 'number'
          ? ` Tentativi rimanenti: ${data.tentativi_rimasti}.`
          : '';
        setError(data.message + tail);
      } else {
        setError('Errore di rete. Riprova.');
      }
    } finally {
      setLoading(false);
    }
  }

  return (
    <>
      <div className="bg-layer" aria-hidden="true">
        <div className="bg-gradient" />
        <div className="bg-grid" />
        <div className="bg-dots" />
        <div className="bg-rings">
          <div className="ring ring-1" />
          <div className="ring ring-2" />
          <div className="ring ring-3" />
        </div>
      </div>

      <nav>
        <a href="/" className="logo">
          <div className="logo-mark">🌱</div>
          <span className="logo-text">GreenSchool</span>
        </a>
        <span className="nav-label">Portale studenti</span>
      </nav>

      <div className="page-center">
        <div className="headline-wrap">
          <div className="status-pill">
            <span className="pill-dot" />
            Ricarica intelligente
          </div>
          <h1 className="main-headline">Bentornato<em>.</em></h1>
          <p className="main-sub">
            Accedi per gestire le colonnine di ricarica del tuo istituto in tempo reale.
          </p>
        </div>

        <div className="login-card">
          <div className="card-strip" />
          <div className="card-body">
            <h2 className="card-title">Accedi</h2>
            <p className="card-sub">Inserisci le credenziali del tuo account</p>

            <form onSubmit={handleSubmit} noValidate>
              <div className="field-group">
                <label className="field-label" htmlFor="email">Indirizzo email</label>
                <input
                  className="field-input"
                  id="email"
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  required
                  autoComplete="email"
                  autoFocus
                  placeholder="nome@scuola.it"
                />
              </div>

              <div className="field-group">
                <label className="field-label" htmlFor="password">Password</label>
                <input
                  className="field-input"
                  id="password"
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  required
                  autoComplete="current-password"
                  placeholder="••••••••"
                />
              </div>

              {error && (
                <p className="field-error field-error--block" role="alert">
                  {error}
                </p>
              )}

              <button type="submit" className="submit-btn" disabled={loading}>
                {loading ? 'Accesso in corso…' : 'Accedi al portale →'}
              </button>
            </form>
          </div>

          <div className="card-footer-link">
            Non hai un account? <Link to="/react/register">Registrati</Link>
          </div>

          <div className="card-features">
            <div className="cf-item">
              <div className="cf-icon">⚡</div>
              <div className="cf-label">Tempo reale</div>
            </div>
            <div className="cf-item">
              <div className="cf-icon">📍</div>
              <div className="cf-label">Mappa live</div>
            </div>
            <div className="cf-item">
              <div className="cf-icon">📊</div>
              <div className="cf-label">Storico</div>
            </div>
          </div>
        </div>
      </div>

      <footer>
        GreenSchool © {new Date().getFullYear()} — Portale di ricarica scolastica
      </footer>
    </>
  );
}