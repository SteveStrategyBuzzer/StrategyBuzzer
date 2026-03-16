/**
 * TimeSyncService — serveur autoritaire pour la synchronisation d'horloge.
 *
 * Chaque client peut envoyer un ping_sync au serveur pour obtenir son offset.
 * Le serveur retourne serverReceivedAtMs, permettant au client de calculer :
 *   offset = serverReceivedAtMs - clientSentAtMs - (roundtripMs / 2)
 *
 * Cet offset est conservé localement par le client et utilisé pour corriger
 * l'affichage du timer (display only — jamais pour arbitrer le gameplay).
 * L'arbitre reste toujours le serveur.
 */

export type TimeSyncRequest = {
  clientSentAtMs: number;
};

export type TimeSyncResponse = {
  clientSentAtMs: number;
  serverReceivedAtMs: number;
  serverSentAtMs: number;
};

export class TimeSyncService {
  handlePing(clientSentAtMs: number): TimeSyncResponse {
    const serverReceivedAtMs = Date.now();
    return {
      clientSentAtMs,
      serverReceivedAtMs,
      serverSentAtMs: Date.now(),
    };
  }
}

export const timeSyncService = new TimeSyncService();
