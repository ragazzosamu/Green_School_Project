import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { AuthProvider } from './context/AuthContext';
import PrivateRoute from './components/PrivateRoute';
import LoginPage from './pages/LoginPage';
import MapPage from './pages/MapPage';

export default function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Navigate to="/react/login" replace />} />
          <Route path="/react" element={<Navigate to="/react/login" replace />} />

          {/* Rotte pubbliche */}
          <Route path="/react/login" element={<LoginPage />} />

          {/* Rotte protette */}
          <Route
            path="/react/map"
            element={
              <PrivateRoute>
                <MapPage />
              </PrivateRoute>
            }
          />

          {/* Catch-all */}
          <Route path="*" element={<Navigate to="/react/login" replace />} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}