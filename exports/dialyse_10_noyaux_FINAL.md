# Dialyse 10 noyaux — État final réel

**Date :** 2026-05-22 18:12:12
**Noyaux :** 4, 7, 34, 46, 64, 67, 85, 100, 121, 139
**Fixes appliqués :** P0 true_false contract · P1 Jaccard saviez_vous_off_topic

---

## Résumé global

| # | Noyau ID | Domaine | Depth | Variantes | Statut | Langs manquantes |
|---|---|---|---|---|---|---|
| 1 | #4 | Histoire | d4 | 5/5 | ✅ COMPLET | — |
| 2 | #7 | Sport | d4 | 4/5 | 🟡 INCOMPLET | — |
| 3 | #34 | Géographie | d5 | 4/5 | 🟡 INCOMPLET | — |
| 4 | #46 | Cinéma | d5 | 2/5 | 🟡 INCOMPLET | — |
| 5 | #64 | Cuisine | d6 | 3/5 | 🟡 INCOMPLET | — |
| 6 | #67 | Science | d6 | 2/5 | 🟡 INCOMPLET | — |
| 7 | #85 | Art | d7 | 3/5 | 🟡 INCOMPLET | — |
| 8 | #100 | Histoire | d8 | 3/5 | 🟡 INCOMPLET | — |
| 9 | #121 | Faune | d8 | 2/5 | 🟡 INCOMPLET | — |
| 10 | #139 | Science | d9 | 3/5 | 🟡 INCOMPLET | — |

---

## NOYAU 1 — #4 · Histoire · depth 4

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 4 |
| intent_key | legacy_declaration-independance-date |
| semantic_key | histoire-guerre-independance-americaine |
| domain | Histoire |
| sub_domain | Histoire |
| difficulty_depth | 4 |
| subject | Guerre d'Indépendance américaine |
| angle_large | Conflits historiques |
| micro_angle | Chronologie et dates clés |
| answer_target | Année de la Déclaration d'Indépendance |
| potential_trap | Confusion 1776 vs 1783 (traité de Paris) |
| concept_family | guerre-independance-americaine |
| dialysis_status | complete |
| dialysed_at | 2026-05-22 16:41:11 |

### 2. État final

**Statut :** ✅ COMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 5/5 |
| Variantes manquantes | — |
| Toutes langues complètes | Oui |
| Quality flags actifs | ✅ Aucun |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 8 |
| readable_code | HI-D04-Q-R-4EFC3 |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | guerre-independance-americaine |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** En quelle année la Déclaration d'indépendance des États-Unis a-t-elle été signée ?

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (131 chars) :** John Hancock a été le premier à signer la Déclaration d'indépendance, et sa signature est la plus grande et la plus reconnaissable.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** In what year was the United States Declaration of Independence signed?

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (123 chars) :** John Hancock was the first to sign the Declaration of Independence, and his signature is the largest and most recognizable.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿En qué año se firmó la Declaración de Independencia de los Estados Unidos?

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (120 chars) :** John Hancock fue el primero en firmar la Declaración de Independencia, y su firma es la más grande y la más reconocible.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** In welchem Jahr wurde die Unabhängigkeitserklärung der Vereinigten Staaten unterzeichnet?

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (131 chars) :** John Hancock war der Erste, der die Unabhängigkeitserklärung unterzeichnete, und seine Unterschrift ist die größte und bekannteste.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** In che anno fu firmata la Dichiarazione di indipendenza degli Stati Uniti?

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (122 chars) :** John Hancock fu il primo a firmare la Dichiarazione di indipendenza e la sua firma è la più grande e la più riconoscibile.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Em que ano foi assinada a Declaração de Independência dos Estados Unidos?

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (118 chars) :** John Hancock foi o primeiro a assinar a Declaração de Independência, e sua assinatura é a maior e a mais reconhecível.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** В каком году была подписана Декларация независимости США?

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (104 chars) :** Джон Хэнкок был первым, кто подписал Декларацию независимости, и его подпись самая большая и узнаваемая.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 美国独立宣言是在哪一年签署的？

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (36 chars) :** 约翰·汉考克是第一个签署《独立宣言》的人，他的签名是最大和最容易辨认的。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** في أي عام تم توقيع إعلان استقلال الولايات المتحدة؟

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (80 chars) :** كان جون هانكوك أول من وقع على إعلان الاستقلال، وتوقيعه هو الأكبر والأكثر تميزًا.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Σε ποιο έτος υπογράφηκε η Διακήρυξη της Ανεξαρτησίας των Ηνωμένων Πολιτειών;

| Clé | Réponse |
|---|---|
| A | 1776 ✅ |
| B | 1789 |
| C | 1775 |
| D | 1783 |

**Correcte :** [A]

**Saviez-vous (131 chars) :** Ο John Hancock ήταν ο πρώτος που υπέγραψε τη Διακήρυξη της Ανεξαρτησίας και η υπογραφή του είναι η μεγαλύτερη και πιο αναγνωρίσιμη.

</details>

---

#### Variante : `qcm/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2516 |
| readable_code | HI-D04-Q-S-08E6F |
| question_type | qcm |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | ottoman-history |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quelle a été la principale cause du 'Siège de Constantinople' par les Ottomans en 1453 ?

| Clé | Réponse |
|---|---|
| A | Faiblesse militaire byzantine ✅ |
| B | Épidémie de peste à Byzance |
| C | Alliance byzantine avec Venise |
| D | Soutien du Pape à Byzance |

**Correcte :** [A]

**Saviez-vous (160 chars) :** Après la conquête, Mehmet II a déplacé sa capitale à Constantinople et a transformé la basilique Sainte-Sophie en mosquée, symbolisant le changement de pouvoir.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What was the main cause of the 'Siege of Constantinople' by the Ottomans in 1453?

| Clé | Réponse |
|---|---|
| A | Byzantine military weakness ✅ |
| B | Plague epidemic in Byzantium |
| C | Byzantine alliance with Venice |
| D | Pope's support for Byzantium |

**Correcte :** [A]

**Saviez-vous (155 chars) :** After the conquest, Mehmet II moved his capital to Constantinople and transformed the Hagia Sophia basilica into a mosque, symbolizing the change of power.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál fue la principal causa del 'Sitio de Constantinopla' por los otomanos en 1453?

| Clé | Réponse |
|---|---|
| A | Debilidad militar bizantina ✅ |
| B | Epidemia de peste en Bizancio |
| C | Alianza bizantina con Venecia |
| D | Apoyo del Papa a Bizancio |

**Correcte :** [A]

**Saviez-vous (161 chars) :** Después de la conquista, Mehmet II trasladó su capital a Constantinopla y transformó la basílica de Santa Sofía en una mezquita, simbolizando el cambio de poder.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Was war die Hauptursache für die 'Belagerung von Konstantinopel' durch die Osmanen im Jahr 1453?

| Clé | Réponse |
|---|---|
| A | Byzantinische militärische Schwäche ✅ |
| B | Pestepidemie in Byzanz |
| C | Byzantinisches Bündnis mit Venedig |
| D | Unterstützung des Papstes für Byzanz |

**Correcte :** [A]

**Saviez-vous (170 chars) :** Nach der Eroberung verlegte Mehmet II. seine Hauptstadt nach Konstantinopel und verwandelte die Hagia Sophia-Basilika in eine Moschee, was den Machtwechsel symbolisierte.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è stata la causa principale dell' 'Assedio di Costantinopoli' da parte degli Ottomani nel 1453?

| Clé | Réponse |
|---|---|
| A | Debolezza militare bizantina ✅ |
| B | Epidemia di peste a Bisanzio |
| C | Alleanza bizantina con Venezia |
| D | Sostegno del Papa a Bisanzio |

**Correcte :** [A]

**Saviez-vous (166 chars) :** Dopo la conquista, Mehmet II trasferì la sua capitale a Costantinopoli e trasformò la basilica di Santa Sofia in una moschea, simboleggiando il cambiamento di potere.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual foi a principal causa do 'Cerco de Constantinopla' pelos otomanos em 1453?

| Clé | Réponse |
|---|---|
| A | Fraqueza militar bizantina ✅ |
| B | Epidemia de peste em Bizâncio |
| C | Aliança bizantina com Veneza |
| D | Apoio do Papa a Bizâncio |

**Correcte :** [A]

**Saviez-vous (156 chars) :** Após a conquista, Mehmet II mudou sua capital para Constantinopla e transformou a basílica de Hagia Sophia em uma mesquita, simbolizando a mudança de poder.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Что было главной причиной «Осады Константинополя» османами в 1453 году?

| Clé | Réponse |
|---|---|
| A | Византийская военная слабость ✅ |
| B | Эпидемия чумы в Византии |
| C | Византийский союз с Венецией |
| D | Поддержка Папы Римского Византии |

**Correcte :** [A]

**Saviez-vous (136 chars) :** После завоевания Мехмед II перенес свою столицу в Константинополь и превратил базилику Святой Софии в мечеть, символизируя смену власти.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 1453年奥斯曼帝国“君士坦丁堡围攻”的主要原因是什么？

| Clé | Réponse |
|---|---|
| A | 拜占庭军事实力薄弱 ✅ |
| B | 拜占庭瘟疫流行 |
| C | 拜占庭与威尼斯结盟 |
| D | 教皇对拜占庭的支持 |

**Correcte :** [A]

**Saviez-vous (47 chars) :** 征服之后，穆罕默德二世将首都迁至君士坦丁堡，并将圣索菲亚大教堂改造成清真寺，象征着权力的更迭。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هو السبب الرئيسي لـ 'حصار القسطنطينية' من قبل العثمانيين في عام 1453؟

| Clé | Réponse |
|---|---|
| A | الضعف العسكري البيزنطي ✅ |
| B | وباء الطاعون في بيزنطة |
| C | التحالف البيزنطي مع البندقية |
| D | دعم البابا لبيزنطة |

**Correcte :** [A]

**Saviez-vous (111 chars) :** بعد الفتح، نقل محمد الثاني عاصمته إلى القسطنطينية وحول كاتدرائية آيا صوفيا إلى مسجد، مما يرمز إلى تغيير السلطة.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποια ήταν η κύρια αιτία της «Πολιορκίας της Κωνσταντινούπολης» από τους Οθωμανούς το 1453;

| Clé | Réponse |
|---|---|
| A | Βυζαντινή στρατιωτική αδυναμία ✅ |
| B | Επιδημία πανώλης στο Βυζάντιο |
| C | Βυζαντινή συμμαχία με τη Βενετία |
| D | Υποστήριξη του Πάπα προς το Βυζάντιο |

**Correcte :** [A]

**Saviez-vous (169 chars) :** Μετά την κατάκτηση, ο Μωάμεθ Β' μετέφερε την πρωτεύουσά του στην Κωνσταντινούπολη και μετέτρεψε τη βασιλική της Αγίας Σοφίας σε τζαμί, συμβολίζοντας την αλλαγή εξουσίας.

</details>

---

#### Variante : `qcm/deceptive_trap`

| Champ | Valeur |
|---|---|
| question_group_id | 2520 |
| readable_code | HI-D04-Q-D-A81BE |
| question_type | qcm |
| cognitive_type | deceptive_trap |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | cold-war-history |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quand le 'Blocus de Berlin' a-t-il commencé ?

| Clé | Réponse |
|---|---|
| A | Juin 1948 ✅ |
| B | Septembre 1950 |
| C | Avril 1947 |
| D | Janvier 1949 |

**Correcte :** [A]

**Saviez-vous (168 chars) :** Le blocus a conduit au pont aérien de Berlin, où des avions alliés ont livré des tonnes de fournitures à la ville coupée, démontrant un engagement envers la population.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** When did the 'Berlin Blockade' begin?

| Clé | Réponse |
|---|---|
| A | June 1948 ✅ |
| B | September 1950 |
| C | April 1947 |
| D | January 1949 |

**Correcte :** [A]

**Saviez-vous (153 chars) :** The blockade led to the Berlin Airlift, where Allied planes delivered tons of supplies to the cut-off city, demonstrating a commitment to the population.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuándo comenzó el 'Bloqueo de Berlín'?

| Clé | Réponse |
|---|---|
| A | Junio de 1948 ✅ |
| B | Septiembre de 1950 |
| C | Abril de 1947 |
| D | Enero de 1949 |

**Correcte :** [A]

**Saviez-vous (168 chars) :** El bloqueo condujo al puente aéreo de Berlín, donde aviones aliados entregaron toneladas de suministros a la ciudad aislada, demostrando un compromiso con la población.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Wann begann die 'Berliner Blockade'?

| Clé | Réponse |
|---|---|
| A | Juni 1948 ✅ |
| B | September 1950 |
| C | April 1947 |
| D | Januar 1949 |

**Correcte :** [A]

**Saviez-vous (189 chars) :** Die Blockade führte zur Berliner Luftbrücke, bei der alliierte Flugzeuge Tonnen von Gütern in die abgeschnittene Stadt lieferten und damit ein Engagement für die Bevölkerung demonstrierten.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quando iniziò il 'Blocco di Berlino'?

| Clé | Réponse |
|---|---|
| A | Giugno 1948 ✅ |
| B | Settembre 1950 |
| C | Aprile 1947 |
| D | Gennaio 1949 |

**Correcte :** [A]

**Saviez-vous (166 chars) :** Il blocco portò al ponte aereo di Berlino, dove aerei alleati consegnarono tonnellate di rifornimenti alla città isolata, dimostrando un impegno verso la popolazione.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Quando começou o 'Bloqueio de Berlim'?

| Clé | Réponse |
|---|---|
| A | Junho de 1948 ✅ |
| B | Setembro de 1950 |
| C | Abril de 1947 |
| D | Janeiro de 1949 |

**Correcte :** [A]

**Saviez-vous (160 chars) :** O bloqueio levou à ponte aérea de Berlim, onde aviões aliados entregaram toneladas de suprimentos à cidade isolada, demonstrando um compromisso com a população.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Когда началась «Берлинская блокада»?

| Clé | Réponse |
|---|---|
| A | Июнь 1948 г. ✅ |
| B | Сентябрь 1950 г. |
| C | Апрель 1947 г. |
| D | Январь 1949 г. |

**Correcte :** [A]

**Saviez-vous (156 chars) :** Блокада привела к Берлинскому воздушному мосту, когда самолеты союзников доставили тонны припасов в отрезанный город, демонстрируя приверженность населению.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** “柏林封锁”是什么时候开始的？

| Clé | Réponse |
|---|---|
| A | 1948年6月 ✅ |
| B | 1950年9月 |
| C | 1947年4月 |
| D | 1949年1月 |

**Correcte :** [A]

**Saviez-vous (40 chars) :** 封锁导致了柏林空运，盟军飞机向被切断的城市运送了大量物资，这表明了对人民的承诺。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** متى بدأ "حصار برلين"؟

| Clé | Réponse |
|---|---|
| A | يونيو 1948 ✅ |
| B | سبتمبر 1950 |
| C | أبريل 1947 |
| D | يناير 1949 |

**Correcte :** [A]

**Saviez-vous (140 chars) :** أدى الحصار إلى الجسر الجوي لبرلين، حيث قامت طائرات الحلفاء بتسليم أطنان من الإمدادات إلى المدينة المقطوعة، مما يدل على الالتزام تجاه السكان.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Πότε ξεκίνησε ο «Αποκλεισμός του Βερολίνου»;

| Clé | Réponse |
|---|---|
| A | Ιούνιος 1948 ✅ |
| B | Σεπτέμβριος 1950 |
| C | Απρίλιος 1947 |
| D | Ιανουάριος 1949 |

**Correcte :** [A]

**Saviez-vous (166 chars) :** Ο αποκλεισμός οδήγησε στην αερογέφυρα του Βερολίνου, όπου συμμαχικά αεροπλάνα παρέδωσαν τόνους προμηθειών στην αποκομμένη πόλη, επιδεικνύοντας δέσμευση στον πληθυσμό.

</details>

---

#### Variante : `true_false/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2521 |
| readable_code | HI-D04-T-S-9EFD3 |
| question_type | true_false |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | 20th-century-history |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** La 'Déclaration Balfour' de 1917 promettait un État palestinien indépendant.

| Clé | Réponse |
|---|---|
| A | Vrai |
| B | Faux ✅ |

**Correcte :** [B]

**Saviez-vous (154 chars) :** La Déclaration Balfour était adressée à Lord Rothschild, un dirigeant de la communauté juive britannique, et fut un facteur clé dans la création d'Israël.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** The 'Balfour Declaration' of 1917 promised an independent Palestinian state.

| Clé | Réponse |
|---|---|
| A | True |
| B | False ✅ |

**Correcte :** [B]

**Saviez-vous (147 chars) :** The Balfour Declaration was addressed to Lord Rothschild, a leader of the British Jewish community, and was a key factor in the creation of Israel.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** La 'Declaración Balfour' de 1917 prometía un Estado palestino independiente.

| Clé | Réponse |
|---|---|
| A | Verdadero |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (144 chars) :** La Declaración Balfour fue dirigida a Lord Rothschild, un líder de la comunidad judía británica, y fue un factor clave en la creación de Israel.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Die 'Balfour-Erklärung' von 1917 versprach einen unabhängigen palästinensischen Staat.

| Clé | Réponse |
|---|---|
| A | Wahr |
| B | Falsch ✅ |

**Correcte :** [B]

**Saviez-vous (159 chars) :** Die Balfour-Erklärung wurde an Lord Rothschild, einen Führer der britischen jüdischen Gemeinde, gerichtet und war ein Schlüsselfaktor bei der Gründung Israels.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** La 'Dichiarazione Balfour' del 1917 prometteva uno Stato palestinese indipendente.

| Clé | Réponse |
|---|---|
| A | Vero |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (154 chars) :** La Dichiarazione Balfour fu indirizzata a Lord Rothschild, un leader della comunità ebraica britannica, e fu un fattore chiave nella creazione di Israele.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** A 'Declaração Balfour' de 1917 prometia um Estado palestino independente.

| Clé | Réponse |
|---|---|
| A | Verdadeiro |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (137 chars) :** A Declaração Balfour foi dirigida a Lord Rothschild, um líder da comunidade judaica britânica, e foi um fator chave na criação de Israel.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** «Декларация Бальфура» 1917 года обещала независимое палестинское государство.

| Clé | Réponse |
|---|---|
| A | Правда |
| B | Ложь ✅ |

**Correcte :** [B]

**Saviez-vous (133 chars) :** Декларация Бальфура была адресована лорду Ротшильду, лидеру британской еврейской общины, и была ключевым фактором в создании Израиля.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 1917年的“贝尔福宣言”承诺建立独立的巴勒斯坦国。

| Clé | Réponse |
|---|---|
| A | 真 |
| B | 假 ✅ |

**Correcte :** [B]

**Saviez-vous (38 chars) :** 《贝尔福宣言》是致英国犹太社区领袖罗斯柴尔德勋爵的，是创建以色列的关键因素。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** وعد "إعلان بلفور" عام 1917 بدولة فلسطينية مستقلة.

| Clé | Réponse |
|---|---|
| A | صحيح |
| B | خاطئ ✅ |

**Correcte :** [B]

**Saviez-vous (116 chars) :** تم توجيه إعلان بلفور إلى اللورد روتشيلد، وهو زعيم الجالية اليهودية البريطانية، وكان عاملاً رئيسيًا في إنشاء إسرائيل.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Η «Διακήρυξη Μπάλφουρ» του 1917 υποσχέθηκε ένα ανεξάρτητο παλαιστινιακό κράτος.

| Clé | Réponse |
|---|---|
| A | Αληθής |
| B | Ψευδής ✅ |

**Correcte :** [B]

**Saviez-vous (153 chars) :** Η Διακήρυξη Μπάλφουρ απευθυνόταν στον Λόρδο Ρότσιλντ, ηγέτη της βρετανικής εβραϊκής κοινότητας, και ήταν βασικός παράγοντας για τη δημιουργία του Ισραήλ.

</details>

---

#### Variante : `true_false/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 2543 |
| readable_code | HI-D04-T-R-9390D |
| question_type | true_false |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | american-revolution-history |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Le 'Boston Tea Party', une protestation contre la taxe sur le thé, a eu lieu en 1773.

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (180 chars) :** Les participants au Boston Tea Party se sont déguisés en Amérindiens pour protester contre la loi sur le thé et la domination britannique. Ils ont jeté 342 caisses de thé à la mer.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** The 'Boston Tea Party', a protest against the tea tax, took place in 1773.

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (161 chars) :** Participants in the Boston Tea Party disguised themselves as Native Americans to protest the Tea Act and British rule. They threw 342 chests of tea into the sea.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** El 'Boston Tea Party', una protesta contra el impuesto sobre el té, tuvo lugar en 1773.

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (171 chars) :** Los participantes en el Boston Tea Party se disfrazaron de nativos americanos para protestar contra la Ley del Té y el dominio británico. Arrojaron 342 cajas de té al mar.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Die 'Boston Tea Party', ein Protest gegen die Teesteuer, fand 1773 statt.

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (188 chars) :** Die Teilnehmer der Boston Tea Party verkleideten sich als amerikanische Ureinwohner, um gegen das Teegesetz und die britische Herrschaft zu protestieren. Sie warfen 342 Teekisten ins Meer.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Il 'Boston Tea Party', una protesta contro la tassa sul tè, ebbe luogo nel 1773.

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (167 chars) :** I partecipanti al Boston Tea Party si travestirono da nativi americani per protestare contro la legge sul tè e il dominio britannico. Getarono in mare 342 casse di tè.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** O 'Boston Tea Party', um protesto contra o imposto sobre o chá, ocorreu em 1773.

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (170 chars) :** Os participantes do Boston Tea Party se disfarçaram de nativos americanos para protestar contra a Lei do Chá e o domínio britânico. Eles jogaram 342 caixas de chá no mar.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** «Бостонское чаепитие», протест против чайного налога, произошло в 1773 году.

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (170 chars) :** Участники «Бостонского чаепития» переоделись коренными американцами, чтобы протестовать против Чайного закона и британского правления. Они выбросили в море 342 ящика чая.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** “波士顿倾茶事件”是反对茶叶税的抗议活动，发生在1773年。

| Clé | Réponse |
|---|---|
| A | 正确 ✅ |
| B | 错误 |

**Correcte :** [A]

**Saviez-vous (47 chars) :** 波士顿倾茶事件的参与者装扮成美洲原住民，以抗议茶叶法和英国的统治。他们将342箱茶叶倒入海中。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** وقعت "حفلة شاي بوسطن"، وهي احتجاج على ضريبة الشاي، في عام 1773.

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (132 chars) :** تنكر المشاركون في حفلة شاي بوسطن في زي الأمريكيين الأصليين للاحتجاج على قانون الشاي والحكم البريطاني. وألقوا 342 صندوق شاي في البحر.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Το 'Boston Tea Party', μια διαμαρτυρία κατά του φόρου τσαγιού, έλαβε χώρα το 1773.

| Clé | Réponse |
|---|---|
| A | Αληθές ✅ |
| B | Ψευδές |

**Correcte :** [A]

**Saviez-vous (193 chars) :** Οι συμμετέχοντες στο Boston Tea Party μεταμφιέστηκαν σε ιθαγενείς Αμερικανούς για να διαμαρτυρηθούν για τον νόμο περί τσαγιού και τη βρετανική κυριαρχία. Έριξαν 342 κιβώτια τσαγιού στη θάλασσα.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **qcm/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible
- **qcm/deceptive_trap** : ✅ OK
- **true_false/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible
- **true_false/recognition** : ✅ OK

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ✅ OK
- **qcm/reasoning** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK
- **true_false/reasoning** : ✅ OK
- **true_false/recognition** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ✅ OK (John Hancock a été le premier à signer la Déclaration d'indépendance, et sa signature est la plus grande et la plus reconnaissable.)
- **qcm/reasoning** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Après la conquête, Mehmet II a déplacé sa capitale à Constantinople et a transformé la basilique Sainte-Sophie en mosquée, symbolisant le changement de pouvoir.
- **qcm/deceptive_trap** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Le blocus a conduit au pont aérien de Berlin, où des avions alliés ont livré des tonnes de fournitures à la ville coupée, démontrant un engagement envers la population.
- **true_false/reasoning** : ⚠️ ⚠️ SV sans marqueur de surprise visible → La Déclaration Balfour était adressée à Lord Rothschild, un dirigeant de la communauté juive britannique, et fut un facteur clé dans la création d'Israël.
- **true_false/recognition** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Les participants au Boston Tea Party se sont déguisés en Amérindiens pour protester contre la loi sur le thé et la domination britannique. Ils ont jeté 342 caisses de thé à la mer.

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
✅ Aucun problème résiduel détecté

#### Dérive sémantique vs noyau
- **qcm/recognition** : ✅ 1/6 mots-clés noyau présents
- **qcm/reasoning** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **qcm/deceptive_trap** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/reasoning** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)

---

## NOYAU 2 — #7 · Sport · depth 4

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 7 |
| intent_key | legacy_grand-slam-mens-singles-titles |
| semantic_key | sport-tennis-grand-slam-records |
| domain | Sport |
| sub_domain | Sport |
| difficulty_depth | 4 |
| subject | Records du Grand Chelem (tennis) |
| angle_large | Records et statistiques sportives |
| micro_angle | Titres en simple masculin |
| answer_target | Nombre de titres Grand Chelem |
| potential_trap | Confusion Federer/Djokovic/Nadal selon l'année |
| concept_family | tennis-grand-slam-records |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 4/5 |
| Variantes manquantes | qcm/reasoning |
| Toutes langues complètes | Oui |
| Quality flags actifs | tautological_sv [#11], sv_too_long_ar [#11:251>140], q_too_long_ar [#11:100>75], sv_too_long_de [#11:241>220], sv_too_long_el [#11:247>220], sv_too_long_en [#11:231>220], sv_too_long_es [#11:243>220], sv_too_long_fr [#11:239>220], sv_too_long_it [#11:242>220], sv_too_long_pt [#11:242>220], sv_too_long_ru [#11:278>220], sv_too_long_zh [#11:111>100] |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 11 |
| readable_code | SP-D04-Q-R-B92B3 |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | tennis-grand-slam-records |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel joueur de tennis détient le record du plus grand nombre de titres en Grand Chelem en simple messieurs?

| Clé | Réponse |
|---|---|
| A | Novak Djokovic ✅ |
| B | Roger Federer |
| C | Rafael Nadal |
| D | Pete Sampras |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (239>220) :** Novak Djokovic a remporté son 24ème titre du Grand Chelem à l'US Open 2023, battant Daniil Medvedev en finale. Il est le seul joueur à avoir remporté au moins 7 fois trois des quatre tournois majeurs (Open d'Australie, Wimbledon, US Open).

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Which tennis player holds the record for the most Grand Slam men's singles titles?

| Clé | Réponse |
|---|---|
| A | Novak Djokovic ✅ |
| B | Roger Federer |
| C | Rafael Nadal |
| D | Pete Sampras |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (231>220) :** Novak Djokovic won his 24th Grand Slam title at the US Open 2023, defeating Daniil Medvedev in the final. He is the only player to have won at least 7 times three of the four major tournaments (Australian Open, Wimbledon, US Open).

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Qué tenista ostenta el récord de más títulos de Grand Slam en individuales masculinos?

| Clé | Réponse |
|---|---|
| A | Novak Djokovic ✅ |
| B | Roger Federer |
| C | Rafael Nadal |
| D | Pete Sampras |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (243>220) :** Novak Djokovic ganó su 24º título de Grand Slam en el US Open 2023, derrotando a Daniil Medvedev en la final. Es el único jugador que ha ganado al menos 7 veces tres de los cuatro torneos principales (Abierto de Australia, Wimbledon, US Open).

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welcher Tennisspieler hält den Rekord für die meisten Grand-Slam-Titel im Einzel der Herren?

| Clé | Réponse |
|---|---|
| A | Novak Djokovic ✅ |
| B | Roger Federer |
| C | Rafael Nadal |
| D | Pete Sampras |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (241>220) :** Novak Djokovic gewann seinen 24. Grand-Slam-Titel bei den US Open 2023 und besiegte Daniil Medvedev im Finale. Er ist der einzige Spieler, der mindestens 7 Mal drei der vier großen Turniere (Australian Open, Wimbledon, US Open) gewonnen hat.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quale tennista detiene il record per il maggior numero di titoli del Grande Slam nel singolare maschile?

| Clé | Réponse |
|---|---|
| A | Novak Djokovic ✅ |
| B | Roger Federer |
| C | Rafael Nadal |
| D | Pete Sampras |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (242>220) :** Novak Djokovic ha vinto il suo 24esimo titolo del Grande Slam agli US Open 2023, sconfiggendo Daniil Medvedev in finale. È l'unico giocatore ad aver vinto almeno 7 volte tre dei quattro tornei principali (Australian Open, Wimbledon, US Open).

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual tenista detém o recorde de mais títulos de Grand Slam em simples masculinos?

| Clé | Réponse |
|---|---|
| A | Novak Djokovic ✅ |
| B | Roger Federer |
| C | Rafael Nadal |
| D | Pete Sampras |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (242>220) :** Novak Djokovic conquistou seu 24º título de Grand Slam no US Open 2023, derrotando Daniil Medvedev na final. Ele é o único jogador a ter vencido pelo menos 7 vezes três dos quatro principais torneios (Aberto da Austrália, Wimbledon, US Open).

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какой теннисист является рекордсменом по количеству титулов Большого шлема в мужском одиночном разряде?

| Clé | Réponse |
|---|---|
| A | Новак Джокович ✅ |
| B | Роджер Федерер |
| C | Рафаэль Надаль |
| D | Пит Сампрас |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (278>220) :** Новак Джокович выиграл свой 24-й титул Большого шлема на Открытом чемпионате США 2023 года, победив Даниила Медведева в финале. Он единственный игрок, выигравший как минимум 7 раз три из четырех основных турниров (Открытый чемпионат Австралии, Уимблдон, Открытый чемпионат США).

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 哪位网球运动员保持着男子单打大满贯冠军头衔最多的记录？

| Clé | Réponse |
|---|---|
| A | 诺瓦克·德约科维奇 ✅ |
| B | 罗杰·费德勒 |
| C | 拉斐尔·纳达尔 |
| D | 皮特·桑普拉斯 |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (111>100) :** 诺瓦克·德约科维奇在2023年美国网球公开赛上赢得了他的第24个大满贯冠军，在决赛中击败了丹尼尔·梅德韦杰夫。他是唯一一位至少7次赢得四个主要赛事中三个赛事的球员（澳大利亚网球公开赛、温布尔登网球公开赛、美国网球公开赛）。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** من هو لاعب التنس الذي يحمل الرقم القياسي لأكبر عدد من ألقاب الفردي للرجال في البطولات الأربع الكبرى؟

| Clé | Réponse |
|---|---|
| A | نوفاك ديوكوفيتش ✅ |
| B | روجر فيدرر |
| C | رافائيل نادال |
| D | بيت سامبراس |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (251>140) :** فاز نوفاك ديوكوفيتش بلقبه الـ 24 في البطولات الأربع الكبرى في بطولة أمريكا المفتوحة 2023، بفوزه على دانييل ميدفيديف في النهائي. إنه اللاعب الوحيد الذي فاز 7 مرات على الأقل بثلاث من البطولات الأربع الكبرى (أستراليا المفتوحة، ويمبلدون، أمريكا المفتوحة).

> ⚠️ question_text trop longue : 100 > max=75

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιος τενίστας κατέχει το ρεκόρ για τους περισσότερους τίτλους Grand Slam στο απλό ανδρών;

| Clé | Réponse |
|---|---|
| A | Νόβακ Τζόκοβιτς ✅ |
| B | Ρότζερ Φέντερερ |
| C | Ραφαέλ Ναδάλ |
| D | Πιτ Σάμπρας |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (247>220) :** Ο Νόβακ Τζόκοβιτς κέρδισε τον 24ο τίτλο Grand Slam στο US Open 2023, νικώντας τον Ντανίλ Μεντβέντεφ στον τελικό. Είναι ο μόνος παίκτης που έχει κερδίσει τουλάχιστον 7 φορές τρία από τα τέσσερα μεγάλα τουρνουά (Australian Open, Wimbledon, US Open).

</details>

---

#### Variante : `qcm/deceptive_trap`

| Champ | Valeur |
|---|---|
| question_group_id | 2523 |
| readable_code | SP-D04-Q-D-5B95D |
| question_type | qcm |
| cognitive_type | deceptive_trap |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | track-and-field-distances |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quelle est la distance d'un marathon standard?

| Clé | Réponse |
|---|---|
| A | 42,195 kilomètres ✅ |
| B | 40 kilomètres |
| C | 50 kilomètres |
| D | 38 kilomètres |

**Correcte :** [A]

**Saviez-vous (162 chars) :** La distance du marathon a été fixée en 1908 aux JO de Londres pour que le départ soit au château de Windsor et l'arrivée devant la loge royale du stade olympique!

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What is the distance of a standard marathon?

| Clé | Réponse |
|---|---|
| A | 42.195 kilometers ✅ |
| B | 40 kilometers |
| C | 50 kilometers |
| D | 38 kilometers |

**Correcte :** [A]

**Saviez-vous (170 chars) :** The marathon distance was set in 1908 at the London Olympics so that the start would be at Windsor Castle and the finish in front of the royal box at the Olympic Stadium!

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál es la distancia de un maratón estándar?

| Clé | Réponse |
|---|---|
| A | 42,195 kilómetros ✅ |
| B | 40 kilómetros |
| C | 50 kilómetros |
| D | 38 kilómetros |

**Correcte :** [A]

**Saviez-vous (182 chars) :** La distancia del maratón se fijó en 1908 en los Juegos Olímpicos de Londres para que la salida fuera en el castillo de Windsor y la llegada frente al palco real del estadio olímpico.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Was ist die Distanz eines Standard-Marathons?

| Clé | Réponse |
|---|---|
| A | 42,195 Kilometer ✅ |
| B | 40 Kilometer |
| C | 50 Kilometer |
| D | 38 Kilometer |

**Correcte :** [A]

**Saviez-vous (180 chars) :** Die Marathonstrecke wurde 1908 bei den Olympischen Spielen in London festgelegt, damit der Start am Windsor Castle und das Ziel vor der königlichen Loge des Olympiastadions liegen!

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è la distanza di una maratona standard?

| Clé | Réponse |
|---|---|
| A | 42,195 chilometri ✅ |
| B | 40 chilometri |
| C | 50 chilometri |
| D | 38 chilometri |

**Correcte :** [A]

**Saviez-vous (180 chars) :** La distanza della maratona fu fissata nel 1908 alle Olimpiadi di Londra affinché la partenza fosse al castello di Windsor e l'arrivo di fronte al palco reale dello stadio olimpico!

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual é a distância de uma maratona padrão?

| Clé | Réponse |
|---|---|
| A | 42,195 quilômetros ✅ |
| B | 40 quilômetros |
| C | 50 quilômetros |
| D | 38 quilômetros |

**Correcte :** [A]

**Saviez-vous (182 chars) :** A distância da maratona foi definida em 1908 nos Jogos Olímpicos de Londres para que a partida fosse no Castelo de Windsor e a chegada em frente ao camarote real no estádio olímpico!

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какова дистанция стандартного марафона?

| Clé | Réponse |
|---|---|
| A | 42,195 километра ✅ |
| B | 40 километров |
| C | 50 километров |
| D | 38 километров |

**Correcte :** [A]

**Saviez-vous (173 chars) :** Дистанция марафона была установлена в 1908 году на Олимпийских играх в Лондоне, чтобы старт был в Виндзорском замке, а финиш перед королевской ложей на Олимпийском стадионе!

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 标准马拉松的距离是多少？

| Clé | Réponse |
|---|---|
| A | 42.195公里 ✅ |
| B | 40公里 |
| C | 50公里 |
| D | 38公里 |

**Correcte :** [A]

**Saviez-vous (49 chars) :** 马拉松的距离在1908年伦敦奥运会上被确定下来，起点在温莎城堡，终点在奥林匹克体育场的皇家包厢前！

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هي مسافة الماراثون القياسي؟

| Clé | Réponse |
|---|---|
| A | 42.195 كيلومتر ✅ |
| B | 40 كيلومتر |
| C | 50 كيلومتر |
| D | 38 كيلومتر |

**Correcte :** [A]

**Saviez-vous (138 chars) :** تم تحديد مسافة الماراثون في عام 1908 في أولمبياد لندن بحيث تكون البداية في قلعة وندسور والنهاية أمام المقصورة الملكية في الاستاد الأولمبي!

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποια είναι η απόσταση ενός τυπικού μαραθωνίου;

| Clé | Réponse |
|---|---|
| A | 42,195 χιλιόμετρα ✅ |
| B | 40 χιλιόμετρα |
| C | 50 χιλιόμετρα |
| D | 38 χιλιόμετρα |

**Correcte :** [A]

**Saviez-vous (209 chars) :** Η απόσταση του μαραθωνίου καθορίστηκε το 1908 στους Ολυμπιακούς Αγώνες του Λονδίνου, έτσι ώστε η εκκίνηση να είναι στο Κάστρο του Windsor και ο τερματισμός μπροστά από το βασιλικό θεωρείο στο Ολυμπιακό Στάδιο!

</details>

---

#### Variante : `true_false/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 2524 |
| readable_code | SP-D04-T-R-78094 |
| question_type | true_false |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | volleyball-rules |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Le filet de volley-ball est plus haut pour les hommes que pour les femmes.

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (152 chars) :** La hauteur du filet de volleyball a été modifiée plusieurs fois au fil des ans, évoluant avec les styles de jeu et les capacités physiques des athlètes.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** The volleyball net is higher for men than for women.

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (153 chars) :** The height of the volleyball net has been modified several times over the years, evolving with the playing styles and physical abilities of the athletes.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** La red de voleibol es más alta para los hombres que para las mujeres.

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (166 chars) :** La altura de la red de voleibol se ha modificado varias veces a lo largo de los años, evolucionando con los estilos de juego y las capacidades físicas de los atletas.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Das Volleyballnetz ist für Männer höher als für Frauen.

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (169 chars) :** Die Höhe des Volleyballnetzes wurde im Laufe der Jahre mehrmals geändert und hat sich mit den Spielstilen und den körperlichen Fähigkeiten der Athleten weiterentwickelt.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** La rete da pallavolo è più alta per gli uomini che per le donne.

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (155 chars) :** L'altezza della rete da pallavolo è stata modificata più volte nel corso degli anni, evolvendosi con gli stili di gioco e le capacità fisiche degli atleti.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** A rede de voleibol é mais alta para homens do que para mulheres.

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (146 chars) :** A altura da rede de voleibol foi modificada várias vezes ao longo dos anos, evoluindo com os estilos de jogo e as capacidades físicas dos atletas.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Волейбольная сетка выше для мужчин, чем для женщин.

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (149 chars) :** Высота волейбольной сетки несколько раз менялась на протяжении многих лет, развиваясь вместе со стилями игры и физическими возможностями спортсменов.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 男子排球网比女子排球网高。

| Clé | Réponse |
|---|---|
| A | 真 ✅ |
| B | 假 |

**Correcte :** [A]

**Saviez-vous (38 chars) :** 多年来，排球网的高度已经过多次修改，并随着运动员的比赛风格和身体能力而发展。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** شبكة الكرة الطائرة أعلى للرجال منها للنساء.

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (108 chars) :** تم تعديل ارتفاع شبكة الكرة الطائرة عدة مرات على مر السنين، وتطورت مع أنماط اللعب والقدرات البدنية للرياضيين.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Το φιλέ του βόλεϊ είναι ψηλότερο για τους άνδρες από ό,τι για τις γυναίκες.

| Clé | Réponse |
|---|---|
| A | Αλήθεια ✅ |
| B | Ψευδής |

**Correcte :** [A]

**Saviez-vous (149 chars) :** Το ύψος του φιλέ του βόλεϊ έχει τροποποιηθεί αρκετές φορές με τα χρόνια, εξελισσόμενο με τα στυλ παιχνιδιού και τις σωματικές ικανότητες των αθλητών.

</details>

---

#### Variante : `true_false/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2525 |
| readable_code | SP-D04-T-S-358D7 |
| question_type | true_false |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | tennis-rules-and-techniques |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Un joueur de tennis peut servir par le haut ou par le bas.

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (167 chars) :** Le service à la cuillère, un type de service par le bas, a été utilisé avec succès par des joueurs de haut niveau comme Nick Kyrgios pour surprendre leurs adversaires.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** A tennis player can serve overhand or underhand.

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (136 chars) :** The underhand serve, a type of underhand serve, has been used successfully by top players like Nick Kyrgios to surprise their opponents.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** Un jugador de tenis puede sacar por arriba o por abajo.

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (155 chars) :** El saque de cuchara, un tipo de saque por abajo, ha sido utilizado con éxito por jugadores de alto nivel como Nick Kyrgios para sorprender a sus oponentes.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Ein Tennisspieler kann von oben oder von unten aufschlagen.

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (154 chars) :** Der Unterschnittaufschlag, eine Art Aufschlag von unten, wurde von Spitzenspielern wie Nick Kyrgios erfolgreich eingesetzt, um ihre Gegner zu überraschen.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Un giocatore di tennis può servire da sopra o da sotto.

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (167 chars) :** Il servizio a cucchiaio, un tipo di servizio da sotto, è stato utilizzato con successo da giocatori di alto livello come Nick Kyrgios per sorprendere i loro avversari.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Um jogador de tênis pode sacar por cima ou por baixo.

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (152 chars) :** O saque por baixo, um tipo de saque por baixo, tem sido usado com sucesso por jogadores de alto nível como Nick Kyrgios para surpreender seus oponentes.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Теннисист может подавать сверху или снизу.

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (146 chars) :** Подача с подкруткой, разновидность подачи снизу, успешно использовалась ведущими игроками, такими как Ник Кирьос, чтобы удивить своих противников.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 网球运动员可以上手发球或下手发球。

| Clé | Réponse |
|---|---|
| A | 正确 ✅ |
| B | 错误 |

**Correcte :** [A]

**Saviez-vous (45 chars) :** 下旋发球是一种下手发球，曾被像尼克·克耶高斯这样的顶级球员成功使用，以出其不意地击败对手。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** يمكن للاعب التنس الإرسال من الأعلى أو من الأسفل.

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (118 chars) :** لقد تم استخدام الإرسال السفلي، وهو نوع من الإرسال من الأسفل، بنجاح من قبل كبار اللاعبين مثل نيك كيريوس لمفاجأة خصومهم.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ένας παίκτης του τένις μπορεί να σερβίρει από πάνω ή από κάτω.

| Clé | Réponse |
|---|---|
| A | Αλήθεια ✅ |
| B | Ψευδής |

**Correcte :** [A]

**Saviez-vous (166 chars) :** Το σερβίς με το κουτάλι, ένας τύπος σερβίς από κάτω, έχει χρησιμοποιηθεί με επιτυχία από κορυφαίους παίκτες όπως ο Nick Kyrgios για να εκπλήξουν τους αντιπάλους τους.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK
- **true_false/recognition** : ✅ OK
- **true_false/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ⚠️ Longueurs dépassées : Q-ar=100>75, SV-ar=251>140, SV-de=241>220, SV-el=247>220, SV-en=231>220, SV-es=243>220, SV-fr=239>220, SV-it=242>220, SV-pt=242>220, SV-ru=278>220, SV-zh=111>100
- **qcm/deceptive_trap** : ✅ OK
- **true_false/recognition** : ✅ OK
- **true_false/reasoning** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ⚠️ ⚠️ tautologique (contient la réponse correcte "novak djokovic") → Novak Djokovic a remporté son 24ème titre du Grand Chelem à l'US Open 2023, battant Daniil Medvedev en finale. Il est le seul joueur à avoir remporté au moins 7 fois trois des quatre tournois majeurs (Open d'Australie, Wimbledon, US Open).
- **qcm/deceptive_trap** : ⚠️ ⚠️ SV sans marqueur de surprise visible → La distance du marathon a été fixée en 1908 aux JO de Londres pour que le départ soit au château de Windsor et l'arrivée devant la loge royale du stade olympique!
- **true_false/recognition** : ⚠️ ⚠️ SV sans marqueur de surprise visible → La hauteur du filet de volleyball a été modifiée plusieurs fois au fil des ans, évoluant avec les styles de jeu et les capacités physiques des athlètes.
- **true_false/reasoning** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Le service à la cuillère, un type de service par le bas, a été utilisé avec succès par des joueurs de haut niveau comme Nick Kyrgios pour surprendre leurs adversaires.

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 4/5 variantes présentes — variantes manquantes bloquées par guards
- ⚠️ saviez_vous ar trop long [11] (P3 non appliqué)
- ⚠️ question ar trop longue [11] (P3 non appliqué)
- ⚠️ saviez_vous de trop long [11] (P3 non appliqué)
- ⚠️ saviez_vous el trop long [11] (P3 non appliqué)
- ⚠️ saviez_vous en trop long [11] (P3 non appliqué)
- ⚠️ saviez_vous es trop long [11] (P3 non appliqué)
- ⚠️ saviez_vous fr trop long [11] (P3 non appliqué)
- ⚠️ saviez_vous it trop long [11] (P3 non appliqué)
- ⚠️ saviez_vous pt trop long [11] (P3 non appliqué)
- ⚠️ saviez_vous ru trop long [11] (P3 non appliqué)
- ⚠️ saviez_vous zh trop long [11] (P3 non appliqué)

#### Dérive sémantique vs noyau
- **qcm/recognition** : ✅ 4/8 mots-clés noyau présents
- **qcm/deceptive_trap** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/reasoning** : ✅ 1/8 mots-clés noyau présents

---

## NOYAU 3 — #34 · Géographie · depth 5

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 34 |
| intent_key | legacy_geographie-e872eaedc555 |
| semantic_key | geographie-african-geography |
| domain | Géographie |
| sub_domain | Géographie |
| difficulty_depth | 5 |
| subject | Géographie africaine |
| angle_large | Géographie continentale |
| micro_angle | Pays et capitales africaines |
| answer_target | Nom de pays ou capitale africaine |
| potential_trap | Pays aux capitales non-intuitives |
| concept_family | african-geography |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 4/5 |
| Variantes manquantes | qcm/reasoning |
| Toutes langues complètes | Oui |
| Quality flags actifs | tautological_sv [#38], tautological_sv [#2544] |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 38 |
| readable_code | GE-D05-Q-R-FA961 |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | african-geography |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel est le plus long fleuve d'Afrique ?

| Clé | Réponse |
|---|---|
| A | Le Nil ✅ |
| B | Le Congo |
| C | Le Niger |
| D | Le Zambèze |

**Correcte :** [A]

**Saviez-vous (119 chars) :** Le Nil a deux affluents principaux : le Nil Blanc et le Nil Bleu. Le Nil Bleu fournit la majorité de l'eau et du limon.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What is the longest river in Africa?

| Clé | Réponse |
|---|---|
| A | The Nile ✅ |
| B | The Congo |
| C | The Niger |
| D | The Zambezi |

**Correcte :** [A]

**Saviez-vous (128 chars) :** The Nile has two major tributaries: the White Nile and the Blue Nile. The Blue Nile provides the majority of the water and silt.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál es el río más largo de África?

| Clé | Réponse |
|---|---|
| A | El Nilo ✅ |
| B | El Congo |
| C | El Níger |
| D | El Zambeze |

**Correcte :** [A]

**Saviez-vous (127 chars) :** El Nilo tiene dos afluentes principales: el Nilo Blanco y el Nilo Azul. El Nilo Azul proporciona la mayoría del agua y el limo.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welcher Fluss ist der längste in Afrika?

| Clé | Réponse |
|---|---|
| A | Der Nil ✅ |
| B | Der Kongo |
| C | Der Niger |
| D | Der Sambesi |

**Correcte :** [A]

**Saviez-vous (131 chars) :** Der Nil hat zwei Hauptzuflüsse: den Weißen Nil und den Blauen Nil. Der Blaue Nil liefert den Großteil des Wassers und des Schlamms.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è il fiume più lungo dell'Africa?

| Clé | Réponse |
|---|---|
| A | Il Nilo ✅ |
| B | Il Congo |
| C | Il Niger |
| D | Lo Zambesi |

**Correcte :** [A]

**Saviez-vous (135 chars) :** Il Nilo ha due affluenti principali: il Nilo Bianco e il Nilo Azzurro. Il Nilo Azzurro fornisce la maggior parte dell'acqua e del limo.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual é o rio mais longo da África?

| Clé | Réponse |
|---|---|
| A | O Nilo ✅ |
| B | O Congo |
| C | O Níger |
| D | O Zambeze |

**Correcte :** [A]

**Saviez-vous (119 chars) :** O Nilo tem dois afluentes principais: o Nilo Branco e o Nilo Azul. O Nilo Azul fornece a maior parte da água e do lodo.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какая река является самой длинной в Африке?

| Clé | Réponse |
|---|---|
| A | Нил ✅ |
| B | Конго |
| C | Нигер |
| D | Замбези |

**Correcte :** [A]

**Saviez-vous (109 chars) :** У Нила есть два основных притока: Белый Нил и Голубой Нил. Голубой Нил обеспечивает большую часть воды и ила.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 非洲最长的河流是什么？

| Clé | Réponse |
|---|---|
| A | 尼罗河 ✅ |
| B | 刚果河 |
| C | 尼日尔河 |
| D | 赞比西河 |

**Correcte :** [A]

**Saviez-vous (37 chars) :** 尼罗河有两条主要支流：白尼罗河和青尼罗河。青尼罗河提供了大部分的水和淤泥。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هو أطول نهر في أفريقيا؟

| Clé | Réponse |
|---|---|
| A | نهر النيل ✅ |
| B | نهر الكونغو |
| C | نهر النيجر |
| D | نهر الزامبيزي |

**Correcte :** [A]

**Saviez-vous (94 chars) :** لنهر النيل رافدان رئيسيان: النيل الأبيض والنيل الأزرق. يوفر النيل الأزرق غالبية المياه والطمي.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιος είναι ο μακρύτερος ποταμός στην Αφρική;

| Clé | Réponse |
|---|---|
| A | Ο Νείλος ✅ |
| B | Ο Κονγκό |
| C | Ο Νίγηρας |
| D | Ο Ζαμβέζης |

**Correcte :** [A]

**Saviez-vous (154 chars) :** Ο Νείλος έχει δύο σημαντικούς παραποτάμους: τον Λευκό Νείλο και τον Γαλάζιο Νείλο. Ο Γαλάζιος Νείλος παρέχει το μεγαλύτερο μέρος του νερού και της λάσπης.

</details>

---

#### Variante : `true_false/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2527 |
| readable_code | GE-D05-T-S-04730 |
| question_type | true_false |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | world-coastal-geography |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** La France métropolitaine possède plus de côtes que le Brésil.

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (161 chars) :** La longueur des côtes françaises est difficile à déterminer précisément, car elle dépend de la méthode de mesure (niveau de détail des indentations considérées).

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Metropolitan France has more coastline than Brazil.

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (164 chars) :** The length of the French coastline is difficult to determine precisely, as it depends on the method of measurement (level of detail of the indentations considered).

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** Francia metropolitana tiene más costa que Brasil.

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (164 chars) :** La longitud de la costa francesa es difícil de determinar con precisión, ya que depende del método de medición (nivel de detalle de las indentaciones consideradas).

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Das französische Mutterland hat mehr Küste als Brasilien.

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (151 chars) :** Die Länge der französischen Küste ist schwer genau zu bestimmen, da sie von der Messmethode abhängt (Detaillierungsgrad der betrachteten Einkerbungen).

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** La Francia metropolitana ha più costa del Brasile.

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (172 chars) :** La lunghezza della costa francese è difficile da determinare con precisione, poiché dipende dal metodo di misurazione (livello di dettaglio delle indentazioni considerate).

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** A França metropolitana tem mais costa que o Brasil.

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (152 chars) :** O comprimento da costa francesa é difícil de determinar com precisão, pois depende do método de medição (nível de detalhe das indentações consideradas).

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Материковая Франция имеет больше береговой линии, чем Бразилия.

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (146 chars) :** Длину французской береговой линии трудно определить точно, так как она зависит от метода измерения (уровня детализации рассматриваемых вдавлений).

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 法国本土的海岸线比巴西长。

| Clé | Réponse |
|---|---|
| A | 真 ✅ |
| B | 假 |

**Correcte :** [A]

**Saviez-vous (38 chars) :** 法国海岸线的长度很难精确确定，因为它取决于测量方法（考虑的压痕的详细程度）。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** تمتلك فرنسا المتروبولية سواحل أكثر من البرازيل.

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (125 chars) :** يصعب تحديد طول السواحل الفرنسية بدقة، لأنه يعتمد على طريقة القياس (مستوى تفاصيل المسافات البادئة التي يتم أخذها في الاعتبار).

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Η μητροπολιτική Γαλλία έχει περισσότερη ακτογραμμή από τη Βραζιλία.

| Clé | Réponse |
|---|---|
| A | Αληθές ✅ |
| B | Ψευδές |

**Correcte :** [A]

**Saviez-vous (173 chars) :** Το μήκος της γαλλικής ακτογραμμής είναι δύσκολο να προσδιοριστεί με ακρίβεια, καθώς εξαρτάται από τη μέθοδο μέτρησης (επίπεδο λεπτομέρειας των εσοχών που λαμβάνονται υπόψη).

</details>

---

#### Variante : `qcm/deceptive_trap`

| Champ | Valeur |
|---|---|
| question_group_id | 2544 |
| readable_code | GE-D05-Q-D-4B661 |
| question_type | qcm |
| cognitive_type | deceptive_trap |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | world-political-geography |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel pays compte le plus grand nombre de 'territoires disputés' au monde ?

| Clé | Réponse |
|---|---|
| A | La Chine |
| B | La Russie |
| C | L'Inde ✅ |
| D | Les États-Unis |

**Correcte :** [C]

**Saviez-vous (194 chars) :** Le différend frontalier entre l'Inde et la Chine a conduit à une guerre en 1962, et des escarmouches frontalières continuent de se produire sporadiquement le long de la ligne de contrôle réelle.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Which country has the largest number of 'disputed territories' in the world?

| Clé | Réponse |
|---|---|
| A | China |
| B | Russia |
| C | India ✅ |
| D | The United States |

**Correcte :** [C]

**Saviez-vous (151 chars) :** The border dispute between India and China led to a war in 1962, and border skirmishes continue to occur sporadically along the Line of Actual Control.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Qué país tiene el mayor número de 'territorios disputados' del mundo?

| Clé | Réponse |
|---|---|
| A | China |
| B | Rusia |
| C | India ✅ |
| D | Estados Unidos |

**Correcte :** [C]

**Saviez-vous (178 chars) :** La disputa fronteriza entre India y China condujo a una guerra en 1962, y las escaramuzas fronterizas continúan ocurriendo esporádicamente a lo largo de la Línea de Control Real.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welches Land hat die größte Anzahl an 'umstrittenen Gebieten' der Welt?

| Clé | Réponse |
|---|---|
| A | China |
| B | Russland |
| C | Indien ✅ |
| D | Die Vereinigten Staaten |

**Correcte :** [C]

**Saviez-vous (162 chars) :** Der Grenzstreit zwischen Indien und China führte 1962 zu einem Krieg, und entlang der Line of Actual Control kommt es weiterhin sporadisch zu Grenzzwischenfällen.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quale paese ha il maggior numero di 'territori contesi' al mondo?

| Clé | Réponse |
|---|---|
| A | Cina |
| B | Russia |
| C | India ✅ |
| D | Stati Uniti |

**Correcte :** [C]

**Saviez-vous (176 chars) :** La disputa sui confini tra India e Cina ha portato a una guerra nel 1962 e schermaglie di confine continuano a verificarsi sporadicamente lungo la Linea di controllo effettivo.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual país tem o maior número de 'territórios disputados' do mundo?

| Clé | Réponse |
|---|---|
| A | China |
| B | Rússia |
| C | Índia ✅ |
| D | Estados Unidos |

**Correcte :** [C]

**Saviez-vous (173 chars) :** A disputa de fronteira entre a Índia e a China levou a uma guerra em 1962, e escaramuças de fronteira continuam a ocorrer esporadicamente ao longo da Linha de Controle Real.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** В какой стране наибольшее количество «спорных территорий» в мире?

| Clé | Réponse |
|---|---|
| A | Китай |
| B | Россия |
| C | Индия ✅ |
| D | Соединенные Штаты |

**Correcte :** [C]

**Saviez-vous (164 chars) :** Пограничный спор между Индией и Китаем привел к войне в 1962 году, и пограничные столкновения продолжают спорадически происходить вдоль Линии фактического контроля.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 世界上哪个国家拥有最多的“争议领土”？

| Clé | Réponse |
|---|---|
| A | 中国 |
| B | 俄罗斯 |
| C | 印度 ✅ |
| D | 美国 |

**Correcte :** [C]

**Saviez-vous (45 chars) :** 印度和中国之间的边界争端导致了1962年的战争，并且沿着实际控制线仍然零星地发生边界冲突。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** أي بلد لديه أكبر عدد من "الأراضي المتنازع عليها" في العالم؟

| Clé | Réponse |
|---|---|
| A | الصين |
| B | روسيا |
| C | الهند ✅ |
| D | الولايات المتحدة |

**Correcte :** [C]

**Saviez-vous (127 chars) :** أدى النزاع الحدودي بين الهند والصين إلى حرب في عام 1962، ولا تزال المناوشات الحدودية تحدث بشكل متقطع على طول خط السيطرة الفعلي.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποια χώρα έχει τον μεγαλύτερο αριθμό «αμφισβητούμενων εδαφών» στον κόσμο;

| Clé | Réponse |
|---|---|
| A | Κίνα |
| B | Ρωσία |
| C | Ινδία ✅ |
| D | Οι Ηνωμένες Πολιτείες |

**Correcte :** [C]

**Saviez-vous (175 chars) :** Η συνοριακή διαφορά μεταξύ Ινδίας και Κίνας οδήγησε σε πόλεμο το 1962 και συνοριακές αψιμαχίες εξακολουθούν να συμβαίνουν σποραδικά κατά μήκος της Γραμμής Πραγματικού Ελέγχου.

</details>

---

#### Variante : `true_false/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 2546 |
| readable_code | GE-D05-T-R-A0D68 |
| question_type | true_false |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | asian-geography |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Le plus long fleuve d'Asie est le Yangtsé.

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (135 chars) :** Le Yangtsé abrite l'esturgeon de Chine, une espèce en danger critique d'extinction, dont l'existence remonte à l'époque des dinosaures.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** The longest river in Asia is the Yangtze.

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (133 chars) :** The Yangtze is home to the Chinese sturgeon, a critically endangered species whose existence dates back to the time of the dinosaurs.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** El río más largo de Asia es el Yangtsé.

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (139 chars) :** El Yangtsé alberga al esturión chino, una especie en peligro crítico de extinción cuya existencia se remonta a la época de los dinosaurios.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Der längste Fluss Asiens ist der Jangtse.

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (147 chars) :** Der Jangtse ist die Heimat des Chinesischen Störs, einer vom Aussterben bedrohten Art, deren Existenz bis in die Zeit der Dinosaurier zurückreicht.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Il fiume più lungo dell'Asia è lo Yangtze.

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (131 chars) :** Lo Yangtze ospita lo storione cinese, una specie in pericolo critico di estinzione la cui esistenza risale all'epoca dei dinosauri.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** O rio mais longo da Ásia é o Yangtzé.

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (130 chars) :** O Yangtzé abriga o esturjão chinês, uma espécie criticamente ameaçada de extinção cuja existência remonta à época dos dinossauros.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Самая длинная река в Азии — Янцзы.

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (130 chars) :** В Янцзы обитает китайский осетр, вид, находящийся под угрозой исчезновения, существование которого восходит ко времени динозавров.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 亚洲最长的河流是长江。

| Clé | Réponse |
|---|---|
| A | 真 ✅ |
| B | 假 |

**Correcte :** [A]

**Saviez-vous (35 chars) :** 长江是中华鲟的家园，这是一种极度濒危的物种，其存在可以追溯到恐龙时代。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** أطول نهر في آسيا هو نهر اليانغتسي.

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (104 chars) :** يعد نهر اليانغتسي موطنًا لسمك الحفش الصيني، وهو نوع مهدد بالانقراض بشدة ويعود وجوده إلى عصر الديناصورات.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ο μακρύτερος ποταμός στην Ασία είναι ο Γιανγκτσέ.

| Clé | Réponse |
|---|---|
| A | Αληθής ✅ |
| B | Ψευδής |

**Correcte :** [A]

**Saviez-vous (156 chars) :** Ο Γιανγκτσέ φιλοξενεί τον κινεζικό οξύρρυγχο, ένα είδος που απειλείται σοβαρά με εξαφάνιση, του οποίου η ύπαρξη χρονολογείται από την εποχή των δεινοσαύρων.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **true_false/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible
- **qcm/deceptive_trap** : ✅ OK
- **true_false/recognition** : ✅ OK

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ✅ OK
- **true_false/reasoning** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK
- **true_false/recognition** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ⚠️ ⚠️ tautologique (contient la réponse correcte "le nil") · ⚠️ SV sans marqueur de surprise visible → Le Nil a deux affluents principaux : le Nil Blanc et le Nil Bleu. Le Nil Bleu fournit la majorité de l'eau et du limon.
- **true_false/reasoning** : ⚠️ ⚠️ SV sans marqueur de surprise visible → La longueur des côtes françaises est difficile à déterminer précisément, car elle dépend de la méthode de mesure (niveau de détail des indentations considérées).
- **qcm/deceptive_trap** : ⚠️ ⚠️ tautologique (contient la réponse correcte "l'inde") · ⚠️ SV sans marqueur de surprise visible → Le différend frontalier entre l'Inde et la Chine a conduit à une guerre en 1962, et des escarmouches frontalières continuent de se produire sporadiquement le long de la ligne de contrôle réelle.
- **true_false/recognition** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Le Yangtsé abrite l'esturgeon de Chine, une espèce en danger critique d'extinction, dont l'existence remonte à l'époque des dinosaures.

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 4/5 variantes présentes — variantes manquantes bloquées par guards

#### Dérive sémantique vs noyau
- **qcm/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/reasoning** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **qcm/deceptive_trap** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)

---

## NOYAU 4 — #46 · Cinéma · depth 5

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 46 |
| intent_key | legacy_cinema-722c18218caa |
| semantic_key | cinema-academy-awards-best-picture |
| domain | Cinéma |
| sub_domain | Cinéma |
| difficulty_depth | 5 |
| subject | Oscars — Meilleur Film |
| angle_large | Récompenses cinématographiques |
| micro_angle | Films primés années 2000–2020 |
| answer_target | Titre du film lauréat |
| potential_trap | Confusion film nominé vs film lauréat |
| concept_family | academy-awards-best-picture |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 2/5 |
| Variantes manquantes | qcm/reasoning, qcm/deceptive_trap, true_false/recognition |
| Toutes langues complètes | Oui |
| Quality flags actifs | tautological_sv [#50] |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 50 |
| readable_code | CI-D05-Q-R-2DBE2 |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | academy-awards-best-picture |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel film a remporté l'Oscar du meilleur film en 1994 ?

| Clé | Réponse |
|---|---|
| A | La Liste de Schindler ✅ |
| B | Le Fugitif |
| C | Les Vestiges du jour |
| D | Philadelphia |

**Correcte :** [A]

**Saviez-vous (107 chars) :** Steven Spielberg a renoncé à son salaire pour La Liste de Schindler, le considérant comme l'argent du sang.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Which film won the Academy Award for Best Picture in 1994?

| Clé | Réponse |
|---|---|
| A | Schindler's List ✅ |
| B | The Fugitive |
| C | The Remains of the Day |
| D | Philadelphia |

**Correcte :** [A]

**Saviez-vous (84 chars) :** Steven Spielberg waived his salary for Schindler's List, considering it blood money.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Qué película ganó el Oscar a la mejor película en 1994?

| Clé | Réponse |
|---|---|
| A | La Lista de Schindler ✅ |
| B | El Fugitivo |
| C | Lo que queda del día |
| D | Philadelphia |

**Correcte :** [A]

**Saviez-vous (107 chars) :** Steven Spielberg renunció a su salario por La Lista de Schindler, considerándolo dinero manchado de sangre.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welcher Film gewann 1994 den Oscar als bester Film?

| Clé | Réponse |
|---|---|
| A | Schindlers Liste ✅ |
| B | Auf der Flucht |
| C | Was vom Tage übrig blieb |
| D | Philadelphia |

**Correcte :** [A]

**Saviez-vous (98 chars) :** Steven Spielberg verzichtete auf sein Gehalt für Schindlers Liste und betrachtete es als Blutgeld.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quale film ha vinto l'Oscar come miglior film nel 1994?

| Clé | Réponse |
|---|---|
| A | Schindler's List - La lista di Schindler ✅ |
| B | Il Fuggitivo |
| C | Quel che resta del giorno |
| D | Philadelphia |

**Correcte :** [A]

**Saviez-vous (123 chars) :** Steven Spielberg ha rinunciato al suo stipendio per Schindler's List - La lista di Schindler, considerandolo denaro sporco.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual filme ganhou o Oscar de Melhor Filme em 1994?

| Clé | Réponse |
|---|---|
| A | A Lista de Schindler ✅ |
| B | O Fugitivo |
| C | Os Vestígios do Dia |
| D | Filadélfia |

**Correcte :** [A]

**Saviez-vous (102 chars) :** Steven Spielberg renunciou ao seu salário por A Lista de Schindler, considerando-o dinheiro de sangue.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какой фильм получил премию «Оскар» за лучший фильм в 1994 году?

| Clé | Réponse |
|---|---|
| A | Список Шиндлера ✅ |
| B | Беглец |
| C | Остаток дня |
| D | Филадельфия |

**Correcte :** [A]

**Saviez-vous (96 chars) :** Стивен Спилберг отказался от своей зарплаты за «Список Шиндлера», считая это кровавыми деньгами.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 哪部电影获得了1994年奥斯卡最佳影片奖？

| Clé | Réponse |
|---|---|
| A | 辛德勒的名单 ✅ |
| B | 亡命天涯 |
| C | 告别有情天 |
| D | 费城故事 |

**Correcte :** [A]

**Saviez-vous (33 chars) :** 史蒂文·斯皮尔伯格放弃了《辛德勒的名单》的薪水，认为这是血腥的钱。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** أي فيلم فاز بجائزة الأوسكار لأفضل فيلم في عام 1994؟

| Clé | Réponse |
|---|---|
| A | قائمة شندلر ✅ |
| B | الهارب |
| C | بقايا اليوم |
| D | فيلادلفيا |

**Correcte :** [A]

**Saviez-vous (83 chars) :** تخلى ستيفن سبيلبرغ عن راتبه عن فيلم قائمة شندلر، معتبراً إياه مالاً ملطخاً بالدماء.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποια ταινία κέρδισε το Όσκαρ Καλύτερης Ταινίας το 1994;

| Clé | Réponse |
|---|---|
| A | Η Λίστα του Σίντλερ ✅ |
| B | Ο Φυγάς |
| C | Τα Απομεινάρια μιας Μέρας |
| D | Φιλαδέλφεια |

**Correcte :** [A]

**Saviez-vous (106 chars) :** Ο Στίβεν Σπίλμπεργκ παραιτήθηκε από τον μισθό του για τη Λίστα του Σίντλερ, θεωρώντας τον χρήματα αίματος.

</details>

---

#### Variante : `true_false/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2548 |
| readable_code | CI-D05-T-S-12A12 |
| question_type | true_false |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | film-history-satire |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Le film 'Le Dictateur' de Charlie Chaplin est-il une parodie d'Adolf Hitler ?

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (149 chars) :** Chaplin a regretté d'avoir fait le film, car il a dit qu'il n'aurait pas pu faire une satire des nazis s'il avait connu l'étendue de leurs atrocités.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Is Charlie Chaplin's film 'The Great Dictator' a parody of Adolf Hitler?

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (131 chars) :** Chaplin regretted making the film, as he said he could not have satirized the Nazis if he had known the extent of their atrocities.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Es la película 'El Gran Dictador' de Charlie Chaplin una parodia de Adolf Hitler?

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (144 chars) :** Chaplin lamentó haber hecho la película, ya que dijo que no podría haber satirizado a los nazis si hubiera sabido el alcance de sus atrocidades.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Ist Charlie Chaplins Film 'Der große Diktator' eine Parodie auf Adolf Hitler?

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (165 chars) :** Chaplin bereute es, den Film gedreht zu haben, da er sagte, er hätte die Nazis nicht satirisch darstellen können, wenn er das Ausmaß ihrer Gräueltaten gekannt hätte.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Il film 'Il Grande Dittatore' di Charlie Chaplin è una parodia di Adolf Hitler?

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (156 chars) :** Chaplin si pentì di aver fatto il film, perché disse che non avrebbe potuto fare una satira dei nazisti se avesse conosciuto la portata delle loro atrocità.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** O filme 'O Grande Ditador' de Charlie Chaplin é uma paródia de Adolf Hitler?

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (133 chars) :** Chaplin lamentou ter feito o filme, pois disse que não poderia ter satirizado os nazistas se soubesse a extensão de suas atrocidades.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Является ли фильм Чарли Чаплина «Великий диктатор» пародией на Адольфа Гитлера?

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (120 chars) :** Чаплин сожалел о создании фильма, так как говорил, что не смог бы высмеять нацистов, если бы знал масштабы их злодеяний.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 查理·卓别林的电影《大独裁者》是对阿道夫·希特勒的模仿吗？

| Clé | Réponse |
|---|---|
| A | 真 ✅ |
| B | 假 |

**Correcte :** [A]

**Saviez-vous (38 chars) :** 卓别林后悔拍了这部电影，因为他说如果他知道纳粹暴行的程度，他就无法讽刺他们。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** هل فيلم 'الديكتاتور العظيم' لتشارلي شابلن هو محاكاة ساخرة لأدولف هتلر؟

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (99 chars) :** ندم تشابلن على صنع الفيلم، لأنه قال إنه لم يكن بإمكانه أن يسخر من النازيين لو كان يعرف مدى فظائعهم.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Είναι η ταινία «Ο Μεγάλος Δικτάτορας» του Τσάρλι Τσάπλιν μια παρωδία του Αδόλφου Χίτλερ;

| Clé | Réponse |
|---|---|
| A | Αληθής ✅ |
| B | Ψευδής |

**Correcte :** [A]

**Saviez-vous (137 chars) :** Ο Τσάπλιν μετάνιωσε που έκανε την ταινία, καθώς είπε ότι δεν θα μπορούσε να σατιρίσει τους Ναζί αν γνώριζε την έκταση των θηριωδιών τους.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **true_false/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ✅ OK
- **true_false/reasoning** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ⚠️ ⚠️ tautologique (contient la réponse correcte "la liste de schindler") · ⚠️ SV sans marqueur de surprise visible → Steven Spielberg a renoncé à son salaire pour La Liste de Schindler, le considérant comme l'argent du sang.
- **true_false/reasoning** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Chaplin a regretté d'avoir fait le film, car il a dit qu'il n'aurait pas pu faire une satire des nazis s'il avait connu l'étendue de leurs atrocités.

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 2/5 variantes présentes — variantes manquantes bloquées par guards

#### Dérive sémantique vs noyau
- **qcm/recognition** : ✅ 2/7 mots-clés noyau présents
- **true_false/reasoning** : ✅ 1/7 mots-clés noyau présents

---

## NOYAU 5 — #64 · Cuisine · depth 6

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 64 |
| intent_key | legacy_cuisine-8f20a70d374c |
| semantic_key | cuisine-french-cuisine-ingredients |
| domain | Cuisine |
| sub_domain | Cuisine |
| difficulty_depth | 6 |
| subject | Ingrédients cuisine française |
| angle_large | Techniques et ingrédients culinaires |
| micro_angle | Herbes et épices régionales |
| answer_target | Ingrédient ou technique culinaire |
| potential_trap | Ingrédients similaires de régions différentes |
| concept_family | french-cuisine-ingredients |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 3/5 |
| Variantes manquantes | qcm/reasoning, true_false/recognition |
| Toutes langues complètes | Oui |
| Quality flags actifs | q_too_long_ar [#68:103>75], q_too_long_de [#68:146>110], q_too_long_el [#68:151>110], q_too_long_es [#68:137>110], q_too_long_fr [#68:144>110], q_too_long_it [#68:142>110], q_too_long_pt [#68:132>110], q_too_long_ru [#68:127>110] |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 68 |
| readable_code | CU-D06-Q-R-6CF06 |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | french-cuisine-ingredients |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel type de champignon est traditionnellement utilisé pour préparer la Duxelles, une garniture fine souvent utilisée dans la cuisine française?

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (161 chars) :** La duxelles a été inventée au 17ème siècle par le cuisinier du Marquis d'Uxelles, François Pierre de la Varenne, et a d'abord servi comme farce pour la volaille.

> ⚠️ question_text trop longue : 144 > max=110

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What type of mushroom is traditionally used to prepare Duxelles, a fine garnish often used in French cuisine?

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (158 chars) :** Duxelles was invented in the 17th century by the cook of the Marquis d'Uxelles, François Pierre de la Varenne, and initially served as a stuffing for poultry.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Qué tipo de hongo se utiliza tradicionalmente para preparar Duxelles, una guarnición fina que se utiliza a menudo en la cocina francesa?

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (158 chars) :** La duxelles fue inventada en el siglo XVII por el cocinero del marqués d'Uxelles, François Pierre de la Varenne, y sirvió inicialmente como relleno para aves.

> ⚠️ question_text trop longue : 137 > max=110

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welche Pilzart wird traditionell zur Zubereitung von Duxelles verwendet, einer feinen Garnitur, die oft in der französischen Küche verwendet wird?

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (151 chars) :** Duxelles wurde im 17. Jahrhundert vom Koch des Marquis d'Uxelles, François Pierre de la Varenne, erfunden und diente zunächst als Füllung für Geflügel.

> ⚠️ question_text trop longue : 146 > max=110

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quale tipo di fungo viene tradizionalmente utilizzato per preparare la Duxelles, una guarnizione fine spesso utilizzata nella cucina francese?

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (155 chars) :** La duxelles fu inventata nel XVII secolo dal cuoco del marchese d'Uxelles, François Pierre de la Varenne, e inizialmente servì come ripieno per il pollame.

> ⚠️ question_text trop longue : 142 > max=110

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Que tipo de cogumelo é tradicionalmente usado para preparar Duxelles, uma guarnição fina frequentemente usada na culinária francesa?

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (154 chars) :** A duxelles foi inventada no século XVII pelo cozinheiro do Marquês d'Uxelles, François Pierre de la Varenne, e inicialmente serviu como recheio para aves.

> ⚠️ question_text trop longue : 132 > max=110

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какой вид грибов традиционно используется для приготовления дюкселя, мелкого гарнира, часто используемого во французской кухне?

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (131 chars) :** Дюксель был изобретен в 17 веке поваром маркиза д'Юкселя, Франсуа Пьером де ла Варенном, и первоначально служил начинкой для птицы.

> ⚠️ question_text trop longue : 127 > max=110

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 传统上用哪种蘑菇来制作 Duxelles？Duxelles 是一种常用于法国菜肴中的精细配菜。

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (84 chars) :** Duxelles 是 17 世纪由 Marquis d'Uxelles 的厨师 François Pierre de la Varenne 发明的，最初用作家禽的馅料。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هو نوع الفطر الذي يستخدم تقليديًا لتحضير دوكسيل، وهو طبق جانبي فاخر يستخدم غالبًا في المطبخ الفرنسي؟

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (132 chars) :** تم اختراع الدوكسيل في القرن السابع عشر على يد طاهي المركيز د'أوكسيل، فرانسوا بيير دي لا فارين، وكان يستخدم في البداية كحشوة للدواجن.

> ⚠️ question_text trop longue : 103 > max=75

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Τι είδους μανιτάρι χρησιμοποιείται παραδοσιακά για την παρασκευή του Duxelles, μιας εκλεκτής γαρνιτούρας που χρησιμοποιείται συχνά στη γαλλική κουζίνα;

| Clé | Réponse |
|---|---|
| A | Champignons de Paris ✅ |
| B | Girolles |
| C | Cèpes |
| D | Morilles |

**Correcte :** [A]

**Saviez-vous (154 chars) :** Το duxelles εφευρέθηκε τον 17ο αιώνα από τον μάγειρα του Μαρκήσιου d'Uxelles, François Pierre de la Varenne, και αρχικά χρησίμευε ως γέμιση για πουλερικά.

> ⚠️ question_text trop longue : 151 > max=110

</details>

---

#### Variante : `qcm/deceptive_trap`

| Champ | Valeur |
|---|---|
| question_group_id | 2528 |
| readable_code | CU-D06-Q-D-634FC |
| question_type | qcm |
| cognitive_type | deceptive_trap |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | legume-cooking-preparation |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel est l'intérêt principal de faire tremper des légumineuses sèches avant de les cuire?

| Clé | Réponse |
|---|---|
| A | Réduire le temps de cuisson et améliorer la digestion. ✅ |
| B | Intensifier leur couleur naturelle. |
| C | Augmenter leur teneur en vitamines. |
| D | Faciliter l'absorption des graisses pendant la cuisson. |

**Correcte :** [A]

**Saviez-vous (185 chars) :** Les Aztèques utilisaient des cendres de bois dans l'eau de trempage des haricots pour accélérer le processus et améliorer leur digestibilité. C'est l'alcalinité des cendres qui aidait !

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What is the main benefit of soaking dried legumes before cooking them?

| Clé | Réponse |
|---|---|
| A | Reduce cooking time and improve digestion. ✅ |
| B | Intensify their natural color. |
| C | Increase their vitamin content. |
| D | Facilitate the absorption of fats during cooking. |

**Correcte :** [A]

**Saviez-vous (156 chars) :** The Aztecs used wood ash in the soaking water for beans to speed up the process and improve their digestibility. It's the alkalinity of the ash that helped!

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál es el principal beneficio de remojar las legumbres secas antes de cocinarlas?

| Clé | Réponse |
|---|---|
| A | Reducir el tiempo de cocción y mejorar la digestión. ✅ |
| B | Intensificar su color natural. |
| C | Aumentar su contenido de vitaminas. |
| D | Facilitar la absorción de grasas durante la cocción. |

**Correcte :** [A]

**Saviez-vous (177 chars) :** Los aztecas usaban cenizas de madera en el agua de remojo de los frijoles para acelerar el proceso y mejorar su digestibilidad. ¡Es la alcalinidad de las cenizas lo que ayudaba!

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Was ist der Hauptvorteil des Einweichens von getrockneten Hülsenfrüchten vor dem Kochen?

| Clé | Réponse |
|---|---|
| A | Verkürzung der Kochzeit und Verbesserung der Verdauung. ✅ |
| B | Intensivierung ihrer natürlichen Farbe. |
| C | Erhöhung ihres Vitamingehalts. |
| D | Erleichterung der Fettaufnahme während des Kochens. |

**Correcte :** [A]

**Saviez-vous (178 chars) :** Die Azteken verwendeten Holzasche im Einweichwasser für Bohnen, um den Prozess zu beschleunigen und ihre Verdaulichkeit zu verbessern. Es ist die Alkalinität der Asche, die half!

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è il vantaggio principale di mettere in ammollo i legumi secchi prima di cuocerli?

| Clé | Réponse |
|---|---|
| A | Ridurre i tempi di cottura e migliorare la digestione. ✅ |
| B | Intensificare il loro colore naturale. |
| C | Aumentare il loro contenuto di vitamine. |
| D | Facilitare l'assorbimento dei grassi durante la cottura. |

**Correcte :** [A]

**Saviez-vous (168 chars) :** Gli Aztechi usavano cenere di legno nell'acqua di ammollo dei fagioli per accelerare il processo e migliorarne la digeribilità. È l'alcalinità della cenere che aiutava!

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual é o principal benefício de demolhar leguminosas secas antes de cozinhá-las?

| Clé | Réponse |
|---|---|
| A | Reduzir o tempo de cozimento e melhorar a digestão. ✅ |
| B | Intensificar sua cor natural. |
| C | Aumentar seu teor de vitaminas. |
| D | Facilitar a absorção de gorduras durante o cozimento. |

**Correcte :** [A]

**Saviez-vous (164 chars) :** Os astecas usavam cinzas de madeira na água de imersão dos feijões para acelerar o processo e melhorar sua digestibilidade. É a alcalinidade das cinzas que ajudava!

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** В чем основная польза замачивания сухих бобовых перед приготовлением?

| Clé | Réponse |
|---|---|
| A | Сокращение времени приготовления и улучшение пищеварения. ✅ |
| B | Усиление их естественного цвета. |
| C | Увеличение содержания витаминов. |
| D | Облегчение усвоения жиров во время приготовления. |

**Correcte :** [A]

**Saviez-vous (147 chars) :** Ацтеки использовали древесную золу в воде для замачивания бобов, чтобы ускорить процесс и улучшить их усвояемость. Именно щелочность золы помогала!

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 烹饪干豆类之前浸泡的主要好处是什么？

| Clé | Réponse |
|---|---|
| A | 减少烹饪时间和改善消化。 ✅ |
| B | 增强其自然颜色。 |
| C | 增加其维生素含量。 |
| D | 促进烹饪过程中脂肪的吸收。 |

**Correcte :** [A]

**Saviez-vous (44 chars) :** 阿兹特克人在浸泡豆子的水中使用木灰，以加速这一过程并提高其消化率。是灰烬的碱性起了作用！

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هي الفائدة الرئيسية من نقع البقوليات الجافة قبل طهيها؟

| Clé | Réponse |
|---|---|
| A | تقليل وقت الطهي وتحسين الهضم. ✅ |
| B | تكثيف لونها الطبيعي. |
| C | زيادة محتواها من الفيتامينات. |
| D | تسهيل امتصاص الدهون أثناء الطهي. |

**Correcte :** [A]

**Saviez-vous (115 chars) :** استخدم الأزتيك رماد الخشب في ماء نقع الفاصوليا لتسريع العملية وتحسين قابليتها للهضم. إن قلوية الرماد هي التي ساعدت!

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιο είναι το κύριο όφελος από το μούλιασμα των αποξηραμένων οσπρίων πριν το μαγείρεμα;

| Clé | Réponse |
|---|---|
| A | Μείωση του χρόνου μαγειρέματος και βελτίωση της πέψης. ✅ |
| B | Ενίσχυση του φυσικού τους χρώματος. |
| C | Αύξηση της περιεκτικότητάς τους σε βιταμίνες. |
| D | Διευκόλυνση της απορρόφησης λιπών κατά το μαγείρεμα. |

**Correcte :** [A]

**Saviez-vous (193 chars) :** Οι Αζτέκοι χρησιμοποιούσαν στάχτη ξύλου στο νερό μουλιάσματος των φασολιών για να επιταχύνουν τη διαδικασία και να βελτιώσουν την πεπτικότητά τους. Είναι η αλκαλικότητα της στάχτης που βοήθησε!

</details>

---

#### Variante : `true_false/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2550 |
| readable_code | CU-D06-T-S-6B880 |
| question_type | true_false |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | honey-properties-usage |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Est-il vrai que le miel cristallisé est impropre à la consommation ?

| Clé | Réponse |
|---|---|
| A | Vrai |
| B | Faux ✅ |

**Correcte :** [B]

**Saviez-vous (153 chars) :** La cristallisation du miel est un indicateur de sa qualité naturelle. Le miel artificiel, souvent additionné de sucres, a tendance à ne pas cristalliser.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Is it true that crystallized honey is unfit for consumption?

| Clé | Réponse |
|---|---|
| A | True |
| B | False ✅ |

**Correcte :** [B]

**Saviez-vous (137 chars) :** The crystallization of honey is an indicator of its natural quality. Artificial honey, often with added sugars, tends not to crystallize.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Es cierto que la miel cristalizada no es apta para el consumo?

| Clé | Réponse |
|---|---|
| A | Verdadero |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (144 chars) :** La cristalización de la miel es un indicador de su calidad natural. La miel artificial, a menudo con azúcares añadidos, tiende a no cristalizar.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Stimmt es, dass kristallisiertes Honig nicht zum Verzehr geeignet ist?

| Clé | Réponse |
|---|---|
| A | Wahr |
| B | Falsch ✅ |

**Correcte :** [B]

**Saviez-vous (157 chars) :** Die Kristallisation von Honig ist ein Indikator für seine natürliche Qualität. Künstlicher Honig, oft mit Zuckerzusatz, neigt dazu, nicht zu kristallisieren.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** È vero che il miele cristallizzato non è adatto al consumo?

| Clé | Réponse |
|---|---|
| A | Vero |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (156 chars) :** La cristallizzazione del miele è un indicatore della sua qualità naturale. Il miele artificiale, spesso addizionato di zuccheri, tende a non cristallizzare.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** É verdade que o mel cristalizado é impróprio para consumo?

| Clé | Réponse |
|---|---|
| A | Verdadeiro |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (145 chars) :** A cristalização do mel é um indicador de sua qualidade natural. O mel artificial, frequentemente adicionado de açúcares, tende a não cristalizar.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Правда ли, что кристаллизовавшийся мед непригоден для употребления?

| Clé | Réponse |
|---|---|
| A | Правда |
| B | Неправда ✅ |

**Correcte :** [B]

**Saviez-vous (145 chars) :** Кристаллизация меда является показателем его природного качества. Искусственный мед, часто с добавлением сахара, как правило, не кристаллизуется.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 结晶的蜂蜜不适合食用，这是真的吗？

| Clé | Réponse |
|---|---|
| A | 真 |
| B | 假 ✅ |

**Correcte :** [B]

**Saviez-vous (32 chars) :** 蜂蜜的结晶是其天然品质的指标。人工蜂蜜通常添加糖，往往不会结晶。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** هل صحيح أن العسل المتبلور غير صالح للاستهلاك؟

| Clé | Réponse |
|---|---|
| A | صحيح |
| B | خاطئ ✅ |

**Correcte :** [B]

**Saviez-vous (115 chars) :** تبلور العسل هو مؤشر على جودته الطبيعية. العسل الاصطناعي، غالبًا ما يكون مضافًا إليه السكريات، يميل إلى عدم التبلور.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Είναι αλήθεια ότι το κρυσταλλωμένο μέλι δεν είναι κατάλληλο για κατανάλωση;

| Clé | Réponse |
|---|---|
| A | Αληθές |
| B | Ψευδές ✅ |

**Correcte :** [B]

**Saviez-vous (143 chars) :** Η κρυστάλλωση του μελιού είναι ένας δείκτης της φυσικής του ποιότητας. Το τεχνητό μέλι, συχνά με προσθήκη σακχάρων, τείνει να μην κρυσταλλώνει.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK
- **true_false/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ⚠️ Longueurs dépassées : Q-ar=103>75, Q-de=146>110, Q-el=151>110, Q-es=137>110, Q-fr=144>110, Q-it=142>110, Q-pt=132>110, Q-ru=127>110
- **qcm/deceptive_trap** : ✅ OK
- **true_false/reasoning** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ⚠️ ⚠️ SV sans marqueur de surprise visible → La duxelles a été inventée au 17ème siècle par le cuisinier du Marquis d'Uxelles, François Pierre de la Varenne, et a d'abord servi comme farce pour la volaille.
- **qcm/deceptive_trap** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Les Aztèques utilisaient des cendres de bois dans l'eau de trempage des haricots pour accélérer le processus et améliorer leur digestibilité. C'est l'alcalinité des cendres qui aidait !
- **true_false/reasoning** : ⚠️ ⚠️ SV sans marqueur de surprise visible → La cristallisation du miel est un indicateur de sa qualité naturelle. Le miel artificiel, souvent additionné de sucres, a tendance à ne pas cristalliser.

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 3/5 variantes présentes — variantes manquantes bloquées par guards
- ⚠️ question ar trop longue [68] (P3 non appliqué)
- ⚠️ question de trop longue [68] (P3 non appliqué)
- ⚠️ question el trop longue [68] (P3 non appliqué)
- ⚠️ question es trop longue [68] (P3 non appliqué)
- ⚠️ question fr trop longue [68] (P3 non appliqué)
- ⚠️ question it trop longue [68] (P3 non appliqué)
- ⚠️ question pt trop longue [68] (P3 non appliqué)
- ⚠️ question ru trop longue [68] (P3 non appliqué)

#### Dérive sémantique vs noyau
- **qcm/recognition** : ✅ 3/6 mots-clés noyau présents
- **qcm/deceptive_trap** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/reasoning** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)

---

## NOYAU 6 — #67 · Science · depth 6

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 67 |
| intent_key | legacy_science-116079ecd54a |
| semantic_key | science-coral-reef-ecosystem |
| domain | Science |
| sub_domain | Science |
| difficulty_depth | 6 |
| subject | Écosystème des récifs coralliens |
| angle_large | Écosystèmes marins |
| micro_angle | Symbioses et organismes clés |
| answer_target | Organisme ou relation écologique |
| potential_trap | Confusion corail / anémone / zooxanthelles |
| concept_family | coral-reef-ecosystem |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 2/5 |
| Variantes manquantes | qcm/reasoning, true_false/recognition, true_false/reasoning |
| Toutes langues complètes | Oui |
| Quality flags actifs | tautological_sv [#71], sv_too_long_ar [#71:149>140], q_too_long_ar [#71:78>75], q_too_long_de [#71:126>110], q_too_long_el [#71:134>110], q_too_long_es [#71:114>110], q_too_long_fr [#71:113>110], q_too_long_it [#71:112>110], tautological_sv [#2551] |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 71 |
| readable_code | SC-D06-Q-R-52DAF |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | coral-reef-ecosystem |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel est le nom de la plus grande structure biologique unique connue sur Terre, créée par des organismes vivants?

| Clé | Réponse |
|---|---|
| A | La Grande Barrière de Corail ✅ |
| B | La Forêt Amazonienne |
| C | Le Parc National de Yellowstone |
| D | Le Delta de l'Okavango |

**Correcte :** [A]

**Saviez-vous (187 chars) :** La Grande Barrière de Corail est si vaste qu'elle est visible depuis l'espace et abrite une biodiversité marine exceptionnelle, comprenant des milliers d'espèces de poissons et de coraux.

> ⚠️ question_text trop longue : 113 > max=110

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What is the name of the largest single biological structure known on Earth, created by living organisms?

| Clé | Réponse |
|---|---|
| A | The Great Barrier Reef ✅ |
| B | The Amazon Rainforest |
| C | Yellowstone National Park |
| D | The Okavango Delta |

**Correcte :** [A]

**Saviez-vous (161 chars) :** The Great Barrier Reef is so vast that it is visible from space and is home to exceptional marine biodiversity, including thousands of species of fish and coral.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál es el nombre de la estructura biológica única más grande conocida en la Tierra, creada por organismos vivos?

| Clé | Réponse |
|---|---|
| A | La Gran Barrera de Coral ✅ |
| B | La Selva Amazónica |
| C | El Parque Nacional de Yellowstone |
| D | El Delta del Okavango |

**Correcte :** [A]

**Saviez-vous (166 chars) :** La Gran Barrera de Coral es tan vasta que es visible desde el espacio y alberga una biodiversidad marina excepcional, incluyendo miles de especies de peces y corales.

> ⚠️ question_text trop longue : 114 > max=110

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Wie heißt die größte einzelne biologische Struktur, die auf der Erde bekannt ist und von lebenden Organismen geschaffen wurde?

| Clé | Réponse |
|---|---|
| A | Das Great Barrier Reef ✅ |
| B | Der Amazonas-Regenwald |
| C | Der Yellowstone-Nationalpark |
| D | Das Okavango-Delta |

**Correcte :** [A]

**Saviez-vous (180 chars) :** Das Great Barrier Reef ist so groß, dass es aus dem Weltraum sichtbar ist und eine außergewöhnliche marine Artenvielfalt beherbergt, darunter Tausende von Fisch- und Korallenarten.

> ⚠️ question_text trop longue : 126 > max=110

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è il nome della più grande struttura biologica singola conosciuta sulla Terra, creata da organismi viventi?

| Clé | Réponse |
|---|---|
| A | La Grande Barriera Corallina ✅ |
| B | La Foresta Amazzonica |
| C | Il Parco Nazionale di Yellowstone |
| D | Il Delta dell'Okavango |

**Correcte :** [A]

**Saviez-vous (165 chars) :** La Grande Barriera Corallina è così vasta da essere visibile dallo spazio e ospita un'eccezionale biodiversità marina, tra cui migliaia di specie di pesci e coralli.

> ⚠️ question_text trop longue : 112 > max=110

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual é o nome da maior estrutura biológica única conhecida na Terra, criada por organismos vivos?

| Clé | Réponse |
|---|---|
| A | A Grande Barreira de Corais ✅ |
| B | A Floresta Amazônica |
| C | O Parque Nacional de Yellowstone |
| D | O Delta do Okavango |

**Correcte :** [A]

**Saviez-vous (163 chars) :** A Grande Barreira de Corais é tão vasta que é visível do espaço e abriga uma biodiversidade marinha excepcional, incluindo milhares de espécies de peixes e corais.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Как называется крупнейшая известная на Земле единая биологическая структура, созданная живыми организмами?

| Clé | Réponse |
|---|---|
| A | Большой Барьерный риф ✅ |
| B | Амазонские тропические леса |
| C | Национальный парк Йеллоустоун |
| D | Дельта Окаванго |

**Correcte :** [A]

**Saviez-vous (169 chars) :** Большой Барьерный риф настолько огромен, что его видно из космоса, и он является домом для исключительного морского биоразнообразия, включая тысячи видов рыб и кораллов.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 地球上已知的由生物创造的最大的单一生物结构是什么？

| Clé | Réponse |
|---|---|
| A | 大堡礁 ✅ |
| B | 亚马逊雨林 |
| C | 黄石国家公园 |
| D | 奥卡万戈三角洲 |

**Correcte :** [A]

**Saviez-vous (45 chars) :** 大堡礁非常广阔，从太空都可以看到，并且是卓越的海洋生物多样性的家园，包括数千种鱼类和珊瑚。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هو اسم أكبر هيكل بيولوجي فريد معروف على الأرض، تم إنشاؤه بواسطة كائنات حية؟

| Clé | Réponse |
|---|---|
| A | الحاجز المرجاني العظيم ✅ |
| B | غابة الأمازون المطيرة |
| C | منتزه يلوستون الوطني |
| D | دلتا أوكافانغو |

**Correcte :** [A]

**Saviez-vous ⚠️ TROP LONG (149>140) :** الحاجز المرجاني العظيم واسع جدًا لدرجة أنه مرئي من الفضاء وهو موطن لتنوع بيولوجي بحري استثنائي، بما في ذلك آلاف الأنواع من الأسماك والشعاب المرجانية.

> ⚠️ question_text trop longue : 78 > max=75

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιο είναι το όνομα της μεγαλύτερης ενιαίας βιολογικής δομής που είναι γνωστή στη Γη, η οποία δημιουργήθηκε από ζωντανούς οργανισμούς;

| Clé | Réponse |
|---|---|
| A | Ο Μεγάλος Κοραλλιογενής Ύφαλος ✅ |
| B | Το τροπικό δάσος του Αμαζονίου |
| C | Το Εθνικό Πάρκο Yellowstone |
| D | Το Δέλτα Οκαβάνγκο |

**Correcte :** [A]

**Saviez-vous (191 chars) :** Ο Μεγάλος Κοραλλιογενής Ύφαλος είναι τόσο τεράστιος που είναι ορατός από το διάστημα και φιλοξενεί εξαιρετική θαλάσσια βιοποικιλότητα, συμπεριλαμβανομένων χιλιάδων ειδών ψαριών και κοραλλιών.

> ⚠️ question_text trop longue : 134 > max=110

</details>

---

#### Variante : `qcm/deceptive_trap`

| Champ | Valeur |
|---|---|
| question_group_id | 2551 |
| readable_code | SC-D06-Q-D-5D3FE |
| question_type | qcm |
| cognitive_type | deceptive_trap |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | automotive-safety-chemistry |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel est le principal gaz utilisé pour gonfler les airbags des voitures ?

| Clé | Réponse |
|---|---|
| A | Diazote ✅ |
| B | Oxygène |
| C | Argon |
| D | Dioxyde de carbone |

**Correcte :** [A]

**Saviez-vous (197 chars) :** Le diazote produit lors du déploiement d'un airbag est suffisamment pur pour être utilisé dans des applications industrielles, mais il est relâché dans l'atmosphère en raison de sa grande quantité.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What is the main gas used to inflate car airbags?

| Clé | Réponse |
|---|---|
| A | Nitrogen ✅ |
| B | Oxygen |
| C | Argon |
| D | Carbon dioxide |

**Correcte :** [A]

**Saviez-vous (176 chars) :** The nitrogen produced during the deployment of an airbag is pure enough to be used in industrial applications, but it is released into the atmosphere due to its large quantity.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál es el principal gas utilizado para inflar las bolsas de aire de los coches?

| Clé | Réponse |
|---|---|
| A | Nitrógeno ✅ |
| B | Oxígeno |
| C | Argón |
| D | Dióxido de carbono |

**Correcte :** [A]

**Saviez-vous (203 chars) :** El nitrógeno producido durante el despliegue de una bolsa de aire es lo suficientemente puro como para ser utilizado en aplicaciones industriales, pero se libera a la atmósfera debido a su gran cantidad.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welches ist das Hauptgas, das zum Aufblasen von Autoairbags verwendet wird?

| Clé | Réponse |
|---|---|
| A | Stickstoff ✅ |
| B | Sauerstoff |
| C | Argon |
| D | Kohlendioxid |

**Correcte :** [A]

**Saviez-vous (198 chars) :** Der bei der Auslösung eines Airbags produzierte Stickstoff ist rein genug, um in industriellen Anwendungen eingesetzt zu werden, wird aber aufgrund seiner großen Menge in die Atmosphäre freigesetzt.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è il gas principale utilizzato per gonfiare gli airbag delle auto?

| Clé | Réponse |
|---|---|
| A | Diazoto ✅ |
| B | Ossigeno |
| C | Argon |
| D | Anidride carbonica |

**Correcte :** [A]

**Saviez-vous (192 chars) :** L'azoto prodotto durante l'apertura di un airbag è sufficientemente puro da essere utilizzato in applicazioni industriali, ma viene rilasciato nell'atmosfera a causa della sua grande quantità.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual é o principal gás usado para inflar os airbags dos carros?

| Clé | Réponse |
|---|---|
| A | Diazoto ✅ |
| B | Oxigênio |
| C | Argônio |
| D | Dióxido de carbono |

**Correcte :** [A]

**Saviez-vous (179 chars) :** O nitrogênio produzido durante a implantação de um airbag é puro o suficiente para ser usado em aplicações industriais, mas é liberado na atmosfera devido à sua grande quantidade.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какой газ в основном используется для надувания автомобильных подушек безопасности?

| Clé | Réponse |
|---|---|
| A | Диазот ✅ |
| B | Кислород |
| C | Аргон |
| D | Углекислый газ |

**Correcte :** [A]

**Saviez-vous (173 chars) :** Азот, образующийся при раскрытии подушки безопасности, достаточно чист для использования в промышленных целях, но из-за его большого количества он выбрасывается в атмосферу.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 汽车安全气囊充气主要使用哪种气体？

| Clé | Réponse |
|---|---|
| A | 二氮气 ✅ |
| B | 氧气 |
| C | 氩气 |
| D | 二氧化碳 |

**Correcte :** [A]

**Saviez-vous (44 chars) :** 安全气囊展开过程中产生的氮气纯度足以用于工业应用，但由于其数量巨大，因此会释放到大气中。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هو الغاز الرئيسي المستخدم لنفخ الوسائد الهوائية في السيارات؟

| Clé | Réponse |
|---|---|
| A | النيتروجين ✅ |
| B | الأكسجين |
| C | الأرجون |
| D | ثاني أكسيد الكربون |

**Correcte :** [A]

**Saviez-vous (140 chars) :** النيتروجين الناتج أثناء نشر الوسادة الهوائية نقي بدرجة كافية لاستخدامه في التطبيقات الصناعية، ولكنه يطلق في الغلاف الجوي بسبب كميته الكبيرة.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιο είναι το κύριο αέριο που χρησιμοποιείται για να φουσκώσουν οι αερόσακοι των αυτοκινήτων;

| Clé | Réponse |
|---|---|
| A | Διαζώλιο ✅ |
| B | Οξυγόνο |
| C | Αργό |
| D | Διοξείδιο του άνθρακα |

**Correcte :** [A]

**Saviez-vous (193 chars) :** Το άζωτο που παράγεται κατά την ανάπτυξη ενός αερόσακου είναι αρκετά καθαρό για να χρησιμοποιηθεί σε βιομηχανικές εφαρμογές, αλλά απελευθερώνεται στην ατμόσφαιρα λόγω της μεγάλης ποσότητάς του.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ⚠️ Longueurs dépassées : Q-ar=78>75, SV-ar=149>140, Q-de=126>110, Q-el=134>110, Q-es=114>110, Q-fr=113>110, Q-it=112>110
- **qcm/deceptive_trap** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ⚠️ ⚠️ tautologique (contient la réponse correcte "la grande barrière de corail") · ⚠️ SV sans marqueur de surprise visible → La Grande Barrière de Corail est si vaste qu'elle est visible depuis l'espace et abrite une biodiversité marine exceptionnelle, comprenant des milliers d'espèces de poissons et de coraux.
- **qcm/deceptive_trap** : ⚠️ ⚠️ tautologique (contient la réponse correcte "diazote") · ⚠️ SV sans marqueur de surprise visible → Le diazote produit lors du déploiement d'un airbag est suffisamment pur pour être utilisé dans des applications industrielles, mais il est relâché dans l'atmosphère en raison de sa grande quantité.

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 2/5 variantes présentes — variantes manquantes bloquées par guards
- ⚠️ saviez_vous ar trop long [71] (P3 non appliqué)
- ⚠️ question ar trop longue [71] (P3 non appliqué)
- ⚠️ question de trop longue [71] (P3 non appliqué)
- ⚠️ question el trop longue [71] (P3 non appliqué)
- ⚠️ question es trop longue [71] (P3 non appliqué)
- ⚠️ question fr trop longue [71] (P3 non appliqué)
- ⚠️ question it trop longue [71] (P3 non appliqué)

#### Dérive sémantique vs noyau
- **qcm/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **qcm/deceptive_trap** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)

---

## NOYAU 7 — #85 · Art · depth 7

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 85 |
| intent_key | legacy_art-cd84e518f832 |
| semantic_key | art-french-romanticism |
| domain | Art |
| sub_domain | Art |
| difficulty_depth | 7 |
| subject | Romantisme français (peinture) |
| angle_large | Mouvements artistiques européens |
| micro_angle | Peintres et œuvres majeures |
| answer_target | Artiste ou œuvre du romantisme |
| potential_trap | Confusion romantisme / impressionnisme |
| concept_family | french-romanticism |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 3/5 |
| Variantes manquantes | qcm/deceptive_trap, true_false/recognition |
| Toutes langues complètes | Oui |
| Quality flags actifs | q_too_long_de [#89:135>110], q_too_long_el [#89:142>110], q_too_long_en [#89:122>110], q_too_long_es [#89:128>110], q_too_long_fr [#89:135>110], q_too_long_it [#89:135>110], q_too_long_pt [#89:121>110], q_too_long_ru [#89:136>110] |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 89 |
| readable_code | AR-D07-Q-R-71D08 |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | french-romanticism |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel artiste est célèbre pour avoir peint 'Le Radeau de la Méduse', une œuvre monumentale représentant les conséquences d'un naufrage ?

| Clé | Réponse |
|---|---|
| A | Théodore Géricault ✅ |
| B | Eugène Delacroix |
| C | Gustave Courbet |
| D | Jean-Auguste-Dominique Ingres |

**Correcte :** [A]

**Saviez-vous (172 chars) :** Géricault a réalisé de nombreuses études préparatoires en interrogeant des survivants et en étudiant des cadavres à l'hôpital pour rendre l'œuvre la plus réaliste possible.

> ⚠️ question_text trop longue : 135 > max=110

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Which artist is famous for painting 'The Raft of the Medusa', a monumental work depicting the consequences of a shipwreck?

| Clé | Réponse |
|---|---|
| A | Théodore Géricault ✅ |
| B | Eugène Delacroix |
| C | Gustave Courbet |
| D | Jean-Auguste-Dominique Ingres |

**Correcte :** [A]

**Saviez-vous (149 chars) :** Géricault made numerous preparatory studies by interviewing survivors and studying corpses in the hospital to make the work as realistic as possible.

> ⚠️ question_text trop longue : 122 > max=110

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Qué artista es famoso por pintar 'La balsa de la Medusa', una obra monumental que representa las consecuencias de un naufragio?

| Clé | Réponse |
|---|---|
| A | Théodore Géricault ✅ |
| B | Eugène Delacroix |
| C | Gustave Courbet |
| D | Jean-Auguste-Dominique Ingres |

**Correcte :** [A]

**Saviez-vous (167 chars) :** Géricault realizó numerosos estudios preparatorios entrevistando a supervivientes y estudiando cadáveres en el hospital para que la obra fuera lo más realista posible.

> ⚠️ question_text trop longue : 128 > max=110

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welcher Künstler ist berühmt für das Gemälde 'Das Floß der Medusa', ein monumentales Werk, das die Folgen eines Schiffbruchs darstellt?

| Clé | Réponse |
|---|---|
| A | Théodore Géricault ✅ |
| B | Eugène Delacroix |
| C | Gustave Courbet |
| D | Jean-Auguste-Dominique Ingres |

**Correcte :** [A]

**Saviez-vous (167 chars) :** Géricault fertigte zahlreiche Vorstudien an, indem er Überlebende befragte und Leichen im Krankenhaus untersuchte, um das Werk so realistisch wie möglich zu gestalten.

> ⚠️ question_text trop longue : 135 > max=110

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quale artista è famoso per aver dipinto 'La zattera della Medusa', un'opera monumentale che rappresenta le conseguenze di un naufragio?

| Clé | Réponse |
|---|---|
| A | Théodore Géricault ✅ |
| B | Eugène Delacroix |
| C | Gustave Courbet |
| D | Jean-Auguste-Dominique Ingres |

**Correcte :** [A]

**Saviez-vous (159 chars) :** Géricault realizzò numerosi studi preparatori intervistando i sopravvissuti e studiando i cadaveri in ospedale per rendere l'opera il più realistica possibile.

> ⚠️ question_text trop longue : 135 > max=110

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual artista é famoso por pintar 'A Jangada da Medusa', uma obra monumental que retrata as consequências de um naufrágio?

| Clé | Réponse |
|---|---|
| A | Théodore Géricault ✅ |
| B | Eugène Delacroix |
| C | Gustave Courbet |
| D | Jean-Auguste-Dominique Ingres |

**Correcte :** [A]

**Saviez-vous (157 chars) :** Géricault realizou numerosos estudos preparatórios entrevistando sobreviventes e estudando cadáveres no hospital para tornar a obra o mais realista possível.

> ⚠️ question_text trop longue : 121 > max=110

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какой художник известен тем, что написал картину «Плот «Медузы»», монументальное произведение, изображающее последствия кораблекрушения?

| Clé | Réponse |
|---|---|
| A | Теодор Жерико ✅ |
| B | Эжен Делакруа |
| C | Гюстав Курбе |
| D | Жан-Огюст-Доминик Энгр |

**Correcte :** [A]

**Saviez-vous (152 chars) :** Жерико провел многочисленные подготовительные исследования, опрашивая выживших и изучая трупы в больнице, чтобы сделать работу максимально реалистичной.

> ⚠️ question_text trop longue : 136 > max=110

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 哪位艺术家以绘画《梅杜萨之筏》而闻名？这是一幅描绘海难后果的巨作。

| Clé | Réponse |
|---|---|
| A | 西奥多·热里科 ✅ |
| B | 欧仁·德拉克罗瓦 |
| C | 古斯塔夫·库尔贝 |
| D | 让-奥古斯特-多米尼克·安格尔 |

**Correcte :** [A]

**Saviez-vous (39 chars) :** 热里科通过采访幸存者和研究医院的尸体进行了大量的准备研究，以使作品尽可能逼真。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** من هو الفنان المشهور برسم 'طوف ميدوسا'، وهو عمل ضخم يصور عواقب غرق سفينة؟

| Clé | Réponse |
|---|---|
| A | تيودور جيريكو ✅ |
| B | أوجين ديلاكروا |
| C | جوستاف كوربيه |
| D | جان أوغست دومينيك إنجرس |

**Correcte :** [A]

**Saviez-vous (120 chars) :** أجرى جيريكو العديد من الدراسات التحضيرية من خلال مقابلة الناجين ودراسة الجثث في المستشفى لجعل العمل واقعيًا قدر الإمكان.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιος καλλιτέχνης είναι διάσημος για τη ζωγραφική της 'Σχεδίας της Μέδουσας', ένα μνημειώδες έργο που απεικονίζει τις συνέπειες ενός ναυαγίου;

| Clé | Réponse |
|---|---|
| A | Τεοντόρ Ζερικώ ✅ |
| B | Ευγένιος Ντελακρουά |
| C | Γκυστάβ Κουρμπέ |
| D | Ζαν-Ωγκύστ-Ντομινίκ Ενγκρ |

**Correcte :** [A]

**Saviez-vous (185 chars) :** Ο Ζερικώ πραγματοποίησε πολλές προπαρασκευαστικές μελέτες παίρνοντας συνεντεύξεις από επιζώντες και μελετώντας πτώματα στο νοσοκομείο για να κάνει το έργο όσο το δυνατόν πιο ρεαλιστικό.

> ⚠️ question_text trop longue : 142 > max=110

</details>

---

#### Variante : `true_false/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2532 |
| readable_code | AR-D07-T-S-2CB65 |
| question_type | true_false |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | italian-renaissance-art |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Le Caravage a utilisé des modèles vivants du peuple pour ses figures religieuses.

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (179 chars) :** L'utilisation de modèles issus du peuple a souvent valu au Caravage des critiques pour son manque de décorum et son réalisme cru, mais cela a aussi contribué à son style novateur.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Caravaggio used live models from the common people for his religious figures.

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (168 chars) :** The use of models from the common people often earned Caravaggio criticism for his lack of decorum and his raw realism, but it also contributed to his innovative style.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** Caravaggio utilizó modelos vivos del pueblo para sus figuras religiosas.

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (159 chars) :** El uso de modelos del pueblo a menudo le valió a Caravaggio críticas por su falta de decoro y su realismo crudo, pero también contribuyó a su estilo innovador.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Caravaggio verwendete lebende Modelle aus dem Volk für seine religiösen Figuren.

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (178 chars) :** Die Verwendung von Modellen aus dem Volk brachte Caravaggio oft Kritik für seinen Mangel an Anstand und seinen rohen Realismus ein, trug aber auch zu seinem innovativen Stil bei.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Caravaggio utilizzò modelli viventi del popolo per le sue figure religiose.

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (168 chars) :** L'uso di modelli tratti dal popolo valse spesso a Caravaggio critiche per la sua mancanza di decoro e il suo crudo realismo, ma contribuì anche al suo stile innovativo.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Caravaggio usou modelos vivos do povo para suas figuras religiosas.

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (158 chars) :** O uso de modelos do povo muitas vezes rendeu a Caravaggio críticas por sua falta de decoro e seu realismo cru, mas também contribuiu para seu estilo inovador.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Караваджо использовал живых моделей из народа для своих религиозных фигур.

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (159 chars) :** Использование моделей из народа часто вызывало у Караваджо критику за отсутствие приличий и грубый реализм, но это также способствовало его новаторскому стилю.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 卡拉瓦乔在他的宗教人物画中使用了来自普通民众的真人模特。

| Clé | Réponse |
|---|---|
| A | 真 ✅ |
| B | 假 |

**Correcte :** [A]

**Saviez-vous (49 chars) :** 使用来自普通民众的模特经常使卡拉瓦乔因缺乏礼仪和粗犷的现实主义而受到批评，但也促成了他创新的风格。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** استخدم كارافاجيو نماذج حية من عامة الناس لشخصياته الدينية.

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (139 chars) :** غالبًا ما أكسب استخدام نماذج من عامة الناس كارافاجيو انتقادات بسبب افتقاره إلى اللياقة والواقعية الفجة، ولكنه ساهم أيضًا في أسلوبه المبتكر.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ο Καραβάτζιο χρησιμοποίησε ζωντανά μοντέλα από τον λαό για τις θρησκευτικές του μορφές.

| Clé | Réponse |
|---|---|
| A | Αληθής ✅ |
| B | Ψευδής |

**Correcte :** [A]

**Saviez-vous (162 chars) :** Η χρήση μοντέλων από τον λαό συχνά έφερε στον Καραβάτζιο κριτική για την έλλειψη ευπρέπειας και τον ωμό ρεαλισμό του, αλλά συνέβαλε επίσης στο καινοτόμο στυλ του.

</details>

---

#### Variante : `qcm/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2552 |
| readable_code | AR-D07-Q-S-EF05B |
| question_type | qcm |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | 20th-century-painting |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel artiste a peint 'Le Cri', symbole de l'angoisse existentielle moderne?

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (195 chars) :** Munch a réalisé plusieurs versions du 'Cri', en peinture et en lithographie. Il a décrit l'inspiration du tableau comme une expérience réelle où il a ressenti une 'grande clameur dans la nature'.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** Which artist painted 'The Scream', a symbol of modern existential angst?

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (185 chars) :** Munch created several versions of 'The Scream', in painting and lithography. He described the inspiration for the painting as a real experience where he felt a 'great scream in nature'.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Qué artista pintó 'El grito', símbolo de la angustia existencial moderna?

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (187 chars) :** Munch realizó varias versiones de 'El grito', en pintura y en litografía. Describió la inspiración del cuadro como una experiencia real en la que sintió un 'gran clamor en la naturaleza'.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welcher Künstler malte 'Der Schrei', ein Symbol der modernen existenziellen Angst?

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (204 chars) :** Munch schuf mehrere Versionen von 'Der Schrei', in Malerei und Lithographie. Er beschrieb die Inspiration für das Gemälde als eine reale Erfahrung, bei der er einen 'großen Schrei in der Natur' verspürte.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quale artista ha dipinto 'L'urlo', simbolo dell'angoscia esistenziale moderna?

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (174 chars) :** Munch realizzò diverse versioni de 'L'urlo', in pittura e litografia. Descrisse l'ispirazione del quadro come un'esperienza reale in cui sentì un 'grande grido nella natura'.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual artista pintou 'O Grito', um símbolo da angústia existencial moderna?

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (176 chars) :** Munch criou várias versões de 'O Grito', em pintura e litografia. Ele descreveu a inspiração para a pintura como uma experiência real onde sentiu um 'grande grito na natureza'.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какой художник написал картину «Крик», символ современного экзистенциального страха?

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (162 chars) :** Мунк создал несколько версий «Крика» в живописи и литографии. Он описал вдохновение для картины как реальный опыт, когда он почувствовал «громкий крик в природе».

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 哪位艺术家创作了象征现代存在主义焦虑的《呐喊》？

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (70 chars) :** Munch创作了几个版本的《呐喊》，包括绘画和石版画。他将这幅画的灵感描述为一次真实的经历，在那次经历中，他感受到了“大自然中的巨大呐喊”。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** من هو الفنان الذي رسم لوحة 'الصرخة'، رمز القلق الوجودي الحديث؟

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (133 chars) :** ابتكر مونش عدة نسخ من 'الصرخة'، في الرسم والنقش الحجري. ووصف الإلهام وراء اللوحة بأنه تجربة حقيقية شعر فيها 'بصرخة عظيمة في الطبيعة'.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιος καλλιτέχνης ζωγράφισε την «Κραυγή», ένα σύμβολο της σύγχρονης υπαρξιακής αγωνίας;

| Clé | Réponse |
|---|---|
| A | Edvard Munch ✅ |
| B | Vincent van Gogh |
| C | Gustav Klimt |
| D | Egon Schiele |

**Correcte :** [A]

**Saviez-vous (191 chars) :** Ο Munch δημιούργησε πολλές εκδόσεις της «Κραυγής», στη ζωγραφική και τη λιθογραφία. Περιέγραψε την έμπνευση για τον πίνακα ως μια πραγματική εμπειρία όπου ένιωσε μια «μεγάλη κραυγή στη φύση».

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **true_false/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible
- **qcm/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ⚠️ Longueurs dépassées : Q-de=135>110, Q-el=142>110, Q-en=122>110, Q-es=128>110, Q-fr=135>110, Q-it=135>110, Q-pt=121>110, Q-ru=136>110
- **true_false/reasoning** : ✅ OK
- **qcm/reasoning** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Géricault a réalisé de nombreuses études préparatoires en interrogeant des survivants et en étudiant des cadavres à l'hôpital pour rendre l'œuvre la plus réaliste possible.
- **true_false/reasoning** : ⚠️ ⚠️ SV sans marqueur de surprise visible → L'utilisation de modèles issus du peuple a souvent valu au Caravage des critiques pour son manque de décorum et son réalisme cru, mais cela a aussi contribué à son style novateur.
- **qcm/reasoning** : ⚠️ ⚠️ SV sans marqueur de surprise visible → Munch a réalisé plusieurs versions du 'Cri', en peinture et en lithographie. Il a décrit l'inspiration du tableau comme une expérience réelle où il a ressenti une 'grande clameur dans la nature'.

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 3/5 variantes présentes — variantes manquantes bloquées par guards
- ⚠️ question de trop longue [89] (P3 non appliqué)
- ⚠️ question el trop longue [89] (P3 non appliqué)
- ⚠️ question en trop longue [89] (P3 non appliqué)
- ⚠️ question es trop longue [89] (P3 non appliqué)
- ⚠️ question fr trop longue [89] (P3 non appliqué)
- ⚠️ question it trop longue [89] (P3 non appliqué)
- ⚠️ question pt trop longue [89] (P3 non appliqué)
- ⚠️ question ru trop longue [89] (P3 non appliqué)

#### Dérive sémantique vs noyau
- **qcm/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/reasoning** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **qcm/reasoning** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)

---

## NOYAU 8 — #100 · Histoire · depth 8

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 100 |
| intent_key | legacy_histoire-89caffd8f7bc |
| semantic_key | histoire-world-war-one |
| domain | Histoire |
| sub_domain | Histoire |
| difficulty_depth | 8 |
| subject | Première Guerre mondiale |
| angle_large | Guerres mondiales |
| micro_angle | Batailles décisives 1914–1918 |
| answer_target | Événement ou date de la Grande Guerre |
| potential_trap | Confusion WWI / WWII pour des batailles similaires |
| concept_family | world-war-one |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 3/5 |
| Variantes manquantes | qcm/reasoning, true_false/reasoning |
| Toutes langues complètes | Oui |
| Quality flags actifs | ans_too_long_el.answer_a [#104] |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 104 |
| readable_code | HI-D08-Q-R-94DFC |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | world-war-one |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel événement a marqué le début de la Première Guerre mondiale?

| Clé | Réponse |
|---|---|
| A | L'assassinat de l'archiduc François-Ferdinand d'Autriche ✅ |
| B | L'invasion de la Pologne par l'Allemagne |
| C | Le naufrage du Lusitania |
| D | La bataille de la Marne |

**Correcte :** [A]

**Saviez-vous (148 chars) :** L'archiduc François-Ferdinand était l'héritier du trône austro-hongrois et son assassinat a été perpétré par Gavrilo Princip, un nationaliste serbe.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What event marked the beginning of World War I?

| Clé | Réponse |
|---|---|
| A | The assassination of Archduke Franz Ferdinand of Austria ✅ |
| B | The invasion of Poland by Germany |
| C | The sinking of the Lusitania |
| D | The Battle of the Marne |

**Correcte :** [A]

**Saviez-vous (150 chars) :** Archduke Franz Ferdinand was the heir to the Austro-Hungarian throne, and his assassination was carried out by Gavrilo Princip, a Serbian nationalist.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Qué evento marcó el comienzo de la Primera Guerra Mundial?

| Clé | Réponse |
|---|---|
| A | El asesinato del archiduque Francisco Fernando de Austria ✅ |
| B | La invasión de Polonia por Alemania |
| C | El hundimiento del Lusitania |
| D | La batalla del Marne |

**Correcte :** [A]

**Saviez-vous (146 chars) :** El archiduque Francisco Fernando era el heredero al trono austrohúngaro y su asesinato fue perpetrado por Gavrilo Princip, un nacionalista serbio.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welches Ereignis markierte den Beginn des Ersten Weltkriegs?

| Clé | Réponse |
|---|---|
| A | Die Ermordung von Erzherzog Franz Ferdinand von Österreich ✅ |
| B | Der Einmarsch Deutschlands in Polen |
| C | Der Untergang der Lusitania |
| D | Die Schlacht an der Marne |

**Correcte :** [A]

**Saviez-vous (150 chars) :** Erzherzog Franz Ferdinand war der Thronfolger Österreich-Ungarns, und sein Attentat wurde von Gavrilo Princip, einem serbischen Nationalisten, verübt.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quale evento ha segnato l'inizio della Prima Guerra Mondiale?

| Clé | Réponse |
|---|---|
| A | L'assassinio dell'arciduca Francesco Ferdinando d'Austria ✅ |
| B | L'invasione della Polonia da parte della Germania |
| C | L'affondamento del Lusitania |
| D | La battaglia della Marna |

**Correcte :** [A]

**Saviez-vous (143 chars) :** L'arciduca Francesco Ferdinando era l'erede al trono austro-ungarico e il suo assassinio fu compiuto da Gavrilo Princip, un nazionalista serbo.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Que evento marcou o início da Primeira Guerra Mundial?

| Clé | Réponse |
|---|---|
| A | O assassinato do Arquiduque Francisco Ferdinando da Áustria ✅ |
| B | A invasão da Polônia pela Alemanha |
| C | O naufrágio do Lusitania |
| D | A Batalha do Marne |

**Correcte :** [A]

**Saviez-vous (150 chars) :** O Arquiduque Francisco Ferdinando era o herdeiro do trono austro-húngaro e seu assassinato foi perpetrado por Gavrilo Princip, um nacionalista sérvio.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какое событие ознаменовало начало Первой мировой войны?

| Clé | Réponse |
|---|---|
| A | Убийство эрцгерцога Франца Фердинанда Австрийского ✅ |
| B | Вторжение Германии в Польшу |
| C | Потопление «Лузитании» |
| D | Битва на Марне |

**Correcte :** [A]

**Saviez-vous (143 chars) :** Эрцгерцог Франц Фердинанд был наследником австро-венгерского престола, и его убийство было совершено Гаврило Принципом, сербским националистом.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 什么事件标志着第一次世界大战的开始？

| Clé | Réponse |
|---|---|
| A | 奥地利大公弗朗茨·斐迪南遇刺 ✅ |
| B | 德国入侵波兰 |
| C | 卢西塔尼亚号沉没 |
| D | 马恩河战役 |

**Correcte :** [A]

**Saviez-vous (49 chars) :** 弗朗茨·斐迪南大公是奥匈帝国的王位继承人，他的遇刺是由塞尔维亚民族主义者加夫里洛·普林西普实施的。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما الحدث الذي شكل بداية الحرب العالمية الأولى؟

| Clé | Réponse |
|---|---|
| A | اغتيال الأرشيدوق فرانز فرديناند النمساوي ✅ |
| B | غزو ألمانيا لبولندا |
| C | غرق لوسيتانيا |
| D | معركة المارن |

**Correcte :** [A]

**Saviez-vous (110 chars) :** كان الأرشيدوق فرانز فرديناند وريث العرش النمساوي المجري، وقد تم اغتياله على يد غافريلو برينسيب، وهو قومي صربي.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιο γεγονός σηματοδότησε την έναρξη του Α' Παγκοσμίου Πολέμου;

| Clé | Réponse |
|---|---|
| A | Η δολοφονία του Αρχιδούκα Φραγκίσκου Φερδινάνδου της Αυστρίας ✅ |
| B | Η εισβολή της Γερμανίας στην Πολωνία |
| C | Η βύθιση του Λουζιτάνια |
| D | Η μάχη του Μάρνη |

**Correcte :** [A]

**Saviez-vous (166 chars) :** Ο Αρχιδούκας Φραγκίσκος Φερδινάνδος ήταν ο κληρονόμος του αυστροουγγρικού θρόνου και η δολοφονία του πραγματοποιήθηκε από τον Γκαβρίλο Πρίντσιπ, έναν Σέρβο εθνικιστή.

</details>

---

#### Variante : `true_false/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 2533 |
| readable_code | HI-D08-T-R-2EF2E |
| question_type | true_false |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | 20th-century-european-history |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** La 'Révolution de velours' en Tchécoslovaquie en 1989 fut un conflit violent.

| Clé | Réponse |
|---|---|
| A | Vrai |
| B | Faux ✅ |

**Correcte :** [B]

**Saviez-vous (206 chars) :** La 'Révolution de velours' a été déclenchée par une manifestation étudiante pacifique brutalement réprimée par la police, inspirant des manifestations de masse qui ont conduit à la fin du régime communiste.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** The 'Velvet Revolution' in Czechoslovakia in 1989 was a violent conflict.

| Clé | Réponse |
|---|---|
| A | True |
| B | False ✅ |

**Correcte :** [B]

**Saviez-vous (177 chars) :** The 'Velvet Revolution' was triggered by a peaceful student demonstration brutally suppressed by the police, inspiring mass protests that led to the end of the communist regime.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** La 'Revolución de Terciopelo' en Checoslovaquia en 1989 fue un conflicto violento.

| Clé | Réponse |
|---|---|
| A | Verdadero |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (200 chars) :** La 'Revolución de Terciopelo' fue desencadenada por una manifestación estudiantil pacífica brutalmente reprimida por la policía, inspirando protestas masivas que llevaron al fin del régimen comunista.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Die 'Samtene Revolution' in der Tschechoslowakei im Jahr 1989 war ein gewaltsamer Konflikt.

| Clé | Réponse |
|---|---|
| A | Wahr |
| B | Falsch ✅ |

**Correcte :** [B]

**Saviez-vous (210 chars) :** Die 'Samtene Revolution' wurde durch eine friedliche Studentendemonstration ausgelöst, die von der Polizei brutal unterdrückt wurde und Massenproteste auslöste, die zum Ende des kommunistischen Regimes führten.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** La 'Rivoluzione di velluto' in Cecoslovacchia nel 1989 fu un conflitto violento.

| Clé | Réponse |
|---|---|
| A | Vero |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (193 chars) :** La 'Rivoluzione di velluto' fu innescata da una manifestazione studentesca pacifica brutalmente repressa dalla polizia, ispirando proteste di massa che portarono alla fine del regime comunista.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** A 'Revolução de Veludo' na Checoslováquia em 1989 foi um conflito violento.

| Clé | Réponse |
|---|---|
| A | Verdadeiro |
| B | Falso ✅ |

**Correcte :** [B]

**Saviez-vous (187 chars) :** A 'Revolução de Veludo' foi desencadeada por uma manifestação estudantil pacífica brutalmente reprimida pela polícia, inspirando protestos em massa que levaram ao fim do regime comunista.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** «Бархатная революция» в Чехословакии в 1989 году была насильственным конфликтом.

| Clé | Réponse |
|---|---|
| A | Правда |
| B | Ложь ✅ |

**Correcte :** [B]

**Saviez-vous (183 chars) :** «Бархатная революция» была вызвана мирной студенческой демонстрацией, жестоко подавленной полицией, что вдохновило массовые протесты, которые привели к концу коммунистического режима.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 1989年捷克斯洛伐克的“天鹅绒革命”是一场暴力冲突。

| Clé | Réponse |
|---|---|
| A | 真 |
| B | 假 ✅ |

**Correcte :** [B]

**Saviez-vous (66 chars) :** “天鹅绒革命”是由一次和平的学生示威活动引发的，该示威活动遭到警察的残酷镇压，从而引发了大规模抗议活动，最终导致共产主义政权的终结。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** كانت "الثورة المخملية" في تشيكوسلوفاكيا عام 1989 صراعًا عنيفًا.

| Clé | Réponse |
|---|---|
| A | صحيح |
| B | خاطئ ✅ |

**Correcte :** [B]

**Saviez-vous (126 chars) :** أُطلقت "الثورة المخملية" بسبب مظاهرة طلابية سلمية قمعتها الشرطة بوحشية، مما ألهم احتجاجات جماعية أدت إلى نهاية النظام الشيوعي.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Η «Βελούδινη Επανάσταση» στην Τσεχοσλοβακία το 1989 ήταν μια βίαιη σύγκρουση.

| Clé | Réponse |
|---|---|
| A | Αληθές |
| B | Ψευδές ✅ |

**Correcte :** [B]

**Saviez-vous (203 chars) :** Η «Βελούδινη Επανάσταση» πυροδοτήθηκε από μια ειρηνική φοιτητική διαδήλωση που καταστάλθηκε βάναυσα από την αστυνομία, εμπνέοντας μαζικές διαδηλώσεις που οδήγησαν στο τέλος του κομμουνιστικού καθεστώτος.

</details>

---

#### Variante : `qcm/deceptive_trap`

| Champ | Valeur |
|---|---|
| question_group_id | 2553 |
| readable_code | HI-D08-Q-D-9604E |
| question_type | qcm |
| cognitive_type | deceptive_trap |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | world-war-two-events |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quand la 'Nuit de Cristal', une série d'attaques antisémites en Allemagne, a-t-elle eu lieu?

| Clé | Réponse |
|---|---|
| A | Novembre 1938 ✅ |
| B | Septembre 1939 |
| C | Janvier 1933 |
| D | Avril 1940 |

**Correcte :** [A]

**Saviez-vous (151 chars) :** Bien que présentée comme une réaction spontanée à l'assassinat d'un diplomate allemand, la Nuit de Cristal fut en réalité planifiée par le régime nazi.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** When did the 'Kristallnacht', a series of antisemitic attacks in Germany, take place?

| Clé | Réponse |
|---|---|
| A | November 1938 ✅ |
| B | September 1939 |
| C | January 1933 |
| D | April 1940 |

**Correcte :** [A]

**Saviez-vous (142 chars) :** Although presented as a spontaneous reaction to the assassination of a German diplomat, Kristallnacht was actually planned by the Nazi regime.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuándo tuvo lugar la 'Noche de los Cristales Rotos', una serie de ataques antisemitas en Alemania?

| Clé | Réponse |
|---|---|
| A | Noviembre 1938 ✅ |
| B | Septiembre 1939 |
| C | Enero 1933 |
| D | Abril 1940 |

**Correcte :** [A]

**Saviez-vous (164 chars) :** Aunque se presentó como una reacción espontánea al asesinato de un diplomático alemán, la Noche de los Cristales Rotos fue en realidad planeada por el régimen nazi.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Wann fand die 'Reichskristallnacht', eine Reihe antisemitischer Angriffe in Deutschland, statt?

| Clé | Réponse |
|---|---|
| A | November 1938 ✅ |
| B | September 1939 |
| C | Januar 1933 |
| D | April 1940 |

**Correcte :** [A]

**Saviez-vous (165 chars) :** Obwohl sie als spontane Reaktion auf die Ermordung eines deutschen Diplomaten dargestellt wurde, wurde die Reichskristallnacht in Wirklichkeit vom NS-Regime geplant.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Quando ebbe luogo la 'Notte dei cristalli', una serie di attacchi antisemiti in Germania?

| Clé | Réponse |
|---|---|
| A | Novembre 1938 ✅ |
| B | Settembre 1939 |
| C | Gennaio 1933 |
| D | Aprile 1940 |

**Correcte :** [A]

**Saviez-vous (156 chars) :** Sebbene presentata come una reazione spontanea all'assassinio di un diplomatico tedesco, la Notte dei cristalli fu in realtà pianificata dal regime nazista.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Quando ocorreu a 'Noite de Cristal', uma série de ataques antissemitas na Alemanha?

| Clé | Réponse |
|---|---|
| A | Novembro de 1938 ✅ |
| B | Setembro de 1939 |
| C | Janeiro de 1933 |
| D | Abril de 1940 |

**Correcte :** [A]

**Saviez-vous (151 chars) :** Embora apresentada como uma reação espontânea ao assassinato de um diplomata alemão, a Noite de Cristal foi, na verdade, planejada pelo regime nazista.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Когда произошла «Хрустальная ночь», серия антисемитских атак в Германии?

| Clé | Réponse |
|---|---|
| A | Ноябрь 1938 ✅ |
| B | Сентябрь 1939 |
| C | Январь 1933 |
| D | Апрель 1940 |

**Correcte :** [A]

**Saviez-vous (151 chars) :** Хотя Хрустальная ночь была представлена как спонтанная реакция на убийство немецкого дипломата, на самом деле она была спланирована нацистским режимом.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 德国发生一系列反犹袭击事件“水晶之夜”是在什么时候发生的？

| Clé | Réponse |
|---|---|
| A | 1938年11月 ✅ |
| B | 1939年9月 |
| C | 1933年1月 |
| D | 1940年4月 |

**Correcte :** [A]

**Saviez-vous (44 chars) :** 虽然“水晶之夜”被描述为对一名德国外交官遇刺事件的自发反应，但实际上它是纳粹政权策划的。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** متى وقعت 'ليلة البلور'، وهي سلسلة من الهجمات المعادية للسامية في ألمانيا؟

| Clé | Réponse |
|---|---|
| A | نوفمبر 1938 ✅ |
| B | سبتمبر 1939 |
| C | يناير 1933 |
| D | أبريل 1940 |

**Correcte :** [A]

**Saviez-vous (131 chars) :** على الرغم من تقديمها على أنها رد فعل عفوي على اغتيال دبلوماسي ألماني، إلا أن ليلة البلور كانت في الواقع مخططة من قبل النظام النازي.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Πότε έλαβε χώρα η «Νύχτα των Κρυστάλλων», μια σειρά αντισημιτικών επιθέσεων στη Γερμανία;

| Clé | Réponse |
|---|---|
| A | Νοέμβριος 1938 ✅ |
| B | Σεπτέμβριος 1939 |
| C | Ιανουάριος 1933 |
| D | Απρίλιος 1940 |

**Correcte :** [A]

**Saviez-vous (168 chars) :** Αν και παρουσιάστηκε ως μια αυθόρμητη αντίδραση στη δολοφονία ενός Γερμανού διπλωμάτη, η Νύχτα των Κρυστάλλων σχεδιάστηκε στην πραγματικότητα από το ναζιστικό καθεστώς.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **true_false/recognition** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ⚠️ Longueurs dépassées : answer_a-el=61>60
- **true_false/recognition** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ⚠️ ⚠️ SV sans marqueur de surprise visible → L'archiduc François-Ferdinand était l'héritier du trône austro-hongrois et son assassinat a été perpétré par Gavrilo Princip, un nationaliste serbe.
- **true_false/recognition** : ⚠️ ⚠️ SV sans marqueur de surprise visible → La 'Révolution de velours' a été déclenchée par une manifestation étudiante pacifique brutalement réprimée par la police, inspirant des manifestations de masse qui ont conduit à la fin du régime communiste.
- **qcm/deceptive_trap** : ✅ OK (Bien que présentée comme une réaction spontanée à l'assassinat d'un diplomate allemand, la Nuit de Cristal fut en réalité planifiée par le régime nazi.)

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 3/5 variantes présentes — variantes manquantes bloquées par guards

#### Dérive sémantique vs noyau
- **qcm/recognition** : ✅ 3/4 mots-clés noyau présents
- **true_false/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **qcm/deceptive_trap** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)

---

## NOYAU 9 — #121 · Faune · depth 8

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 121 |
| intent_key | legacy_faune-17aa659d6fab |
| semantic_key | faune-avian-anatomy-adaptation |
| domain | Faune |
| sub_domain | Faune |
| difficulty_depth | 8 |
| subject | Anatomie et adaptations aviaires |
| angle_large | Biologie des oiseaux |
| micro_angle | Structures physiques et vol |
| answer_target | Structure anatomique ou adaptation |
| potential_trap | Adaptation partagée avec reptiles (évolution) |
| concept_family | avian-anatomy-adaptation |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 2/5 |
| Variantes manquantes | qcm/reasoning, qcm/deceptive_trap, true_false/recognition |
| Toutes langues complètes | Oui |
| Quality flags actifs | ✅ Aucun |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 125 |
| readable_code | FA-D08-Q-R-EF9B3 |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | avian-anatomy-adaptation |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel est le seul oiseau connu pour avoir des ailes munies de griffes fonctionnelles?

| Clé | Réponse |
|---|---|
| A | Le hoazin ✅ |
| B | L'autruche |
| C | Le casoar |
| D | Le kiwi |

**Correcte :** [A]

**Saviez-vous (160 chars) :** Les jeunes hoazins utilisent leurs griffes sur les ailes pour grimper aux arbres afin d'échapper aux prédateurs, une capacité unique parmi les oiseaux modernes.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What is the only bird known to have wings equipped with functional claws?

| Clé | Réponse |
|---|---|
| A | The hoatzin ✅ |
| B | The ostrich |
| C | The cassowary |
| D | The kiwi |

**Correcte :** [A]

**Saviez-vous (118 chars) :** Young hoatzins use their claws on their wings to climb trees to escape predators, a unique ability among modern birds.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál es la única ave conocida por tener alas equipadas con garras funcionales?

| Clé | Réponse |
|---|---|
| A | El hoacín ✅ |
| B | El avestruz |
| C | El casuario |
| D | El kiwi |

**Correcte :** [A]

**Saviez-vous (153 chars) :** Los hoacines jóvenes usan sus garras en las alas para trepar a los árboles para escapar de los depredadores, una habilidad única entre las aves modernas.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Welcher Vogel ist als einziger dafür bekannt, Flügel mit funktionellen Krallen zu haben?

| Clé | Réponse |
|---|---|
| A | Der Hoatzin ✅ |
| B | Der Strauß |
| C | Der Kasuar |
| D | Die Kiwi |

**Correcte :** [A]

**Saviez-vous (155 chars) :** Junge Hoatzins nutzen ihre Krallen an den Flügeln, um auf Bäume zu klettern und Raubtieren zu entkommen, eine einzigartige Fähigkeit unter modernen Vögeln.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è l'unico uccello conosciuto per avere ali dotate di artigli funzionali?

| Clé | Réponse |
|---|---|
| A | L'hoatzin ✅ |
| B | Lo struzzo |
| C | Il casuario |
| D | Il kiwi |

**Correcte :** [A]

**Saviez-vous (147 chars) :** I giovani hoatzin usano i loro artigli sulle ali per arrampicarsi sugli alberi per sfuggire ai predatori, un'abilità unica tra gli uccelli moderni.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual é a única ave conhecida por ter asas equipadas com garras funcionais?

| Clé | Réponse |
|---|---|
| A | O hoatzin ✅ |
| B | A avestruz |
| C | O casuar |
| D | O kiwi |

**Correcte :** [A]

**Saviez-vous (139 chars) :** Os hoatzins jovens usam suas garras nas asas para subir em árvores para escapar de predadores, uma habilidade única entre as aves modernas.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какая птица является единственной известной птицей, у которой есть крылья, оснащенные функциональными когтями?

| Clé | Réponse |
|---|---|
| A | Гоацин ✅ |
| B | Страус |
| C | Казуар |
| D | Киви |

**Correcte :** [A]

**Saviez-vous (158 chars) :** Молодые гоацины используют свои когти на крыльях, чтобы лазать по деревьям и убегать от хищников, что является уникальной способностью среди современных птиц.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 哪种鸟是已知唯一一种翅膀上长有功能爪子的鸟类？

| Clé | Réponse |
|---|---|
| A | 麝雉 ✅ |
| B | 鸵鸟 |
| C | 食火鸡 |
| D | 几维鸟 |

**Correcte :** [A]

**Saviez-vous (37 chars) :** 年幼的麝雉使用翅膀上的爪子爬树以躲避捕食者，这是现代鸟类中一种独特的能力。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هو الطائر الوحيد المعروف بامتلاكه أجنحة مجهزة بمخالب وظيفية؟

| Clé | Réponse |
|---|---|
| A | الهواتزين ✅ |
| B | النعامة |
| C | الشبنم |
| D | الكيوي |

**Correcte :** [A]

**Saviez-vous (128 chars) :** تستخدم طيور الهواتزين الصغيرة مخالبها على الأجنحة لتسلق الأشجار للهروب من الحيوانات المفترسة، وهي قدرة فريدة بين الطيور الحديثة.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιο είναι το μόνο γνωστό πτηνό που έχει φτερά εξοπλισμένα με λειτουργικά νύχια;

| Clé | Réponse |
|---|---|
| A | Ο γκιόας ✅ |
| B | Η στρουθοκάμηλος |
| C | Ο καζουάριος |
| D | Το κίουι |

**Correcte :** [A]

**Saviez-vous (171 chars) :** Οι νεαροί γκιόες χρησιμοποιούν τα νύχια τους στα φτερά για να σκαρφαλώνουν στα δέντρα για να ξεφύγουν από τα αρπακτικά, μια μοναδική ικανότητα μεταξύ των σύγχρονων πτηνών.

</details>

---

#### Variante : `true_false/reasoning`

| Champ | Valeur |
|---|---|
| question_group_id | 2538 |
| readable_code | FA-D08-T-S-05543 |
| question_type | true_false |
| cognitive_type | reasoning |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | antarctic-wildlife |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Un manchot empereur femelle ne pond qu'un seul œuf par saison de reproduction.

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (151 chars) :** La période d'incubation de l'œuf unique du manchot empereur dure environ 64 jours, pendant lesquels le mâle ne mange rien et peut perdre jusqu'à 12 kg.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** A female emperor penguin lays only one egg per breeding season.

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (140 chars) :** The incubation period for the emperor penguin's single egg lasts about 64 days, during which the male eats nothing and can lose up to 12 kg.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** Una hembra de pingüino emperador pone solo un huevo por temporada de reproducción.

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (162 chars) :** El período de incubación del único huevo del pingüino emperador dura aproximadamente 64 días, durante los cuales el macho no come nada y puede perder hasta 12 kg.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Ein weiblicher Kaiserpinguin legt nur ein Ei pro Brutsaison.

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (150 chars) :** Die Inkubationszeit für das einzelne Ei des Kaiserpinguins beträgt etwa 64 Tage, während der das Männchen nichts isst und bis zu 12 kg verlieren kann.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Un pinguino imperatore femmina depone un solo uovo per stagione riproduttiva.

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (159 chars) :** Il periodo di incubazione dell'unico uovo del pinguino imperatore dura circa 64 giorni, durante i quali il maschio non mangia nulla e può perdere fino a 12 kg.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Uma pinguim-imperador fêmea põe apenas um ovo por época de reprodução.

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (143 chars) :** O período de incubação do único ovo do pinguim-imperador dura cerca de 64 dias, durante os quais o macho não come nada e pode perder até 12 kg.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Самка императорского пингвина откладывает только одно яйцо за сезон размножения.

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (150 chars) :** Инкубационный период единственного яйца императорского пингвина длится около 64 дней, в течение которых самец ничего не ест и может потерять до 12 кг.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 雌性帝企鹅每个繁殖季节只产一枚卵。

| Clé | Réponse |
|---|---|
| A | 真 ✅ |
| B | 假 |

**Correcte :** [A]

**Saviez-vous (47 chars) :** 帝企鹅的单卵孵化期约为 64 天，在此期间，雄性不吃任何东西，体重可能会下降多达 12 公斤。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** لا تضع أنثى طائر البطريق الإمبراطور سوى بيضة واحدة في موسم التكاثر.

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (134 chars) :** تستغرق فترة حضانة البيضة الوحيدة لطائر البطريق الإمبراطور حوالي 64 يومًا، ولا يأكل الذكر خلالها شيئًا ويمكن أن يفقد ما يصل إلى 12 كجم.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ένας θηλυκός αυτοκρατορικός πιγκουίνος γεννά μόνο ένα αυγό ανά αναπαραγωγική περίοδο.

| Clé | Réponse |
|---|---|
| A | Αληθής ✅ |
| B | Ψευδής |

**Correcte :** [A]

**Saviez-vous (189 chars) :** Η περίοδος επώασης του μοναδικού αυγού του αυτοκρατορικού πιγκουίνου διαρκεί περίπου 64 ημέρες, κατά τη διάρκεια των οποίων το αρσενικό δεν τρώει τίποτα και μπορεί να χάσει έως και 12 κιλά.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **true_false/reasoning** : ⚠️ ⚠️ question reasoning sans marqueur causal visible

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ✅ OK
- **true_false/reasoning** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ✅ OK (Les jeunes hoazins utilisent leurs griffes sur les ailes pour grimper aux arbres afin d'échapper aux prédateurs, une capacité unique parmi les oiseaux modernes.)
- **true_false/reasoning** : ✅ OK (La période d'incubation de l'œuf unique du manchot empereur dure environ 64 jours, pendant lesquels le mâle ne mange rien et peut perdre jusqu'à 12 kg.)

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 2/5 variantes présentes — variantes manquantes bloquées par guards

#### Dérive sémantique vs noyau
- **qcm/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/reasoning** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)

---

## NOYAU 10 — #139 · Science · depth 9

### 1. Métadonnées noyau

| Champ | Valeur |
|---|---|
| question_intent_id | 139 |
| intent_key | legacy_science-7977927dba76 |
| semantic_key | science-astronomy-cosmic-structures |
| domain | Science |
| sub_domain | Science |
| difficulty_depth | 9 |
| subject | Structures cosmiques (astronomie) |
| angle_large | Cosmologie et astrophysique |
| micro_angle | Galaxies, amas et filaments |
| answer_target | Structure cosmique ou propriété |
| potential_trap | Confusion étoile / planète / galaxie à grande échelle |
| concept_family | astronomy-cosmic-structures |
| dialysis_status | pending |
| dialysed_at | — |

### 2. État final

**Statut :** 🟡 INCOMPLET

| Métrique | Valeur |
|---|---|
| Variantes présentes | 3/5 |
| Variantes manquantes | qcm/reasoning, true_false/reasoning |
| Toutes langues complètes | Oui |
| Quality flags actifs | tautological_sv [#143] |

### 3. Variantes finales

---

#### Variante : `qcm/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 143 |
| readable_code | SC-D09-Q-R-E96D2 |
| question_type | qcm |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | astronomy-cosmic-structures |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quel est le nom donné à la plus grande structure connue dans l'univers?

| Clé | Réponse |
|---|---|
| A | Le Grand Mur d'Hercule-Couronne boréale ✅ |
| B | Le Superamas de la Vierge |
| C | La Grande Tache rouge de Jupiter |
| D | L'Anneau de Feu du Pacifique |

**Correcte :** [A]

**Saviez-vous (161 chars) :** Le Grand Mur d'Hercule-Couronne boréale est si vaste que la lumière met environ 10 milliards d'années pour le traverser, soit environ 73 % de l'âge de l'univers.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What is the name given to the largest known structure in the universe?

| Clé | Réponse |
|---|---|
| A | The Hercules-Corona Borealis Great Wall ✅ |
| B | The Virgo Supercluster |
| C | The Great Red Spot of Jupiter |
| D | The Pacific Ring of Fire |

**Correcte :** [A]

**Saviez-vous (152 chars) :** The Hercules-Corona Borealis Great Wall is so vast that it takes light approximately 10 billion years to cross it, about 73% of the age of the universe.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál es el nombre que se le da a la estructura más grande conocida en el universo?

| Clé | Réponse |
|---|---|
| A | La Gran Muralla de Hércules-Corona Boreal ✅ |
| B | El Supercúmulo de Virgo |
| C | La Gran Mancha Roja de Júpiter |
| D | El Anillo de Fuego del Pacífico |

**Correcte :** [A]

**Saviez-vous (172 chars) :** La Gran Muralla de Hércules-Corona Boreal es tan vasta que la luz tarda aproximadamente 10 mil millones de años en cruzarla, aproximadamente el 73% de la edad del universo.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Wie heißt die größte bekannte Struktur im Universum?

| Clé | Réponse |
|---|---|
| A | Die Große Mauer von Herkules-Corona Borealis ✅ |
| B | Der Virgo-Superhaufen |
| C | Der Große Rote Fleck des Jupiter |
| D | Der pazifische Feuerring |

**Correcte :** [A]

**Saviez-vous (182 chars) :** Die Große Mauer von Herkules-Corona Borealis ist so groß, dass das Licht etwa 10 Milliarden Jahre benötigt, um sie zu durchqueren, was etwa 73 % des Alters des Universums entspricht.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è il nome dato alla più grande struttura conosciuta nell'universo?

| Clé | Réponse |
|---|---|
| A | La Grande Muraglia di Ercole-Corona Boreale ✅ |
| B | Il Superammasso della Vergine |
| C | La Grande Macchia Rossa di Giove |
| D | L'Anello di Fuoco del Pacifico |

**Correcte :** [A]

**Saviez-vous (158 chars) :** La Grande Muraglia di Ercole-Corona Boreale è così vasta che la luce impiega circa 10 miliardi di anni per attraversarla, circa il 73% dell'età dell'universo.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual é o nome dado à maior estrutura conhecida no universo?

| Clé | Réponse |
|---|---|
| A | A Grande Muralha de Hércules-Corona Borealis ✅ |
| B | O Superaglomerado de Virgem |
| C | A Grande Mancha Vermelha de Júpiter |
| D | O Anel de Fogo do Pacífico |

**Correcte :** [A]

**Saviez-vous (160 chars) :** A Grande Muralha de Hércules-Corona Borealis é tão vasta que a luz leva aproximadamente 10 bilhões de anos para atravessá-la, cerca de 73% da idade do universo.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Как называется самая большая известная структура во Вселенной?

| Clé | Réponse |
|---|---|
| A | Великая стена Геркулеса — Северной Короны ✅ |
| B | Сверхскопление Девы |
| C | Большое красное пятно Юпитера |
| D | Тихоокеанское огненное кольцо |

**Correcte :** [A]

**Saviez-vous (168 chars) :** Великая стена Геркулеса — Северной Короны настолько велика, что свету требуется около 10 миллиардов лет, чтобы пересечь ее, что составляет около 73% возраста Вселенной.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 宇宙中已知的最大结构是什么名字？

| Clé | Réponse |
|---|---|
| A | 武仙座-北冕座长城 ✅ |
| B | 室女座超星系团 |
| C | 木星大红斑 |
| D | 太平洋火环 |

**Correcte :** [A]

**Saviez-vous (39 chars) :** 武仙座-北冕座长城非常巨大，光穿过它大约需要100亿年，约占宇宙年龄的73%。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هو الاسم الذي يطلق على أكبر هيكل معروف في الكون؟

| Clé | Réponse |
|---|---|
| A | جدار هرقل-كورونا بورياليس العظيم ✅ |
| B | عنقود العذراء المجري الهائل |
| C | البقعة الحمراء العظيمة على كوكب المشتري |
| D | حلقة النار في المحيط الهادئ |

**Correcte :** [A]

**Saviez-vous (120 chars) :** جدار هرقل-كورونا بورياليس العظيم واسع جدًا لدرجة أن الضوء يستغرق حوالي 10 مليارات سنة لعبوره، أي حوالي 73٪ من عمر الكون.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιο είναι το όνομα που δίνεται στη μεγαλύτερη γνωστή δομή στο σύμπαν;

| Clé | Réponse |
|---|---|
| A | Το Μέγα Τείχος Ηρακλή-Στεφάνου Βορείου ✅ |
| B | Το Υπερσμήνος της Παρθένου |
| C | Η Μεγάλη Ερυθρά Κηλίδα του Δία |
| D | Ο Δακτύλιος της Φωτιάς του Ειρηνικού |

**Correcte :** [A]

**Saviez-vous (176 chars) :** Το Μέγα Τείχος Ηρακλή-Στεφάνου Βορείου είναι τόσο τεράστιο που το φως χρειάζεται περίπου 10 δισεκατομμύρια χρόνια για να το διασχίσει, περίπου το 73% της ηλικίας του σύμπαντος.

</details>

---

#### Variante : `qcm/deceptive_trap`

| Champ | Valeur |
|---|---|
| question_group_id | 2540 |
| readable_code | SC-D09-Q-D-A3E6B |
| question_type | qcm |
| cognitive_type | deceptive_trap |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | water-properties-science |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** Quelle est la principale raison pour laquelle l'eau mouille ?

| Clé | Réponse |
|---|---|
| A | La tension superficielle de l'eau est faible. ✅ |
| B | L'eau est un solvant universel. |
| C | L'eau possède une forte viscosité. |
| D | L'eau est incolore et inodore. |

**Correcte :** [A]

**Saviez-vous (164 chars) :** La tension superficielle de l'eau est due à la cohésion entre les molécules d'eau. Les gouttes d'eau sont sphériques à cause de cette tension minimisant la surface.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** What is the main reason why water wets?

| Clé | Réponse |
|---|---|
| A | The surface tension of water is low. ✅ |
| B | Water is a universal solvent. |
| C | Water has a high viscosity. |
| D | Water is colorless and odorless. |

**Correcte :** [A]

**Saviez-vous (153 chars) :** The surface tension of water is due to the cohesion between water molecules. Water droplets are spherical because of this tension minimizing the surface.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** ¿Cuál es la principal razón por la que el agua moja?

| Clé | Réponse |
|---|---|
| A | La tensión superficial del agua es baja. ✅ |
| B | El agua es un disolvente universal. |
| C | El agua tiene una alta viscosidad. |
| D | El agua es incolora e inodora. |

**Correcte :** [A]

**Saviez-vous (164 chars) :** La tensión superficial del agua se debe a la cohesión entre las moléculas de agua. Las gotas de agua son esféricas debido a esta tensión que minimiza la superficie.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Was ist der Hauptgrund, warum Wasser benetzt?

| Clé | Réponse |
|---|---|
| A | Die Oberflächenspannung von Wasser ist gering. ✅ |
| B | Wasser ist ein universelles Lösungsmittel. |
| C | Wasser hat eine hohe Viskosität. |
| D | Wasser ist farblos und geruchlos. |

**Correcte :** [A]

**Saviez-vous (181 chars) :** Die Oberflächenspannung des Wassers beruht auf dem Zusammenhalt zwischen den Wassermolekülen. Wassertropfen sind aufgrund dieser Spannung, die die Oberfläche minimiert, kugelförmig.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** Qual è la ragione principale per cui l'acqua bagna?

| Clé | Réponse |
|---|---|
| A | La tensione superficiale dell'acqua è bassa. ✅ |
| B | L'acqua è un solvente universale. |
| C | L'acqua ha un'alta viscosità. |
| D | L'acqua è incolore e inodore. |

**Correcte :** [A]

**Saviez-vous (170 chars) :** La tensione superficiale dell'acqua è dovuta alla coesione tra le molecole d'acqua. Le gocce d'acqua sono sferiche a causa di questa tensione che minimizza la superficie.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** Qual é a principal razão pela qual a água molha?

| Clé | Réponse |
|---|---|
| A | A tensão superficial da água é baixa. ✅ |
| B | A água é um solvente universal. |
| C | A água tem uma alta viscosidade. |
| D | A água é incolor e inodora. |

**Correcte :** [A]

**Saviez-vous (152 chars) :** A tensão superficial da água deve-se à coesão entre as moléculas de água. As gotas de água são esféricas devido a essa tensão que minimiza a superfície.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Какова основная причина того, что вода мочит?

| Clé | Réponse |
|---|---|
| A | Низкое поверхностное натяжение воды. ✅ |
| B | Вода является универсальным растворителем. |
| C | Вода обладает высокой вязкостью. |
| D | Вода бесцветна и не имеет запаха. |

**Correcte :** [A]

**Saviez-vous (161 chars) :** Поверхностное натяжение воды обусловлено сцеплением между молекулами воды. Капли воды имеют сферическую форму из-за этого натяжения, минимизирующего поверхность.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 水润湿的主要原因是什么？

| Clé | Réponse |
|---|---|
| A | 水的表面张力很低。 ✅ |
| B | 水是一种通用溶剂。 |
| C | 水具有高粘度。 |
| D | 水是无色无味的。 |

**Correcte :** [A]

**Saviez-vous (42 chars) :** 水的表面张力是由于水分子之间的内聚力引起的。水滴呈球形是由于这种张力使表面积最小化。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** ما هو السبب الرئيسي لتبليل الماء؟

| Clé | Réponse |
|---|---|
| A | التوتر السطحي للماء منخفض. ✅ |
| B | الماء مذيب عالمي. |
| C | الماء لديه لزوجة عالية. |
| D | الماء عديم اللون والرائحة. |

**Correcte :** [A]

**Saviez-vous (114 chars) :** التوتر السطحي للماء يرجع إلى التماسك بين جزيئات الماء. قطرات الماء كروية بسبب هذا التوتر الذي يقلل من مساحة السطح.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Ποιος είναι ο κύριος λόγος για τον οποίο το νερό βρέχει;

| Clé | Réponse |
|---|---|
| A | Η επιφανειακή τάση του νερού είναι χαμηλή. ✅ |
| B | Το νερό είναι ένας καθολικός διαλύτης. |
| C | Το νερό έχει υψηλό ιξώδες. |
| D | Το νερό είναι άχρωμο και άοσμο. |

**Correcte :** [A]

**Saviez-vous (166 chars) :** Η επιφανειακή τάση του νερού οφείλεται στη συνοχή μεταξύ των μορίων του νερού. Οι σταγόνες νερού είναι σφαιρικές λόγω αυτής της τάσης που ελαχιστοποιεί την επιφάνεια.

</details>

---

#### Variante : `true_false/recognition`

| Champ | Valeur |
|---|---|
| question_group_id | 2541 |
| readable_code | SC-D09-T-R-C02FF |
| question_type | true_false |
| cognitive_type | recognition |
| post_review_status | ready_bank |
| validated | Oui |
| source | gemini |
| concept_family | astronomy-lunar-science |
| langues présentes | ar, de, el, en, es, fr, it, pt, ru, zh |

<details>
<summary><strong>🇫🇷 Français (fr)</strong></summary>

**Question :** La Lune est en rotation synchrone avec la Terre, nous montrant toujours la même face.

| Clé | Réponse |
|---|---|
| A | Vrai ✅ |
| B | Faux |

**Correcte :** [A]

**Saviez-vous (165 chars) :** Bien que nous ne voyions jamais la 'face cachée' directement, environ 59% de la surface lunaire est visible depuis la Terre au fil du temps en raison des librations.

</details>

<details>
<summary><strong>🇬🇧 English (en)</strong></summary>

**Question :** The Moon is in synchronous rotation with the Earth, always showing us the same face.

| Clé | Réponse |
|---|---|
| A | True ✅ |
| B | False |

**Correcte :** [A]

**Saviez-vous (128 chars) :** Although we never see the 'far side' directly, about 59% of the lunar surface is visible from Earth over time due to librations.

</details>

<details>
<summary><strong>🇪🇸 Español (es)</strong></summary>

**Question :** La Luna está en rotación síncrona con la Tierra, mostrándonos siempre la misma cara.

| Clé | Réponse |
|---|---|
| A | Verdadero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (157 chars) :** Aunque nunca vemos la 'cara oculta' directamente, alrededor del 59% de la superficie lunar es visible desde la Tierra con el tiempo debido a las libraciones.

</details>

<details>
<summary><strong>🇩🇪 Deutsch (de)</strong></summary>

**Question :** Der Mond befindet sich in synchroner Rotation mit der Erde und zeigt uns immer die gleiche Seite.

| Clé | Réponse |
|---|---|
| A | Wahr ✅ |
| B | Falsch |

**Correcte :** [A]

**Saviez-vous (148 chars) :** Obwohl wir die 'Rückseite' nie direkt sehen, sind im Laufe der Zeit etwa 59 % der Mondoberfläche von der Erde aus aufgrund von Librationen sichtbar.

</details>

<details>
<summary><strong>🇮🇹 Italiano (it)</strong></summary>

**Question :** La Luna è in rotazione sincrona con la Terra, mostrandoci sempre la stessa faccia.

| Clé | Réponse |
|---|---|
| A | Vero ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (155 chars) :** Anche se non vediamo mai direttamente la 'faccia nascosta', circa il 59% della superficie lunare è visibile dalla Terra nel tempo a causa delle librazioni.

</details>

<details>
<summary><strong>🇵🇹 Português (pt)</strong></summary>

**Question :** A Lua está em rotação síncrona com a Terra, sempre nos mostrando a mesma face.

| Clé | Réponse |
|---|---|
| A | Verdadeiro ✅ |
| B | Falso |

**Correcte :** [A]

**Saviez-vous (139 chars) :** Embora nunca vejamos a 'face oculta' diretamente, cerca de 59% da superfície lunar é visível da Terra ao longo do tempo devido às libações.

</details>

<details>
<summary><strong>🇷🇺 Русский (ru)</strong></summary>

**Question :** Луна находится в синхронном вращении с Землей, всегда показывая нам одну и ту же сторону.

| Clé | Réponse |
|---|---|
| A | Правда ✅ |
| B | Ложь |

**Correcte :** [A]

**Saviez-vous (131 chars) :** Хотя мы никогда не видим «обратную сторону» напрямую, около 59% лунной поверхности видно с Земли с течением времени из-за либраций.

</details>

<details>
<summary><strong>🇨🇳 中文 (zh)</strong></summary>

**Question :** 月球与地球处于同步自转状态，总是向我们展示同一面。

| Clé | Réponse |
|---|---|
| A | 真 ✅ |
| B | 假 |

**Correcte :** [A]

**Saviez-vous (51 chars) :** 虽然我们从未直接看到“背面”，但由于天平动，随着时间的推移，从地球上可以看到大约 59% 的月球表面。

</details>

<details>
<summary><strong>🇸🇦 العربية (ar)</strong></summary>

**Question :** يدور القمر في دوران متزامن مع الأرض، ويظهر لنا دائمًا نفس الوجه.

| Clé | Réponse |
|---|---|
| A | صحيح ✅ |
| B | خاطئ |

**Correcte :** [A]

**Saviez-vous (125 chars) :** على الرغم من أننا لا نرى أبدًا 'الجانب البعيد' مباشرة، إلا أن حوالي 59٪ من سطح القمر مرئي من الأرض بمرور الوقت بسبب الترنحات.

</details>

<details>
<summary><strong>🇬🇷 Ελληνικά (el)</strong></summary>

**Question :** Η Σελήνη βρίσκεται σε σύγχρονη περιστροφή με τη Γη, δείχνοντάς μας πάντα την ίδια πλευρά.

| Clé | Réponse |
|---|---|
| A | Αλήθεια ✅ |
| B | Ψέμα |

**Correcte :** [A]

**Saviez-vous (158 chars) :** Αν και δεν βλέπουμε ποτέ την 'αθέατη πλευρά' άμεσα, περίπου το 59% της σεληνιακής επιφάνειας είναι ορατό από τη Γη με την πάροδο του χρόνου λόγω των λίκνισμα.

</details>

### 4. Analyse humaine

#### Cohérence cognitive
- **qcm/recognition** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK
- **true_false/recognition** : ✅ OK

#### Cohérence gameplay / lisibilité mobile
- **qcm/recognition** : ✅ OK
- **qcm/deceptive_trap** : ✅ OK
- **true_false/recognition** : ✅ OK

#### Qualité des Saviez-vous (FR)
- **qcm/recognition** : ⚠️ ⚠️ tautologique (contient la réponse correcte "le grand mur d'hercule-couronne boréale") · ⚠️ SV sans marqueur de surprise visible → Le Grand Mur d'Hercule-Couronne boréale est si vaste que la lumière met environ 10 milliards d'années pour le traverser, soit environ 73 % de l'âge de l'univers.
- **qcm/deceptive_trap** : ⚠️ ⚠️ SV sans marqueur de surprise visible → La tension superficielle de l'eau est due à la cohésion entre les molécules d'eau. Les gouttes d'eau sont sphériques à cause de cette tension minimisant la surface.
- **true_false/recognition** : ✅ OK (Bien que nous ne voyions jamais la 'face cachée' directement, environ 59% de la surface lunaire est visible depuis la Terre au fil du temps en raison des librations.)

#### Diversité des variantes
- ✅ Pas de doublons détectés

#### Problèmes encore visibles
- ❌ 3/5 variantes présentes — variantes manquantes bloquées par guards

#### Dérive sémantique vs noyau
- **qcm/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **qcm/deceptive_trap** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)
- **true_false/recognition** : ⚠️ dérive potentielle — aucun mot-clé du noyau dans la question (P4 non appliqué)

---

*Généré par `questions:dialyse:final-export` le 2026-05-22 18:12:12*