// ── App.jsx aggiornato ───────────────────────────────────────────────────────
// Aggiunge la rotta /react/admin protetta, visibile solo agli utenti admin.
import RegisterPage from './pages/RegisterPage';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import PrivateRoute from './components/PrivateRoute';
import LoginPage    from './pages/LoginPage';
import MapPage      from './pages/MapPage';
import SessionPage  from './pages/SessionPage';
import ProfilePage  from './pages/ProfilePage';
import AdminPage    from './pages/AdminPage';   // ← AGGIUNTO

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          {/* Redirect radice */}
          <Route path="/"       element={<Navigate to="/react/login" replace />} />
          <Route path="/react"  element={<Navigate to="/react/login" replace />} />

          {/* Pubblica */}
          <Route path="/react/login" element={<LoginPage />} />

          {/* Protette utente */}
          <Route path="/react/map" element={
            <PrivateRoute><MapPage /></PrivateRoute>
          } />
          <Route path="/react/sessione" element={
            <PrivateRoute><SessionPage /></PrivateRoute>
          } />
          <Route path="/react/profilo" element={
            <PrivateRoute><ProfilePage /></PrivateRoute>
          } />

          {/* Admin — accessibile solo se ruolo === 'admin' (il componente stesso fa il redirect) */}
          <Route path="/react/admin" element={
            <PrivateRoute><AdminPage /></PrivateRoute>
          } />
          <Route path="/react/admin/*" element={
            <PrivateRoute><AdminPage /></PrivateRoute>
          } />
<Route path="/react/register" element={<RegisterPage />} />
          {/* Catch-all */}
          <Route path="*" element={<Navigate to="/react/login" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
