import { useState, useEffect, useRef } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function buildEcho(token) {
  const isHttps = window.location.protocol === 'https:';
  return new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: window.location.hostname,
    wsPort: isHttps ? 443 : 80,
    wssPort: 443,
    forceTLS: isHttps,
    enabledTransports: ['ws', 'wss'],
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    },
  });
}

/**
 * useSessionChannel
 * Ascolta il canale privato `user.{userId}` per eventi real-time della sessione.
 * @param {string|null} userId  - id_utente dell'utente autenticato
 * @param {string|null} token   - Bearer token Sanctum
 * @param {object|null} initialSession - dati sessione pre-caricati via REST
 */
export function useSessionChannel(userId, token, initialSession) {
  const [session, setSession] = useState(initialSession ?? null);
  const echoRef = useRef(null);

  // Aggiorna sessione quando arrivano i dati REST iniziali
  useEffect(() => {
    if (initialSession) setSession(initialSession);
  }, [initialSession]);

  // Connessione al canale privato user.{id}
  useEffect(() => {
    if (!userId || !token) return;

    try {
      echoRef.current = buildEcho(token);
      const channel = echoRef.current.private(`user.${userId}`);

      // Sessione avviata (nel caso la pagina venga aperta durante l'avvio)
      channel.listen('.sessione.avviata', (e) => {
        setSession((prev) => ({
          ...(prev ?? {}),
          ...e,
          kwh: e.kwh_totali ?? prev?.kwh ?? 0,
        }));
      });

      // Aggiornamento kWh in tempo reale (telemetria ogni ~5s)
      channel.listen('.ricarica.heartbeat', (e) => {
        setSession((prev) => {
          if (!prev) return prev;
          return {
            ...prev,
            kwh: e.kwh_totali ?? prev.kwh,
            voltaggio: e.voltaggio,
            corrente: e.corrente,
            potenza_kw: e.voltaggio && e.corrente
              ? ((e.voltaggio * e.corrente) / 1000).toFixed(2)
              : prev.potenza_kw,
          };
        });
      });
    } catch (err) {
      console.error('[Echo] Errore canale privato:', err);
    }

    return () => {
      echoRef.current?.leaveChannel(`private-user.${userId}`);
    };
  }, [userId, token]);

  return { session, setSession };
}