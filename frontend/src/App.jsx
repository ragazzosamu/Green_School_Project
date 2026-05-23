// ── App.jsx aggiornato ───────────────────────────────────────────────────────
// Aggiunge la rotta /react/admin protetta, visibile solo agli utenti admin.
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import PrivateRoute       from './components/PrivateRoute';
import LoginPage          from './pages/LoginPage';
import RegisterPage       from './pages/RegisterPage';
import MapPage            from './pages/MapPage';
import StationDetailPage  from './pages/StationDetailPage';
import SessionPage        from './pages/SessionPage';
import ProfilePage        from './pages/ProfilePage';
import ClassificaPage     from './pages/ClassificaPage';
import ScuolaPage         from './pages/ScuolaPage';
import AdminPage          from './pages/AdminPage';

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          {/* Redirect radice */}
          <Route path="/"       element={<Navigate to="/react/login" replace />} />
          <Route path="/react"  element={<Navigate to="/react/login" replace />} />

          {/* Pubbliche */}
          <Route path="/react/login"    element={<LoginPage />} />
          <Route path="/react/register" element={<RegisterPage />} />

          {/* Protette utente */}
          <Route path="/react/map" element={
            <PrivateRoute><MapPage /></PrivateRoute>
          } />
          <Route path="/react/stazione/:id" element={
            <PrivateRoute><StationDetailPage /></PrivateRoute>
          } />
          <Route path="/react/classifica" element={
            <PrivateRoute><ClassificaPage /></PrivateRoute>
          } />
          <Route path="/react/scuola" element={
            <PrivateRoute><ScuolaPage /></PrivateRoute>
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

          {/* Catch-all */}
          <Route path="*" element={<Navigate to="/react/login" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}
