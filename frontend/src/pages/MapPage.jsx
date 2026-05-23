import { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { MapContainer, TileLayer, CircleMarker, Popup } from 'react-leaflet';
import 'leaflet/dist/leaflet.css';
import { useStationsEcho } from '../hooks/useStationsEcho';
import NavBar from '../components/NavBar';
import apiClient from '../api/client';
import './MapPage.css';

// Allineati a backend/src/config/map.php (Castelfranco)
const DEFAULT_LAT  = 45.6722;
const DEFAULT_LNG  = 11.9272;
const DEFAULT_ZOOM = 14;

// Stessa logica di map.blade.php → getStazioneColor():
//   - grigio: nessun punto, oppure nessun punto online
//   - verde : almeno un punto online + libero
//   - rosso : altrimenti (tutti i punti online sono occupati)
function markerColor(station) {
  const punti = station.punti_ricarica ?? [];
  if (punti.length === 0) return '#9CA3AF';
  const tuttiOffline = punti.every((p) => p.stato_hardware !== 'online');
  if (tuttiOffline) return '#9CA3AF';
  const haPuntiLiberi = punti.some(
    (p) => Number(p.libera) === 1 && p.stato_hardware === 'online',
  );
  return haPuntiLiberi ? '#16A34A' : '#DC2626';
}

function markerLabel(station) {
  const c = markerColor(station);
  if (c === '#9CA3AF') return 'Offline';
  if (c === '#DC2626') return 'Occupata';
  return 'Libera';
}

export default function MapPage() {
  const navigate = useNavigate();
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
    navigate(`/react/stazione/${stationId}`);
  }

  return (
    <div className="map-page">
      <NavBar />

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