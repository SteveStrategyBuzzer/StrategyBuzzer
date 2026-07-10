<?php

namespace App\Services\QuestionBank;

/**
 * KernelBlueprint — contrat vivant du noyau intellectuel.
 *
 * ══ PHILOSOPHIE ═══════════════════════════════════════════════════════════════
 *
 * Le KernelBlueprint n'est pas un DTO, ni un objet de transfert, ni un slot.
 *
 * Il constitue l'unique enveloppe de travail commune à tous les moteurs du pipeline.
 * Il est créé UNE SEULE FOIS au début d'une rotation par KernelRotationPlanner.
 * Il est conservé vivant pendant tout le pipeline, jamais recréé.
 * Il est progressivement enrichi par les moteurs successifs selon leur contrat.
 *
 * Le Blueprint :
 *   - ne crée rien ;
 *   - ne décide rien ;
 *   - ne valide rien ;
 *   - transporte uniquement les informations du noyau actif.
 *
 * ══ CONTRAT DE CHAQUE MOTEUR ══════════════════════════════════════════════════
 *
 * Chaque moteur respecte obligatoirement :
 *   - lit uniquement les slots dont il a besoin ;
 *   - écrit uniquement les slots dont il est propriétaire ;
 *   - ne modifie jamais les slots appartenant à un autre moteur.
 *
 * ══ CYCLE DE VIE ══════════════════════════════════════════════════════════════
 *
 *   Création      KernelRotationPlanner crée le Blueprint et le remet au pipeline.
 *   Partie 1      Identité intellectuelle du noyau (ce fichier).
 *   Partie 2      Création des contenus cognitifs (Phase 1) — à venir.
 *   Partie 3      Validation Phase 1 — à venir.
 *   Partie 4      Phase 2 — à venir.
 *   Partie 5      Validation Phase 2 — à venir.
 *   Partie 6      READY_BANK — à venir.
 *
 * Chaque nouvelle partie sera ajoutée au MÊME Blueprint, qui restera vivant
 * pendant tout le pipeline.
 *
 * ══ PARTIE 1 — IDENTITÉ INTELLECTUELLE DU NOYAU (6 champs) ═══════════════════
 *
 *   depth               — créé + écrit par KernelRotationPlanner
 *   domain              — créé + écrit par KernelRotationPlanner
 *   subdomain_active    — écrit par Taxonomy
 *   subject_active      — écrit par Taxonomy
 *   dominant_idea_active— écrit par Taxonomy (après validation DominantIdeaValidator)
 *   kernel_code         — écrit par KernelCodeEngine
 *
 * ══ RÉPARTITION OFFICIELLE DES RESPONSABILITÉS ════════════════════════════════
 *
 *   KernelRotationPlanner
 *     Crée le Blueprint, écrit depth + domain.
 *     Premier moteur — ne lit aucune donnée du Blueprint.
 *     Aucun moteur suivant ne peut modifier depth ni domain.
 *
 *   DepthContract[depth]
 *     N'est pas un moteur. Contrat architectural pris automatiquement
 *     par chaque moteur selon Blueprint.depth.
 *     Contient : objectifs pédagogiques, niveau de précision, règles
 *     KnowledgeFrequency. Reste entièrement externe au Blueprint.
 *
 *   Taxonomy
 *     Lit depth + domain. Travaille dans le réservoir (depth × domain).
 *     Les réservoirs ne transitent jamais dans le Blueprint.
 *     Écrit subdomain_active + subject_active + dominant_idea_active.
 *     dominant_idea_active n'est écrit QU'APRÈS validation DominantIdeaValidator.
 *     En cas de FAIL → Taxonomy propose une nouvelle idée, même slot.
 *     En cas de NO_MORE_IDEAS → passe au sujet suivant.
 *
 *   KernelCodeEngine (à spécifier ultérieurement)
 *     Lit les 5 champs d'identité (depth, domain, subdomain_active,
 *     subject_active, dominant_idea_active).
 *     Écrit kernel_code — seul propriétaire de ce champ.
 *     Ne peut s'exécuter qu'après validation complète de l'identité.
 *
 * ══ RÈGLES ARCHITECTURALES ════════════════════════════════════════════════════
 *
 *   - Aucune règle n'est stockée dans le Blueprint.
 *   - DepthContract reste entièrement externe.
 *   - RotationRules restent dans KernelRotationPlanner.
 *   - TaxonomyRules restent dans Taxonomy.
 *   - KernelCodeFormat / KernelCodeContract restent dans KernelCodeEngine.
 *   - KnowledgeFrequency est défini par DepthContract — jamais dans le Blueprint.
 *   - rotation_identifier ne transite jamais dans le Blueprint.
 *   - knowledge_frequency ne transite jamais dans le Blueprint.
 */
class KernelBlueprint
{
    // ─── Partie 1 — 6 champs de l'identité intellectuelle ────────────────────

    /**
     * Propriétaire : KernelRotationPlanner.
     * Détermine le DepthContract utilisé par tous les moteurs suivants.
     * Immuable après écriture initiale.
     */
    public ?int $depth = null;

    /**
     * Propriétaire : KernelRotationPlanner.
     * Détermine le réservoir exploité par Taxonomy.
     * Domaines autorisés : Géographie, Histoire, Faune, Art, Sport, Cinéma, Cuisine, Général.
     * Immuable après écriture initiale.
     */
    public ?string $domain = null;

    /**
     * Propriétaire : Taxonomy.
     * Découle du domaine actif. Plus précis que le domaine, jamais un sujet déguisé.
     * Immuable après écriture initiale.
     */
    public ?string $subdomain_active = null;

    /**
     * Propriétaire : Taxonomy.
     * Appartient au sous-domaine actif. Court, fermé, sans réponse ni indice.
     * Immuable après écriture initiale.
     */
    public ?string $subject_active = null;

    /**
     * Propriétaire : Taxonomy (propose) + DominantIdeaValidator (valide).
     * N'est inscrit dans le Blueprint QU'APRÈS obtention d'un PASS de DominantIdeaValidator.
     * En cas de FAIL → même slot actif, nouvelle proposition de Taxonomy.
     * En cas de NO_MORE_IDEAS → sujet considéré terminé, Taxonomy avance.
     * Immuable après écriture initiale.
     */
    public ?string $dominant_idea_active = null;

    /**
     * Propriétaire : KernelCodeEngine.
     * Produit uniquement après validation complète des 5 champs d'identité.
     * Format : yy-xx-xxx-xxx-xxx-zz (KernelCodeContract).
     * Immuable après écriture initiale.
     */
    public ?string $kernel_code = null;

    // ═════════════════════════════════════════════════════════════════════════
    // Méthodes d'écriture — une méthode par contrat de responsabilité
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Appelée par KernelRotationPlanner uniquement — premier moteur.
     *
     * KernelRotationPlanner crée le Blueprint puis appelle cette méthode.
     * Remplit depth + domain. Ne touche pas aux champs Taxonomy ni kernel_code.
     * Aucun moteur suivant ne peut modifier ces deux champs.
     */
    public function fillRotation(int $depth, string $domain): void
    {
        $this->depth  = $depth;
        $this->domain = $domain;
    }

    /**
     * Appelée par Taxonomy uniquement — après validation DominantIdeaValidator.
     *
     * Taxonomy lit depth + domain (déjà écrits par KernelRotationPlanner).
     * dominant_idea_active ne doit être passé ici QU'APRÈS obtention d'un PASS.
     * Ne touche pas à depth, domain, kernel_code.
     */
    public function fillTaxonomy(
        string $subdomainActive,
        string $subjectActive,
        string $dominantIdeaActive
    ): void {
        $this->subdomain_active     = $subdomainActive;
        $this->subject_active       = $subjectActive;
        $this->dominant_idea_active = $dominantIdeaActive;
    }

    /**
     * Appelée par KernelCodeEngine uniquement.
     *
     * Précondition : isIdentityComplete() === true.
     * Lit les 5 champs d'identité — ne les modifie jamais.
     * Aucun autre moteur ne peut modifier kernel_code.
     */
    public function fillKernelCode(string $kernelCode): void
    {
        $this->kernel_code = $kernelCode;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers d'état — lecture seule
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Vérifie que KernelRotationPlanner a rempli sa partie (depth + domain).
     */
    public function isRotationFilled(): bool
    {
        return $this->depth !== null && $this->domain !== null;
    }

    /**
     * Vérifie que Taxonomy a rempli sa partie
     * (subdomain_active + subject_active + dominant_idea_active).
     * dominant_idea_active est garanti PASS de DominantIdeaValidator si true.
     */
    public function isTaxonomyFilled(): bool
    {
        return $this->subdomain_active !== null
            && $this->subject_active !== null
            && $this->dominant_idea_active !== null;
    }

    /**
     * Vérifie que les 5 champs d'identité sont remplis.
     * Précondition obligatoire pour que KernelCodeEngine puisse écrire kernel_code.
     */
    public function isIdentityComplete(): bool
    {
        return $this->isRotationFilled() && $this->isTaxonomyFilled();
    }

    /**
     * Vérifie que la Partie 1 est entièrement complète (kernel_code inclus).
     * Précondition pour passer à la Partie 2 du pipeline.
     */
    public function isComplete(): bool
    {
        return $this->isIdentityComplete() && $this->kernel_code !== null;
    }

    /**
     * Exporte les 6 champs de la Partie 1 sous forme de tableau.
     * Aucune règle, aucun contrat, aucune métadonnée.
     */
    public function toArray(): array
    {
        return [
            'depth'                => $this->depth,
            'domain'               => $this->domain,
            'subdomain_active'     => $this->subdomain_active,
            'subject_active'       => $this->subject_active,
            'dominant_idea_active' => $this->dominant_idea_active,
            'kernel_code'          => $this->kernel_code,
        ];
    }
}
