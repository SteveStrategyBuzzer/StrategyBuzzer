<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use RuntimeException;

/**
 * DepthTourState — valeur immuable de l'état du Tour de Depth actif.
 *
 * Responsabilités :
 *   - Mémoriser l'état ON/OFF des 8 Domaines officiels pour le Tour courant
 *   - Calculer la progression (0/8 → 8/8)
 *   - Sélectionner le prochain Domaine ON dans le DomainCycle
 *
 * Interdictions :
 *   - Ne prend aucune décision de rotation
 *   - Ne touche pas à la base de données
 *   - Ne connaît pas les Depths, les cibles, ni les noyaux reçus
 *
 * DomainCycle officiel (ordre figé — DEC-061, DEC-067) :
 *   Géographie → Histoire → Faune → Art → Sport → Cinéma → Cuisine → Science
 *
 * Signal EMPTY : Domaine ON → OFF (idempotent si déjà OFF).
 * Tour terminé  : 8 Domaines OFF.
 */
final class DepthTourState
{
    /** Ordre officiel du DomainCycle. Général exclu. */
    public const DOMAIN_CYCLE = [
        'geographie',
        'histoire',
        'faune',
        'art',
        'sport',
        'cinema',
        'cuisine',
        'science',
    ];

    public const STATE_ON  = 'ON';
    public const STATE_OFF = 'OFF';

    /** @var array<string, string>  Ex : ['geographie' => 'ON', 'histoire' => 'OFF', …] */
    private array $states;

    /** Nombre de transitions ON → OFF dans ce Tour (0..8). */
    private int $emptyProgress;

    private function __construct(array $states, int $emptyProgress)
    {
        $this->states        = $states;
        $this->emptyProgress = $emptyProgress;
    }

    // =========================================================================
    // Constructeurs statiques
    // =========================================================================

    /**
     * Crée un Tour de Depth frais — tous les Domaines ON, progression 0/8.
     */
    public static function initTour(): self
    {
        $states = [];
        foreach (self::DOMAIN_CYCLE as $domain) {
            $states[$domain] = self::STATE_ON;
        }

        return new self($states, 0);
    }

    /**
     * Recrée un DepthTourState depuis un tableau persisté.
     *
     * @param array{states: array<string, string>, empty_progress: int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['states'],
            (int) ($data['empty_progress'] ?? 0)
        );
    }

    // =========================================================================
    // Mutation (retourne toujours une nouvelle instance)
    // =========================================================================

    /**
     * Applique un signal EMPTY sur un Domaine.
     *
     * Si le Domaine est ON  → passe à OFF, incrémente la progression.
     * Si le Domaine est OFF → retourne l'instance actuelle sans changement (NO-OP idempotent).
     *
     * @throws RuntimeException si le Domaine est inconnu.
     */
    public function applyEmpty(string $domain): self
    {
        if (! array_key_exists($domain, $this->states)) {
            throw new RuntimeException(
                "[DepthTourState] Domaine inconnu : '{$domain}'. "
                . 'Domaines valides : ' . implode(', ', self::DOMAIN_CYCLE)
            );
        }

        if ($this->states[$domain] === self::STATE_OFF) {
            return $this; // idempotent
        }

        $newStates          = $this->states;
        $newStates[$domain] = self::STATE_OFF;

        return new self($newStates, $this->emptyProgress + 1);
    }

    // =========================================================================
    // Lecture
    // =========================================================================

    /**
     * Retourne true si le Domaine est ON.
     *
     * @throws RuntimeException si le Domaine est inconnu.
     */
    public function isOn(string $domain): bool
    {
        if (! array_key_exists($domain, $this->states)) {
            throw new RuntimeException(
                "[DepthTourState] Domaine inconnu : '{$domain}'."
            );
        }

        return $this->states[$domain] === self::STATE_ON;
    }

    /**
     * Retourne true si le Domaine est OFF.
     */
    public function isOff(string $domain): bool
    {
        return ! $this->isOn($domain);
    }

    /**
     * Nombre de transitions ON → OFF dans ce Tour (0..8).
     */
    public function getEmptyProgress(): int
    {
        return $this->emptyProgress;
    }

    /**
     * Retourne true lorsque tous les 8 Domaines sont OFF (Tour 8/8).
     */
    public function isTourComplete(): bool
    {
        return $this->emptyProgress >= count(self::DOMAIN_CYCLE);
    }

    /**
     * Retourne la liste des Domaines encore ON, dans l'ordre du DomainCycle.
     *
     * @return string[]
     */
    public function getOnDomains(): array
    {
        return array_values(array_filter(
            self::DOMAIN_CYCLE,
            fn(string $d) => ($this->states[$d] ?? self::STATE_OFF) === self::STATE_ON
        ));
    }

    /**
     * Sélectionne le prochain Domaine ON dans le DomainCycle officiel.
     *
     * Règle :
     *   - Part du Domaine précédent ($previousDomain), parcourt circulairement.
     *   - Ignore les Domaines OFF.
     *   - Retourne le premier Domaine ON trouvé.
     *   - Si $previousDomain est null → retourne le premier Domaine ON du cycle.
     *   - Si aucun Domaine ON → retourne null (Tour complet ou état vide).
     *
     * Aucun curseur numérique n'est persisté (DEC-061).
     */
    public function getNextOnDomain(?string $previousDomain): ?string
    {
        $cycle = self::DOMAIN_CYCLE;
        $count = count($cycle);

        if ($previousDomain === null) {
            foreach ($cycle as $domain) {
                if (($this->states[$domain] ?? self::STATE_OFF) === self::STATE_ON) {
                    return $domain;
                }
            }
            return null;
        }

        $startIndex = array_search($previousDomain, $cycle, true);

        if ($startIndex === false) {
            // Domaine inconnu → fallback au premier ON
            foreach ($cycle as $domain) {
                if (($this->states[$domain] ?? self::STATE_OFF) === self::STATE_ON) {
                    return $domain;
                }
            }
            return null;
        }

        for ($offset = 1; $offset <= $count; $offset++) {
            $domain = $cycle[($startIndex + $offset) % $count];
            if (($this->states[$domain] ?? self::STATE_OFF) === self::STATE_ON) {
                return $domain;
            }
        }

        return null;
    }

    /**
     * États bruts des Domaines (lecture seule).
     *
     * @return array<string, string>
     */
    public function getStates(): array
    {
        return $this->states;
    }

    // =========================================================================
    // Persistance
    // =========================================================================

    /**
     * Exporte l'état pour persistance dans kernel_rotation_state_v2.
     *
     * @return array{states: array<string, string>, empty_progress: int}
     */
    public function toArray(): array
    {
        return [
            'states'         => $this->states,
            'empty_progress' => $this->emptyProgress,
        ];
    }
}
