import { useState, useEffect, useRef } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function buildEcho() {
  const key    = import.meta.env.VITE_REVERB_APP_KEY;
  const host   = import.meta.env.VITE_REVERB_HOST   || window.location.hostname;
  const scheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
  const port   = Number(import.meta.env.VITE_REVERB_PORT) || Number(window.location.port) || 5173;
  const isHttps = scheme === 'https';

  if (!key) {
    console.error(
      '[Echo] VITE_REVERB_APP_KEY mancante. Crea frontend/.env (vedi .env.example) e riavvia il container react.'
    );
  }

  return new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: port,
    wssPort: port,
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

      // Stazione intera libera/occupata (flag aggregato lato server)
      channel.listen('.stazione.status', (e) => {
        setStations((prev) =>
          prev.map((s) =>
            s.id_stazione === e.id_stazione
              ? { ...s, libera: e.libera }
              : s,
          ),
        );
      });

      // Cambio stato libero/occupato di un singolo punto di ricarica.
      // Il colore della stazione viene poi ricalcolato da markerColor()
      // sulla base dei punti aggiornati (vedi MapPage.jsx).
      channel.listen('.punto.status', (e) => {
        setStations((prev) =>
          prev.map((s) => {
            if (e.id_stazione && s.id_stazione !== e.id_stazione) return s;
            const punti = s.punti_ricarica?.map((p) =>
              p.id_punto === e.id_punto
                ? { ...p, libera: e.libera ? 1 : 0 }
                : p,
            );
            return { ...s, punti_ricarica: punti };
          }),
        );
      });

      // Cambio stato hardware (online/offline/guasto/manutenzione)
      // di un singolo punto di ricarica.
      channel.listen('.punto.hardware.status', (e) => {
        setStations((prev) =>
          prev.map((s) => {
            if (e.id_stazione && s.id_stazione !== e.id_stazione) return s;
            const punti = s.punti_ricarica?.map((p) =>
              p.id_punto === e.id_punto
                ? { ...p, stato_hardware: e.stato_hardware }
                : p,
            );
            return { ...s, punti_ricarica: punti };
          }),
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