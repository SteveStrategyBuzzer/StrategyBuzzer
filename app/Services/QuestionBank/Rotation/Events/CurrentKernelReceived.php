<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation\Events;

/**
 * CurrentKernelReceived — événement transactionnel ReadyBank → KRP.
 *
 * Déclenché dans la même transaction que la réception ReadyBank.
 * Transmis via kernel_pipeline_outbox (Outbox pattern — DEC-063).
 *
 * Champs obligatoires du payload (schema_version = 1) :
 *   event_id       — identifiant unique de l'événement
 *   event_type     — toujours CURRENT_KERNEL_RECEIVED
 *   schema_version — toujours 1
 *   blueprint_id   — identité canonique du Blueprint
 *   depth          — depth du Blueprint
 *   domain         — domain_code du Blueprint
 *   occurred_at    — horodatage de l'événement
 */
final class CurrentKernelReceived
{
    public function __construct(
        public readonly string $eventId,
        public readonly string $blueprintId,
        public readonly int    $depth,
        public readonly string $domain,
        public readonly string $occurredAt,
        public readonly int    $schemaVersion = 1,
        public readonly string $eventType     = 'CURRENT_KERNEL_RECEIVED',
    ) {}

    /**
     * Exporte le payload pour persistance dans kernel_pipeline_outbox.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'event_id'       => $this->eventId,
            'event_type'     => $this->eventType,
            'schema_version' => $this->schemaVersion,
            'blueprint_id'   => $this->blueprintId,
            'depth'          => $this->depth,
            'domain'         => $this->domain,
            'occurred_at'    => $this->occurredAt,
        ];
    }

    /**
     * Recrée un événement depuis un payload JSON (rejeu Outbox).
     *
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): self
    {
        return new self(
            eventId:       (string) $payload['event_id'],
            blueprintId:   (string) $payload['blueprint_id'],
            depth:         (int) $payload['depth'],
            domain:        (string) $payload['domain'],
            occurredAt:    (string) $payload['occurred_at'],
            schemaVersion: (int) ($payload['schema_version'] ?? 1),
        );
    }
}
