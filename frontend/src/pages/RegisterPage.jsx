import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './LoginPage.css';

export default function RegisterPage() {
  const { login } = useAuth();
  const navigate  = useNavigate();

  const [form, setForm] = useState({
    nome: '', cognome: '', email: '',
    cellulare: '', password: '', password_confirmation: '',
  });
  const [error,   setError]   = useState('');
  const [loading, setLoading] = useState(false);

  function handleChange(e) {
    setForm({ ...form, [e.target.name]: e.target.value });
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    if (form.password !== form.password_confirmation) {
      setError('Le password non coincidono.');
      return;
    }

    setLoading(true);
    try {
      const res = await fetch('/api/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(form),
      });
      const data = await res.json();

      if (!res.ok) {
        const errors = data.errors;
        if (errors) {
          const first = Object.values(errors)[0];
          setError(Array.isArray(first) ? first[0] : first);
        } else {
          setError(data.message ?? 'Errore durante la registrazione.');
        }
        return;
      }

      // Login automatico dopo registrazione
      await login({ email: form.email, password: form.password });
      navigate('/react/map', { replace: true });
    } catch {
      setError('Errore di rete. Riprova.');
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
          <h1 className="main-headline">Benvenuto<em>.</em></h1>
          <p className="main-sub">
            Crea un account per iniziare a usare le colonnine di ricarica del tuo istituto.
          </p>
        </div>

        <div className="login-card">
          <div className="card-strip" />
          <div className="card-body">
            <h2 className="card-title">Registrati</h2>
            <p className="card-sub">Compila i campi per creare il tuo account</p>

            <form onSubmit={handleSubmit} noValidate>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
                <div className="field-group">
                  <label className="field-label" htmlFor="nome">Nome</label>
                  <input
                    className="field-input"
                    id="nome" name="nome" type="text"
                    value={form.nome} onChange={handleChange}
                    required placeholder="Mario"
                  />
                </div>
                <div className="field-group">
                  <label className="field-label" htmlFor="cognome">Cognome</label>
                  <input
                    className="field-input"
                    id="cognome" name="cognome" type="text"
                    value={form.cognome} onChange={handleChange}
                    required placeholder="Rossi"
                  />
                </div>
              </div>

              <div className="field-group">
                <label className="field-label" htmlFor="email">Indirizzo email</label>
                <input
                  className="field-input"
                  id="email" name="email" type="email"
                  value={form.email} onChange={handleChange}
                  required placeholder="nome@scuola.it"
                />
              </div>

              <div className="field-group">
                <label className="field-label" htmlFor="cellulare">Cellulare <span style={{opacity:0.5}}>(opzionale)</span></label>
                <input
                  className="field-input"
                  id="cellulare" name="cellulare" type="tel"
                  value={form.cellulare} onChange={handleChange}
                  placeholder="+39 333 1234567"
                />
              </div>

              <div className="field-group">
                <label className="field-label" htmlFor="password">Password</label>
                <input
                  className="field-input"
                  id="password" name="password" type="password"
                  value={form.password} onChange={handleChange}
                  required placeholder="Minimo 8 caratteri"
                />
              </div>

              <div className="field-group">
                <label className="field-label" htmlFor="password_confirmation">Conferma password</label>
                <input
                  className="field-input"
                  id="password_confirmation" name="password_confirmation" type="password"
                  value={form.password_confirmation} onChange={handleChange}
                  required placeholder="Ripeti la password"
                />
              </div>

              {error && (
                <p className="field-error field-error--block" role="alert">{error}</p>
              )}

              <button type="submit" className="submit-btn" disabled={loading}>
                {loading ? 'Registrazione in corso…' : 'Crea account →'}
              </button>
            </form>
          </div>

          <div className="card-footer-link">
            Hai già un account? <Link to="/react/login">Accedi</Link>
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
