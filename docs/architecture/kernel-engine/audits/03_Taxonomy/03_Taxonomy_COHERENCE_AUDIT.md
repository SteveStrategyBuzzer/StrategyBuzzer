# 03_Taxonomy — Audit final de cohérence

**Date :** 2026-08-16  
**Spécification auditée :** `03_Taxonomy_ACTIVE_SPEC.md` v1.0  
**Portée :** Taxonomy ↔ KernelBlueprint ↔ KRP actif ↔ brides VDI/QuestionIntent

## 1. Taxonomy ↔ KernelBlueprint

| Contrôle | Verdict |
|---|---|
| Blueprint existe avant Taxonomy | PASS |
| KRP possède `depth + domain` | PASS |
| Taxonomy lit seulement `depth + domain` comme entrée intellectuelle | PASS |
| Taxonomy écrit seulement `subdomain_active + subject_active + dominant_idea_active` | PASS |
| Réservoirs Taxonomy hors Blueprint | PASS |
| aucune occurrence de bassin ajoutée comme slot Blueprint | PASS |
| `kernel_code` reste aval | PASS |
| triplet Taxonomy write-once | PASS |

## 2. Taxonomy ↔ KRP

| Contrôle | Verdict |
|---|---|
| Taxonomy ne choisit pas prochain Depth/Domain | PASS |
| `DOMAIN_EXHAUSTED` vient de Taxonomy | PASS |
| `DEPTH_EXHAUSTED` vient de Taxonomy | PASS |
| signaux prospectifs | PASS |
| KRP garde le cadran VISIBLE/ESTOMPÉ | PASS |
| pas de recul ESTOMPÉ→VISIBLE dans le même tour | PASS |
| doublon signal = NO-OP côté KRP | PASS |
| KRP persiste avant progression | PASS |
| `DEPTH_EXHAUSTED` termine un tour | PASS |
| `cycle_completed[depth] += 1` appartient à KRP/DepthNeedMatrix | PASS |
| `cycle_target` appartient à DepthNeedMatrix | PASS |
| après Depth10, retour vers prochain Depth nécessaire possible | PASS architecture active v3.3 |
| `PRODUCTION_ON_HOLD` seulement quand toutes les cibles sont satisfaites | PASS architecture active v3.3 |

**Note de version :** la v3.2 historique de KRP reste verrouillée comme archive, mais sa décision qui avait retiré `cycle_target/cycle_completed` du chemin actif doit être supersédée dans v3.3.

## 3. Répétition des Depths / occurrence de bassin

Contradiction résolue : un simple `(Depth + Domain)` ne peut pas identifier durablement un bassin lorsque `cycle_target` impose plusieurs tours du même Depth.

Règle active :

```text
Bassin actif = Depth + occurrence du tour de Depth + Domain
```

Cette occurrence est interne à Taxonomy et n’ajoute aucun slot Blueprint.

Verdict : **PASS**.

## 4. Taxonomy ↔ ValidationDominantIdeas

| Contrôle | Verdict |
|---|---|
| VDI reste document 04 | PASS |
| VDI = mécanisme/règles utilisé par Gemini | PASS |
| VDI ne lit pas Blueprint directement | PASS |
| VDI ne possède pas les Banks Taxonomy | PASS |
| Taxonomy possède anti-doublon et mémoires | PASS |
| PASS/FAIL retournés par Subject | PASS |

## 5. Taxonomy ↔ QuestionIntent

| Contrôle | Verdict |
|---|---|
| Taxonomy finit le triplet intellectuel avant QuestionIntent | PASS |
| Taxonomy ne crée pas `kernel_code` | PASS |
| QuestionIntent n’a pas besoin des Banks Taxonomy | PASS |

## 6. Erreurs / reprise

| Contrôle | Verdict |
|---|---|
| erreur technique Gemini ≠ épuisement | PASS |
| 4 tentatives max par opération | PASS |
| 3 opérations complètes non résolues → BLOCKED | PASS |
| succès → compteur 0 | PASS |
| TAX-003 empêche épuisement prématuré | PASS |
| reprise depuis curseur persistant | PASS |

## 7. Verdict

```text
Architecture Taxonomy : 100 %
Contrat Taxonomy :      100 %
Cohérence Blueprint :   PASS
Cohérence KRP active :  PASS, sous obligation de réécrire KRP v3.3
Cohérence VDI :         PASS à la frontière
Cohérence QuestionIntent : PASS
```

**VERDICT : 03_Taxonomy v1.0 — SPÉCIFICATION VERROUILLÉE.**

Aucune implantation Taxonomy n’est autorisée avant la consolidation de l’Architecture Register et la reconstruction de `02_KernelRotationPlanner` v3.3 exigée par les décisions actives sur les besoins globaux.