import { useState, useEffect, useRef } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function buildEcho() {
  const isHttps = window.location.protocol === 'https:';
  return new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost:  window.location.hostname,
    wsPort:  isHttps ? 443 : 80,
    wssPort: 443,
    forceTLS: isHttps,
    enabledTransports: ['ws', 'wss'],
  });
}

export function useStationsEcho(initialStations) {
  const [stations, setStations] = useState(initialStations ?? []);
  const echoRef = useRef(null);

  // Quando arrivano le stazioni dal fetch iniziale le carichiamo
  useEffect(() => {
    if (initialStations?.length) {
      setStations(initialStations);
    }
  }, [initialStations]);

  // Connessione Echo: una sola volta al mount
  useEffect(() => {
    try {
      echoRef.current = buildEcho();
      const channel = echoRef.current.channel('mappa');

      // Stazione libera/occupata
      channel.listen('.stazione.status', (e) => {
        setStations((prev) =>
          prev.map((s) =>
            s.id_stazione === e.id_stazione
              ? { ...s, libera: e.libera }
              : s,
          ),
        );
      });

      // Stato hardware singolo punto di ricarica
      channel.listen('.punto.status', (e) => {
        setStations((prev) =>
          prev.map((s) => ({
            ...s,
            punti_ricarica: s.punti_ricarica?.map((p) =>
              p.id_punto === e.id_punto
                ? { ...p, stato_hardware: e.stato_hardware }
                : p,
            ),
          })),
        );
      });
    } catch (err) {
      console.error('[Echo] Errore connessione:', err);
    }

    return () => {
      echoRef.current?.leaveChannel('mappa');
    };
  }, []);

  return { stations, setStations };
}