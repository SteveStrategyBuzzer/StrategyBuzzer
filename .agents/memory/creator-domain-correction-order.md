---
name: Frontière des domaines créateurs
description: Contrat des Depths et domaines créateurs, distinct de la commande Shuffle Gameplay.
---

Le contrat obligatoire est :

1. les Depths officiels sont `2, 4, 6, 7, 8, 9, 10` ; le Depth 10 est
   officiel et ne doit jamais être traité comme ancien ou anormal ;
2. les domaines créateurs sont exactement `GEO, HIS, FAU, ART, SPO, CIN, CUI,
   SCI`, également segments `DO` canoniques du `kernel_code` ;
3. `kernel_depth_domain_totals` conserve exactement les 56 couples officiels
   (7 Depths × 8 domaines) comme compteurs dérivés des reçus
   `CURRENT_KERNEL_RECEIVED` ;
4. `General` est exclu de la création intellectuelle (Rotation, Blueprint,
   Taxonomy, QuestionIntent et Phase 1), mais demeure la commande d’agrégation
   Shuffle/Gameplay de contenu déjà publié provenant des huit domaines.
5. Taxonomy reçoit le code créateur de Rotation, utilise le nom anglais du
   registre et ne peut persister que des sous-domaines, sujets et idées en anglais.
6. Toute conversion des clés CKR exige l’arrêt vérifié des writers persistants ;
   un simple verrou de table ne protège pas contre un ancien worker après COMMIT.

**Why:** Une identité créatrice canonique ne doit pas être déduite d’un slug,
d’un libellé administratif ou d’une commande Gameplay. La séparation empêche
`General` de devenir un neuvième domaine sans modifier le comportement Shuffle.

**How to apply:** Utiliser un registre fermé partagé et des adaptateurs explicites
pour les slugs, valeurs anglaises et libellés français. Les APIs propriétaires
exposent des colonnes fixes, jamais un tableau libre. Modifier et valider Rotation
avant Taxonomy. Toute contradiction avec ce contrat est bloquante.