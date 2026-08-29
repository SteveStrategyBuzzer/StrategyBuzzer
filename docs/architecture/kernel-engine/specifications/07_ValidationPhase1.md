# STRATEGYBUZZER — 07_VALIDATIONPHASE1

**Version :** 0.2  
**Date :** 29 août 2026  
**Statut :** FRONTIÈRE OFFICIELLE VERROUILLÉE — MODULE À COMPLÉTER  
**Décision :** DEC-122  
**Implémentation :** À AUDITER  
**Validation terminale :** NON

---

# 1. Mission verrouillée

ValidationPhase1 valide officiellement les contenus source des sept CognitiveSlots du même KernelBlueprint.

Elle n’ajoute aucun cognitif, ne traduit rien et ne modifie jamais l’identité intellectuelle.

Les autocontrôles retournés pendant l’appel de création Phase1 sont des éléments de preuve préventifs. Ils ne remplacent jamais la décision indépendante de ValidationPhase1.

# 2. Entrées validées

Pour chaque CognitiveSlot, ValidationPhase1 lit au minimum :

```text
cognitive_type
question
correct_answer
choices
sv
self_checks de création
depth
domain
subdomain_active
subject_active
dominant_idea_active
kernel_code
```

Elle ne modifie aucune donnée de la Section 1.

# 3. Contrôles officiels

ValidationPhase1 contrôle au minimum :

- présence et structure des champs obligatoires;
- conformité au `cognitive_type`;
- factualité de la question et de la bonne réponse;
- bonne réponse répondant exactement à la question;
- bonne réponse présente parmi les choix;
- exactement quatre choix et une bonne réponse pour un QCM;
- exactement deux choix pour un Vrai/Faux;
- plausibilité et qualité des choix;
- absence d’ambiguïté créant plusieurs bonnes réponses;
- absence de doublon textuel direct;
- absence de répétition conceptuelle interdite entre les sept slots;
- question lisible en moins de huit secondes par une personne lisant normalement à légèrement lentement;
- SV lisible en moins de trente secondes;
- SV expliquant la bonne réponse dans le contexte cognitif;
- absence de remplissage artificiel;
- cohérence contextuelle complète.

Chaîne de cohérence obligatoire :

```text
question
→ bonne réponse
→ choix
→ SV
→ dominant_idea_active
→ subject_active
→ subdomain_active
```

Une information vraie mais appartenant à un autre sous-domaine ou à un autre contexte intellectuel est refusée.

Le Depth détermine la difficulté intellectuelle. Il ne justifie jamais une question ou un SV inutilement plus long.

# 4. Autorité de décision

ValidationPhase1 est l’autorité qui décide officiellement :

```text
PASS
ou
SUSPICION → QUARANTINE
```

Une déclaration `self_checks=true` produite par le même appel de création ne suffit jamais à établir PASS.

Les contrôles déterministes sont exécutés localement lorsque possible. Un contrôle intellectuel additionnel peut être nécessaire pour la factualité, la conformité cognitive, la qualité des distracteurs ou la répétition conceptuelle.

# 5. Sortie sans suspicion

Un CognitiveSlot source validé peut poursuivre vers Phase2 / Traductions.

Aucune traduction ne commence pour un CognitiveSlot source qui n’a pas obtenu la validation requise.

# 6. Sortie avec suspicion

Toute suspicion source :

- identifie exactement le CognitiveSlot et les champs concernés;
- conserve la raison SV et les preuves disponibles;
- déclenche une copie complète du Blueprint vers Quarantine;
- permet l’affichage en rouge des chemins soupçonnés;
- bloque la création des traductions dépendantes de ce CognitiveSlot;
- n’empêche pas le Blueprint canonique de poursuivre jusqu’à ReadyBank;
- ne marque pas les traductions non créées comme erreurs de traduction.

Un champ absent, une structure technique invalide ou un slot non créé est ciblé comme source non admissible. Aucun contenu partiel n’est validé silencieusement.

# 7. Revalidation d’une copie corrigée

Une copie corrigée reprend ValidationPhase1 uniquement pour les slots et champs ciblés.

Après PASS, le CognitiveSlot peut poursuivre vers Phase2 pour produire les traductions manquantes.

Les slots déjà validés et non ciblés ne sont pas rejoués.

# 8. Invariants

- copie Quarantine complète;
- ciblage structuré;
- source suspecte non traduite;
- slots valides non rejoués;
- même `blueprint_id`;
- même `kernel_code`;
- aucun accès gameplay avant validations requises;
- aucun `question_code`, `COG` ou `VAR` créé;
- temps de lecture évalué sans minimum artificiel de caractères;
- sous-domaine utilisé comme frontière contextuelle finale;
- autocontrôle Gemini distinct de la validation officielle.

# 9. Statut restant

Restent à spécifier :

- règles intellectuelles détaillées de chacun des sept cognitifs;
- codes de raisons PASS/SUSPICION;
- seuil et mécanisme anti-répétition sémantique;
- méthode versionnée d’estimation du temps de lecture par langue;
- règles détaillées des distracteurs;
- schémas de preuve;
- retries et escalade technique;
- états détaillés des CognitiveSlots.

La présente version verrouille les validations déjà autorisées sans déclarer ValidationPhase1 terminée.
