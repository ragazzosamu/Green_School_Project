import { useState, useEffect, useRef } from 'react';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function buildEcho(token) {
  const key    = import.meta.env.VITE_REVERB_APP_KEY;
  const host   = import.meta.env.VITE_REVERB_HOST   || window.location.hostname;
  const scheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
  const port   = Number(import.meta.env.VITE_REVERB_PORT) || Number(window.location.port) || 5173;
  const isHttps = scheme === 'https';

  if (!key) {
    // Lo segnaliamo forte: senza key qui Reverb rifiuta l'handshake e
    // il sintomo e' "i kWh non si aggiornano in tempo reale". Vedi
    // frontend/.env.example: copia .env.example in .env e riavvia Vite.
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
    // Endpoint auth dedicato a Sanctum (vedi routes/api.php):
    // il default /broadcasting/auth usa middleware 'web' (sessione + CSRF)
    // che React non ha, quindi senza questo override Echo non riusciva
    // a sottoscrivere il PrivateChannel `user.{id}` e gli eventi di
    // telemetria non arrivavano mai (-> kWh non si aggiornavano live).
    authEndpoint: '/api/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
        Accept: 'application/json',
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

      // Sessione avviata (cavo collegato -> sessione partita)
      channel.listen('.sessione.avviata', (e) => {
        setSession((prev) => ({
          ...(prev ?? {}),
          ...e,
          kwh: 0,
        }));
      });

      // Telemetria ogni ~5s: l'evento broadcasta SOLO il delta in
      // `cambiamento_kwh` (vedi backend/src/app/Events/TelemetriaRicevuta.php).
      // Va accumulato lato client — il payload non contiene il totale.
      channel.listen('.ricarica.heartbeat', (e) => {
        setSession((prev) => {
          if (!prev) return prev;
          if (e.id_sessione && prev.id_sessione && e.id_sessione !== prev.id_sessione) {
            return prev;
          }
          const delta = Number(e.cambiamento_kwh) || 0;
          const totale = (Number(prev.kwh) || 0) + delta;
          return { ...prev, kwh: totale };
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