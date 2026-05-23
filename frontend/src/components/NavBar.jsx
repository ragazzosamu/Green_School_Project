// NavBar condivisa: una sola fonte di verita' per ordine voci, link SPA
// (react-router) e hamburger mobile. Sostituisce le nav copiate-incollate
// dentro MapPage/ClassificaPage/ScuolaPage/SessionPage/ProfilePage/StationDetail.
import { useState, useEffect } from 'react';
import { Link, useLocation, useNavigate } from 'react-router-dom';
import { useAuth } from '../context/AuthContext';
import './NavBar.css';

const VOCI = [
  { to: '/react/map',        label: 'Mappa',      icon: '🗺️' },
  { to: '/react/profilo',    label: 'Profilo',    icon: '👤' },
  { to: '/react/classifica', label: 'Classifica', icon: '🏆' },
  { to: '/react/scuola',     label: 'Scuola',     icon: '🏫' },
  { to: '/react/sessione',   label: 'Sessione',   icon: '⚡' },
];

export default function NavBar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();

  const [menuOpen, setMenuOpen] = useState(false);

  // Chiudi il drawer mobile ogni volta che cambia la rotta
  useEffect(() => { setMenuOpen(false); }, [location.pathname]);

  async function handleLogout() {
    await logout();
    navigate('/react/login', { replace: true });
  }

  function isActive(path) {
    if (path === '/react/admin') return location.pathname.startsWith('/react/admin');
    return location.pathname === path;
  }

  return (
    <>
      <nav className="gs-nav">
        <div className="gs-nav-inner">
          <Link to="/react/map" className="gs-logo">
            <div className="gs-logo-mark">🌱</div>
            <span className="gs-logo-text">GreenSchool</span>
          </Link>

          {/* Desktop */}
          <div className="gs-nav-right">
            {user && (
              <>
                <span className="gs-nav-user">{user.nome}</span>
                <div className="gs-nav-divider" />
                {VOCI.map((v) => (
                  <Link key={v.to} to={v.to}
                        className={`gs-nav-link ${isActive(v.to) ? 'active' : ''}`}>
                    {v.label}
                  </Link>
                ))}
                {user.ruolo === 'admin' && (
                  <>
                    <div className="gs-nav-divider" />
                    <Link to="/react/admin"
                          className={`gs-nav-link gs-nav-admin ${isActive('/react/admin') ? 'active' : ''}`}>
                      ⚙ Admin
                    </Link>
                  </>
                )}
                <div className="gs-nav-divider" />
                <button onClick={handleLogout} className="gs-logout-btn">Esci</button>
              </>
            )}
          </div>

          {/* Hamburger (mobile) */}
          {user && (
            <button
              className="gs-hamburger"
              aria-label="Menu"
              aria-expanded={menuOpen}
              onClick={() => setMenuOpen((v) => !v)}
            >
              <span /><span /><span />
            </button>
          )}
        </div>
      </nav>

      {/* Drawer mobile */}
      {user && menuOpen && (
        <div className="gs-mobile-menu" onClick={(e) => { if (e.target === e.currentTarget) setMenuOpen(false); }}>
          <div className="gs-mobile-overlay" />
          <div className="gs-mobile-drawer">
            <p className="gs-mobile-user">{user.nome}</p>
            {VOCI.map((v) => (
              <Link key={v.to} to={v.to}
                    className={`gs-nav-link ${isActive(v.to) ? 'active' : ''}`}>
                {v.icon} {v.label}
              </Link>
            ))}
            {user.ruolo === 'admin' && (
              <Link to="/react/admin"
                    className={`gs-nav-link gs-nav-admin ${isActive('/react/admin') ? 'active' : ''}`}>
                ⚙ Admin
              </Link>
            )}
            <div className="gs-nav-divider" />
            <button onClick={handleLogout} className="gs-logout-btn">Esci</button>
          </div>
        </div>
      )}
    </>
  );
}
