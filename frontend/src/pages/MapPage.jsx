import { useEffect, useState, useCallback } from 'react';
import { MapContainer, TileLayer, CircleMarker, Popup } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { useAuth } from '../context/AuthContext';
import { useStationsEcho } from '../hooks/useStationsEcho';
import apiClient from '../api/client';
import './MapPage.css';

const DEFAULT_LAT  = 45.6713;
const DEFAULT_LNG  = 11.9286;
const DEFAULT_ZOOM = 14;

function markerColor(station) {
  if (!station.attiva) return '#9CA3AF';
  if (station.libera === false) return '#DC2626';
  return '#16A34A';
}

function markerLabel(station) {
  if (!station.attiva) return 'Offline';
  if (station.libera === false) return 'Occupata';
  return 'Libera';
}

export default function MapPage() {
  const { logout, user } = useAuth();
  const [rawStations, setRawStations] = useState([]);
  const [loading, setLoading]         = useState(true);
  const [fetchError, setFetchError]   = useState('');

  const { stations } = useStationsEcho(rawStations);

  const loadStations = useCallback(async () => {
    setLoading(true);
    setFetchError('');
    try {
      const { data } = await apiClient.get('/stations');
      setRawStations(data.data ?? data);
    } catch (err) {
      console.error('[MapPage] fetch /stations:', err);
      setFetchError('Impossibile caricare le stazioni. Riprova più tardi.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadStations(); }, [loadStations]);

  function handleStationClick(stationId) {
    window.location.href = `/react/stazione/${stationId}`;
  }

  async function handleLogout() {
    await logout();
    window.location.href = '/react/login';
  }

  return (
    <div className="map-page">
      <header className="map-header">
        <a href="/" className="logo">
          <div className="logo-mark">🌱</div>
          <span className="logo-text">GreenSchool</span>
        </a>
        <nav className="map-nav">
          <a href="/react/classifica" className="nav-link">🏆 Classifica</a>
         <a href="/react/scuola"     className="nav-link">🏫 Scuola</a>
          <a href="/react/profilo"    className="nav-link">👤 Profilo</a>
          {user?.is_admin &&  (
            <a href="/react/admin"      className="nav-link nav-link--admin">⚙️ Admin</a>
          )}
          <button onClick={handleLogout} className="logout-btn">Esci</button>
        </nav>
      </header>

      <main className="map-main">
        <div className="page-header">
          <div>
            <p className="page-eyebrow">Rete scolastica</p>
            <h1 className="page-title">Mappa colonnine</h1>
          </div>
          <div className="legend">
            <div className="legend-item">
              <span className="legend-dot green" />
              Libera
            </div>
            <span className="legend-sep" />
            <div className="legend-item">
              <span className="legend-dot red" />
              Occupata
            </div>
            <span className="legend-sep" />
            <div className="legend-item">
              <span className="legend-dot gray" />
              Offline
            </div>
          </div>
        </div>

        <div className="map-wrap">
          {loading && (
            <div className="map-overlay">
              <span className="map-spinner" />
              <p>Caricamento stazioni…</p>
            </div>
          )}
          {fetchError && (
            <div className="map-overlay map-overlay--error">
              <p>{fetchError}</p>
              <button onClick={loadStations} className="retry-btn">Riprova</button>
            </div>
          )}

          <MapContainer
            center={[DEFAULT_LAT, DEFAULT_LNG]}
            zoom={DEFAULT_ZOOM}
            style={{ width: '100%', height: 'calc(100vh - 220px)', minHeight: '400px' }}
          >
            <TileLayer
              url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
              attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            />
            {stations.map((station) => (
              <CircleMarker
                key={station.id_stazione}
                center={[parseFloat(station.latitudine), parseFloat(station.longitudine)]}
                radius={10}
                pathOptions={{
                  fillColor: markerColor(station),
                  fillOpacity: 0.9,
                  color: '#ffffff',
                  weight: 2,
                }}
                eventHandlers={{
                  click: () => handleStationClick(station.id_stazione),
                }}
              >
                <Popup>
                  <div className="popup-content">
                    <div className="popup-header">
                      <span className="popup-status-dot" style={{ background: markerColor(station) }} />
                      <span className="popup-status-label">{markerLabel(station)}</span>
                    </div>
                    <h3 className="popup-name">{station.nome}</h3>
                    {station.indirizzo && (
                      <p className="popup-address">{station.indirizzo}</p>
                    )}
                    <p className="popup-points">
                      {station.punti_ricarica?.length ?? 0} punto/i di ricarica
                    </p>
                    <button
                      className="popup-btn"
                      onClick={() => handleStationClick(station.id_stazione)}
                    >
                      Dettagli →
                    </button>
                  </div>
                </Popup>
              </CircleMarker>
            ))}
          </MapContainer>
        </div>
      </main>
    </div>
  );
}