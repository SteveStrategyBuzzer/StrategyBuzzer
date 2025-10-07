<?php

namespace App\Services;

class QuestionService
{
    private $themes = [
        'general' => 'culture générale',
        'geographie' => 'géographie',
        'histoire' => 'histoire',
        'sport' => 'sport',
        'cuisine' => 'cuisine et gastronomie',
        'faune' => 'animaux et nature',
        'sciences' => 'sciences',
    ];

    public function generateQuestion($theme, $niveau, $questionNumber, $usedQuestionIds = [])
    {
        $themeLabel = $this->themes[$theme] ?? 'culture générale';
        
        $questions = $this->getStaticQuestions($theme, $niveau);
        
        // Filtrer les questions déjà utilisées
        $availableQuestions = array_filter($questions, function($question, $index) use ($usedQuestionIds, $theme) {
            $questionId = $theme . '_' . $index;
            return !in_array($questionId, $usedQuestionIds);
        }, ARRAY_FILTER_USE_BOTH);
        
        // Si toutes les questions ont été utilisées, réinitialiser
        if (empty($availableQuestions)) {
            $availableQuestions = $questions;
        }
        
        // Sélectionner aléatoirement une question disponible
        $randomIndex = array_rand($availableQuestions);
        $question = $availableQuestions[$randomIndex];
        $questionId = $theme . '_' . $randomIndex;
        
        return [
            'id' => $questionId,
            'text' => $question['text'],
            'type' => $question['type'] ?? 'multiple',
            'answers' => $question['answers'],
            'correct_index' => $question['correct_index'],
            'difficulty' => $niveau,
            'theme' => $theme,
        ];
    }

    private function getStaticQuestions($theme, $niveau)
    {
        $difficultyMultiplier = ceil($niveau / 10);
        
        $baseQuestions = [
            'general' => [
                ['text' => 'Combien de pays sont dans l\'ONU?', 'type' => 'multiple', 'answers' => ['193', '201', '79', '101'], 'correct_index' => 0],
                ['text' => 'La Tour Eiffel est à Paris', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Australie?', 'type' => 'multiple', 'answers' => ['Canberra', 'Sydney', 'Melbourne', 'Brisbane'], 'correct_index' => 0],
                ['text' => 'En quelle année a débuté le 21ème siècle?', 'type' => 'multiple', 'answers' => ['2001', '2000', '1999', '2002'], 'correct_index' => 0],
                ['text' => 'Combien de continents y a-t-il sur Terre?', 'type' => 'multiple', 'answers' => ['7', '5', '6', '8'], 'correct_index' => 0],
                ['text' => 'Le Soleil est une étoile', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la langue la plus parlée au monde?', 'type' => 'multiple', 'answers' => ['Mandarin', 'Anglais', 'Espagnol', 'Hindi'], 'correct_index' => 0],
                ['text' => 'Combien de secondes y a-t-il dans une heure?', 'type' => 'multiple', 'answers' => ['3600', '60', '600', '360'], 'correct_index' => 0],
                ['text' => 'La Lune tourne autour de la Terre', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est le plus petit pays du monde?', 'type' => 'multiple', 'answers' => ['Vatican', 'Monaco', 'San Marin', 'Liechtenstein'], 'correct_index' => 0],
                ['text' => 'Quelle est la monnaie du Japon?', 'type' => 'multiple', 'answers' => ['Yen', 'Won', 'Yuan', 'Rupiah'], 'correct_index' => 0],
                ['text' => 'La Terre est ronde', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de jours y a-t-il dans une année bissextile?', 'type' => 'multiple', 'answers' => ['366', '365', '364', '367'], 'correct_index' => 0],
                ['text' => 'Quel est le symbole chimique de l\'eau?', 'type' => 'multiple', 'answers' => ['H2O', 'CO2', 'O2', 'H2'], 'correct_index' => 0],
                ['text' => 'Paris est la capitale de la France', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de lettres y a-t-il dans l\'alphabet français?', 'type' => 'multiple', 'answers' => ['26', '25', '27', '24'], 'correct_index' => 0],
                ['text' => 'Quelle est la couleur du ciel par temps clair?', 'type' => 'multiple', 'answers' => ['Bleu', 'Vert', 'Rouge', 'Jaune'], 'correct_index' => 0],
                ['text' => 'Le café est une boisson', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de saisons y a-t-il dans une année?', 'type' => 'multiple', 'answers' => ['4', '3', '5', '2'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Italie?', 'type' => 'multiple', 'answers' => ['Rome', 'Milan', 'Venise', 'Florence'], 'correct_index' => 0],
                ['text' => 'Internet a été inventé au 20ème siècle', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de minutes y a-t-il dans 2 heures?', 'type' => 'multiple', 'answers' => ['120', '60', '100', '140'], 'correct_index' => 0],
                ['text' => 'Quel est le plus grand pays du monde par superficie?', 'type' => 'multiple', 'answers' => ['Russie', 'Canada', 'Chine', 'USA'], 'correct_index' => 0],
                ['text' => 'L\'or est un métal précieux', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de côtés a un triangle?', 'type' => 'multiple', 'answers' => ['3', '4', '5', '6'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Espagne?', 'type' => 'multiple', 'answers' => ['Madrid', 'Barcelone', 'Séville', 'Valence'], 'correct_index' => 0],
                ['text' => 'Le français est une langue romane', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de degrés y a-t-il dans un cercle complet?', 'type' => 'multiple', 'answers' => ['360', '180', '90', '270'], 'correct_index' => 0],
                ['text' => 'Quelle est la monnaie de l\'Union Européenne?', 'type' => 'multiple', 'answers' => ['Euro', 'Dollar', 'Livre', 'Franc'], 'correct_index' => 0],
                ['text' => 'Le piano est un instrument de musique', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de joueurs y a-t-il dans une équipe de basketball?', 'type' => 'multiple', 'answers' => ['5', '6', '7', '11'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Allemagne?', 'type' => 'multiple', 'answers' => ['Berlin', 'Munich', 'Hambourg', 'Francfort'], 'correct_index' => 0],
                ['text' => 'Les humains ont besoin d\'oxygène pour vivre', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de zéros y a-t-il dans un million?', 'type' => 'multiple', 'answers' => ['6', '5', '7', '8'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale du Royaume-Uni?', 'type' => 'multiple', 'answers' => ['Londres', 'Manchester', 'Liverpool', 'Birmingham'], 'correct_index' => 0],
                ['text' => 'Le chocolat vient du cacao', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de mois y a-t-il dans une année?', 'type' => 'multiple', 'answers' => ['12', '10', '11', '13'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de la Chine?', 'type' => 'multiple', 'answers' => ['Pékin', 'Shanghai', 'Hong Kong', 'Canton'], 'correct_index' => 0],
                ['text' => 'Le diamant est le minéral le plus dur', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de pattes a un insecte?', 'type' => 'multiple', 'answers' => ['6', '8', '4', '10'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale du Portugal?', 'type' => 'multiple', 'answers' => ['Lisbonne', 'Porto', 'Faro', 'Braga'], 'correct_index' => 0],
                ['text' => 'La photosynthèse produit de l\'oxygène', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de cordes a une guitare classique?', 'type' => 'multiple', 'answers' => ['6', '4', '5', '7'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de la Russie?', 'type' => 'multiple', 'answers' => ['Moscou', 'Saint-Pétersbourg', 'Kiev', 'Minsk'], 'correct_index' => 0],
                ['text' => 'Le papier est fabriqué à partir du bois', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de faces a un cube?', 'type' => 'multiple', 'answers' => ['6', '4', '8', '12'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale des États-Unis?', 'type' => 'multiple', 'answers' => ['Washington D.C.', 'New York', 'Los Angeles', 'Chicago'], 'correct_index' => 0],
                ['text' => 'L\'électricité peut être dangereuse', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de couleurs y a-t-il dans un arc-en-ciel?', 'type' => 'multiple', 'answers' => ['7', '5', '6', '8'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Inde?', 'type' => 'multiple', 'answers' => ['New Delhi', 'Mumbai', 'Bangalore', 'Calcutta'], 'correct_index' => 0],
            ],
            'geographie' => [
                ['text' => 'Quel est le plus grand océan du monde?', 'type' => 'multiple', 'answers' => ['Pacifique', 'Atlantique', 'Indien', 'Arctique'], 'correct_index' => 0],
                ['text' => 'Le Mont Everest est le plus haut sommet du monde', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale du Canada?', 'type' => 'multiple', 'answers' => ['Ottawa', 'Toronto', 'Montréal', 'Vancouver'], 'correct_index' => 0],
                ['text' => 'Quel est le plus long fleuve du monde?', 'type' => 'multiple', 'answers' => ['Nil', 'Amazone', 'Yangtsé', 'Mississippi'], 'correct_index' => 0],
                ['text' => 'L\'Islande est en Europe', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel désert est le plus grand au monde?', 'type' => 'multiple', 'answers' => ['Sahara', 'Gobi', 'Kalahari', 'Atacama'], 'correct_index' => 0],
                ['text' => 'Combien de pays partagent une frontière avec la France?', 'type' => 'multiple', 'answers' => ['8', '6', '7', '5'], 'correct_index' => 0],
                ['text' => 'Le Brésil est en Amérique du Sud', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale du Brésil?', 'type' => 'multiple', 'answers' => ['Brasília', 'Rio de Janeiro', 'São Paulo', 'Salvador'], 'correct_index' => 0],
                ['text' => 'Le Groenland appartient au Danemark', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays a le plus de fuseaux horaires?', 'type' => 'multiple', 'answers' => ['France', 'Russie', 'USA', 'Chine'], 'correct_index' => 0],
                ['text' => 'Dans quel pays se trouve le Machu Picchu?', 'type' => 'multiple', 'answers' => ['Pérou', 'Bolivie', 'Équateur', 'Chili'], 'correct_index' => 0],
                ['text' => 'La mer Morte est le point le plus bas de la Terre', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de la Nouvelle-Zélande?', 'type' => 'multiple', 'answers' => ['Wellington', 'Auckland', 'Christchurch', 'Hamilton'], 'correct_index' => 0],
                ['text' => 'Combien de pays composent le Royaume-Uni?', 'type' => 'multiple', 'answers' => ['4', '3', '5', '2'], 'correct_index' => 0],
                ['text' => 'L\'Équateur traverse l\'Afrique', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle chaîne de montagnes sépare l\'Europe de l\'Asie?', 'type' => 'multiple', 'answers' => ['Oural', 'Alpes', 'Caucase', 'Carpates'], 'correct_index' => 0],
                ['text' => 'Quel est le plus petit continent?', 'type' => 'multiple', 'answers' => ['Océanie', 'Europe', 'Antarctique', 'Amérique du Sud'], 'correct_index' => 0],
                ['text' => 'Le Canal de Suez relie la Méditerranée à la mer Rouge', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Dans quel pays se trouve le lac Baïkal?', 'type' => 'multiple', 'answers' => ['Russie', 'Mongolie', 'Kazakhstan', 'Chine'], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de la Suisse?', 'type' => 'multiple', 'answers' => ['Berne', 'Zurich', 'Genève', 'Lausanne'], 'correct_index' => 0],
                ['text' => 'La Grande Barrière de Corail est en Australie', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays a la plus longue côte au monde?', 'type' => 'multiple', 'answers' => ['Canada', 'Australie', 'Russie', 'Indonésie'], 'correct_index' => 0],
                ['text' => 'Combien d\'États composent les États-Unis?', 'type' => 'multiple', 'answers' => ['50', '48', '52', '51'], 'correct_index' => 0],
                ['text' => 'Le Danube traverse Paris', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de la Norvège?', 'type' => 'multiple', 'answers' => ['Oslo', 'Stockholm', 'Copenhague', 'Helsinki'], 'correct_index' => 0],
                ['text' => 'Dans quel pays se trouve Angkor Vat?', 'type' => 'multiple', 'answers' => ['Cambodge', 'Thaïlande', 'Vietnam', 'Laos'], 'correct_index' => 0],
                ['text' => 'L\'Islande possède des volcans actifs', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle ville est surnommée "la Ville Éternelle"?', 'type' => 'multiple', 'answers' => ['Rome', 'Athènes', 'Jérusalem', 'Istanbul'], 'correct_index' => 0],
                ['text' => 'Quel détroit sépare l\'Espagne du Maroc?', 'type' => 'multiple', 'answers' => ['Gibraltar', 'Bosphore', 'Messine', 'Béring'], 'correct_index' => 0],
                ['text' => 'Le mont Kilimandjaro est en Tanzanie', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Égypte?', 'type' => 'multiple', 'answers' => ['Le Caire', 'Alexandrie', 'Louxor', 'Gizeh'], 'correct_index' => 0],
                ['text' => 'Combien de pays bordent la mer Méditerranée?', 'type' => 'multiple', 'answers' => ['22', '15', '18', '25'], 'correct_index' => 0],
                ['text' => 'Les chutes du Niagara sont entre le Canada et les USA', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle île est la plus grande du monde?', 'type' => 'multiple', 'answers' => ['Groenland', 'Nouvelle-Guinée', 'Bornéo', 'Madagascar'], 'correct_index' => 0],
                ['text' => 'Dans quel pays se trouve la ville de Tombouctou?', 'type' => 'multiple', 'answers' => ['Mali', 'Niger', 'Mauritanie', 'Sénégal'], 'correct_index' => 0],
                ['text' => 'Le Taj Mahal est en Inde', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de la Turquie?', 'type' => 'multiple', 'answers' => ['Ankara', 'Istanbul', 'Izmir', 'Bursa'], 'correct_index' => 0],
                ['text' => 'Quel océan borde la côte ouest des États-Unis?', 'type' => 'multiple', 'answers' => ['Pacifique', 'Atlantique', 'Indien', 'Arctique'], 'correct_index' => 0],
                ['text' => 'Le Sahara est plus grand que l\'Australie', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Autriche?', 'type' => 'multiple', 'answers' => ['Vienne', 'Salzbourg', 'Graz', 'Innsbruck'], 'correct_index' => 0],
                ['text' => 'Dans quel pays se trouve le désert d\'Atacama?', 'type' => 'multiple', 'answers' => ['Chili', 'Pérou', 'Argentine', 'Bolivie'], 'correct_index' => 0],
                ['text' => 'La Finlande partage une frontière avec la Russie', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de l\'Argentine?', 'type' => 'multiple', 'answers' => ['Buenos Aires', 'Córdoba', 'Rosario', 'Mendoza'], 'correct_index' => 0],
                ['text' => 'Combien de pays composent l\'Afrique du Nord?', 'type' => 'multiple', 'answers' => ['7', '5', '8', '6'], 'correct_index' => 0],
                ['text' => 'Le fleuve Congo est en Afrique', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de la Thaïlande?', 'type' => 'multiple', 'answers' => ['Bangkok', 'Phuket', 'Chiang Mai', 'Pattaya'], 'correct_index' => 0],
                ['text' => 'Dans quel pays se trouve la région de Transylvanie?', 'type' => 'multiple', 'answers' => ['Roumanie', 'Hongrie', 'Bulgarie', 'Serbie'], 'correct_index' => 0],
                ['text' => 'Madagascar est une île', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la capitale de la Suède?', 'type' => 'multiple', 'answers' => ['Stockholm', 'Oslo', 'Copenhague', 'Helsinki'], 'correct_index' => 0],
            ],
            'histoire' => [
                ['text' => 'En quelle année a eu lieu la Révolution française?', 'type' => 'multiple', 'answers' => ['1789', '1776', '1804', '1815'], 'correct_index' => 0],
                ['text' => 'Napoléon Bonaparte était français', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pharaon a construit la Grande Pyramide?', 'type' => 'multiple', 'answers' => ['Khéops', 'Ramsès II', 'Toutânkhamon', 'Cléopâtre'], 'correct_index' => 0],
                ['text' => 'En quelle année s\'est terminée la Seconde Guerre mondiale?', 'type' => 'multiple', 'answers' => ['1945', '1944', '1946', '1943'], 'correct_index' => 0],
                ['text' => 'Jules César était un empereur romain', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel événement a déclenché la Première Guerre mondiale?', 'type' => 'multiple', 'answers' => ['Assassinat de l\'archiduc François-Ferdinand', 'Invasion de la Pologne', 'Traité de Versailles', 'Révolution russe'], 'correct_index' => 0],
                ['text' => 'En quelle année Christophe Colomb a-t-il découvert l\'Amérique?', 'type' => 'multiple', 'answers' => ['1492', '1488', '1500', '1482'], 'correct_index' => 0],
                ['text' => 'La guerre de Cent Ans a duré 116 ans', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui était le premier empereur de Rome?', 'type' => 'multiple', 'answers' => ['Auguste', 'Néron', 'Caligula', 'César'], 'correct_index' => 0],
                ['text' => 'En quelle année le mur de Berlin est-il tombé?', 'type' => 'multiple', 'answers' => ['1989', '1990', '1991', '1988'], 'correct_index' => 0],
                ['text' => 'Jeanne d\'Arc était française', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui a inventé l\'imprimerie?', 'type' => 'multiple', 'answers' => ['Gutenberg', 'Da Vinci', 'Galilée', 'Copernic'], 'correct_index' => 0],
                ['text' => 'En quelle année a été signée la Déclaration d\'Indépendance américaine?', 'type' => 'multiple', 'answers' => ['1776', '1789', '1770', '1783'], 'correct_index' => 0],
                ['text' => 'Louis XIV était surnommé le Roi-Soleil', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle civilisation a construit le Machu Picchu?', 'type' => 'multiple', 'answers' => ['Incas', 'Aztèques', 'Mayas', 'Olmèques'], 'correct_index' => 0],
                ['text' => 'En quelle année a commencé la Première Guerre mondiale?', 'type' => 'multiple', 'answers' => ['1914', '1912', '1916', '1918'], 'correct_index' => 0],
                ['text' => 'Cléopâtre était la dernière reine d\'Égypte', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui a peint la Joconde?', 'type' => 'multiple', 'answers' => ['Léonard de Vinci', 'Michel-Ange', 'Raphaël', 'Botticelli'], 'correct_index' => 0],
                ['text' => 'En quelle année l\'homme a-t-il marché sur la Lune?', 'type' => 'multiple', 'answers' => ['1969', '1968', '1970', '1967'], 'correct_index' => 0],
                ['text' => 'La Renaissance a commencé en Italie', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui était le premier président des États-Unis?', 'type' => 'multiple', 'answers' => ['George Washington', 'Thomas Jefferson', 'Abraham Lincoln', 'John Adams'], 'correct_index' => 0],
                ['text' => 'En quelle année a eu lieu la Révolution russe?', 'type' => 'multiple', 'answers' => ['1917', '1918', '1916', '1920'], 'correct_index' => 0],
                ['text' => 'Le Titanic a coulé en 1912', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel empire a été dirigé par Gengis Khan?', 'type' => 'multiple', 'answers' => ['Mongol', 'Ottoman', 'Perse', 'Romain'], 'correct_index' => 0],
                ['text' => 'En quelle année la France a-t-elle aboli l\'esclavage définitivement?', 'type' => 'multiple', 'answers' => ['1848', '1789', '1794', '1815'], 'correct_index' => 0],
                ['text' => 'Marco Polo était un explorateur vénitien', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui a unifié l\'Allemagne au 19ème siècle?', 'type' => 'multiple', 'answers' => ['Bismarck', 'Guillaume Ier', 'Frédéric II', 'Metternich'], 'correct_index' => 0],
                ['text' => 'En quelle année l\'URSS s\'est-elle effondrée?', 'type' => 'multiple', 'answers' => ['1991', '1989', '1990', '1992'], 'correct_index' => 0],
                ['text' => 'Alexandre le Grand était macédonien', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle bataille a marqué la fin de Napoléon?', 'type' => 'multiple', 'answers' => ['Waterloo', 'Austerlitz', 'Leipzig', 'Iéna'], 'correct_index' => 0],
                ['text' => 'En quelle année a été construite la muraille de Berlin?', 'type' => 'multiple', 'answers' => ['1961', '1959', '1963', '1945'], 'correct_index' => 0],
                ['text' => 'Gandhi a été assassiné en 1948', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui a découvert la pénicilline?', 'type' => 'multiple', 'answers' => ['Fleming', 'Pasteur', 'Koch', 'Jenner'], 'correct_index' => 0],
                ['text' => 'En quelle année l\'Inde a-t-elle obtenu son indépendance?', 'type' => 'multiple', 'answers' => ['1947', '1945', '1950', '1946'], 'correct_index' => 0],
                ['text' => 'Charlemagne a été couronné empereur en l\'an 800', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui était le pharaon le plus célèbre pour sa tombe?', 'type' => 'multiple', 'answers' => ['Toutânkhamon', 'Ramsès II', 'Khéops', 'Akhenaton'], 'correct_index' => 0],
                ['text' => 'En quelle année la guerre de Sécession américaine a-t-elle commencé?', 'type' => 'multiple', 'answers' => ['1861', '1860', '1865', '1859'], 'correct_index' => 0],
                ['text' => 'La Bastille a été prise le 14 juillet 1789', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui a écrit "Le Prince"?', 'type' => 'multiple', 'answers' => ['Machiavel', 'Dante', 'Pétrarque', 'Boccace'], 'correct_index' => 0],
                ['text' => 'En quelle année le Canada est-il devenu indépendant?', 'type' => 'multiple', 'answers' => ['1867', '1776', '1900', '1840'], 'correct_index' => 0],
                ['text' => 'Nelson Mandela a passé 27 ans en prison', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel empire a dominé la Méditerranée pendant l\'Antiquité?', 'type' => 'multiple', 'answers' => ['Romain', 'Grec', 'Perse', 'Égyptien'], 'correct_index' => 0],
                ['text' => 'En quelle année a été signée la Magna Carta?', 'type' => 'multiple', 'answers' => ['1215', '1066', '1300', '1400'], 'correct_index' => 0],
                ['text' => 'Vasco de Gama a découvert la route des Indes', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Qui a fondé l\'Empire ottoman?', 'type' => 'multiple', 'answers' => ['Osman Ier', 'Soliman', 'Mehmed II', 'Selim Ier'], 'correct_index' => 0],
                ['text' => 'En quelle année a eu lieu la bataille de Verdun?', 'type' => 'multiple', 'answers' => ['1916', '1914', '1917', '1918'], 'correct_index' => 0],
                ['text' => 'Winston Churchill était britannique', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel traité a mis fin à la Première Guerre mondiale?', 'type' => 'multiple', 'answers' => ['Traité de Versailles', 'Traité de Paris', 'Traité de Rome', 'Traité de Tordesillas'], 'correct_index' => 0],
                ['text' => 'La guerre froide a opposé les USA et l\'URSS', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle année a marqué la fin de l\'Empire romain d\'Occident?', 'type' => 'multiple', 'answers' => ['476', '410', '500', '395'], 'correct_index' => 0],
            ],
            'sport' => [
                ['text' => 'Combien de joueurs y a-t-il dans une équipe de football?', 'type' => 'multiple', 'answers' => ['11', '10', '9', '12'], 'correct_index' => 0],
                ['text' => 'Le tennis se joue avec une balle', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays a remporté la Coupe du Monde 2018?', 'type' => 'multiple', 'answers' => ['France', 'Brésil', 'Allemagne', 'Argentine'], 'correct_index' => 0],
                ['text' => 'Combien de sets faut-il gagner au tennis pour gagner un match masculin en Grand Chelem?', 'type' => 'multiple', 'answers' => ['3', '2', '4', '5'], 'correct_index' => 0],
                ['text' => 'Le basketball a été inventé aux États-Unis', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de joueurs y a-t-il dans une équipe de rugby?', 'type' => 'multiple', 'answers' => ['15', '11', '13', '12'], 'correct_index' => 0],
                ['text' => 'Combien de points vaut un essai au rugby?', 'type' => 'multiple', 'answers' => ['5', '3', '7', '4'], 'correct_index' => 0],
                ['text' => 'Le Tour de France est une course cycliste', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la distance d\'un marathon?', 'type' => 'multiple', 'answers' => ['42,195 km', '40 km', '45 km', '50 km'], 'correct_index' => 0],
                ['text' => 'Combien de joueurs composent une équipe de volleyball?', 'type' => 'multiple', 'answers' => ['6', '5', '7', '8'], 'correct_index' => 0],
                ['text' => 'Les Jeux Olympiques ont lieu tous les 4 ans', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel sport pratique Rafael Nadal?', 'type' => 'multiple', 'answers' => ['Tennis', 'Football', 'Basketball', 'Golf'], 'correct_index' => 0],
                ['text' => 'Combien de rounds y a-t-il dans un match de boxe professionnel?', 'type' => 'multiple', 'answers' => ['12', '10', '15', '8'], 'correct_index' => 0],
                ['text' => 'Le cricket est originaire d\'Angleterre', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays a remporté le plus de Coupes du Monde de football?', 'type' => 'multiple', 'answers' => ['Brésil', 'Allemagne', 'Italie', 'Argentine'], 'correct_index' => 0],
                ['text' => 'Combien de points vaut un panier à 3 points au basketball?', 'type' => 'multiple', 'answers' => ['3', '2', '4', '5'], 'correct_index' => 0],
                ['text' => 'Le hockey sur glace se joue avec une rondelle', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la hauteur d\'un filet de tennis?', 'type' => 'multiple', 'answers' => ['0,914 m', '1 m', '0,85 m', '1,2 m'], 'correct_index' => 0],
                ['text' => 'Combien de manches y a-t-il au baseball?', 'type' => 'multiple', 'answers' => ['9', '7', '10', '12'], 'correct_index' => 0],
                ['text' => 'Usain Bolt est jamaïcain', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel sport pratique Tiger Woods?', 'type' => 'multiple', 'answers' => ['Golf', 'Tennis', 'Baseball', 'Cricket'], 'correct_index' => 0],
                ['text' => 'Combien de temps dure un match de football?', 'type' => 'multiple', 'answers' => ['90 minutes', '80 minutes', '100 minutes', '120 minutes'], 'correct_index' => 0],
                ['text' => 'La natation synchronisée est un sport olympique', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays a inventé le judo?', 'type' => 'multiple', 'answers' => ['Japon', 'Chine', 'Corée', 'Thaïlande'], 'correct_index' => 0],
                ['text' => 'Combien de joueurs y a-t-il sur le terrain au handball?', 'type' => 'multiple', 'answers' => ['7', '6', '8', '9'], 'correct_index' => 0],
                ['text' => 'Le golf se joue avec une balle blanche', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle ville a accueilli les JO de 2016?', 'type' => 'multiple', 'answers' => ['Rio de Janeiro', 'Tokyo', 'Londres', 'Pékin'], 'correct_index' => 0],
                ['text' => 'Combien de périodes y a-t-il au hockey sur glace?', 'type' => 'multiple', 'answers' => ['3', '2', '4', '5'], 'correct_index' => 0],
                ['text' => 'Michael Jordan a joué au basketball', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel sport se joue sur un court?', 'type' => 'multiple', 'answers' => ['Tennis', 'Football', 'Rugby', 'Cricket'], 'correct_index' => 0],
                ['text' => 'Combien de joueurs y a-t-il dans une équipe de water-polo?', 'type' => 'multiple', 'answers' => ['7', '6', '8', '9'], 'correct_index' => 0],
                ['text' => 'Le sumo est un sport japonais', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la couleur de la ceinture la plus élevée au judo?', 'type' => 'multiple', 'answers' => ['Rouge', 'Noire', 'Blanche', 'Marron'], 'correct_index' => 0],
                ['text' => 'Combien de grands chelems y a-t-il au tennis?', 'type' => 'multiple', 'answers' => ['4', '3', '5', '6'], 'correct_index' => 0],
                ['text' => 'Le badminton utilise un volant', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays a gagné l\'Euro 2020?', 'type' => 'multiple', 'answers' => ['Italie', 'Angleterre', 'France', 'Espagne'], 'correct_index' => 0],
                ['text' => 'Combien de joueurs composent une équipe de cricket?', 'type' => 'multiple', 'answers' => ['11', '10', '12', '9'], 'correct_index' => 0],
                ['text' => 'La Formule 1 est un sport automobile', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la longueur d\'une piscine olympique?', 'type' => 'multiple', 'answers' => ['50 mètres', '25 mètres', '100 mètres', '75 mètres'], 'correct_index' => 0],
                ['text' => 'Combien de trous y a-t-il sur un parcours de golf?', 'type' => 'multiple', 'answers' => ['18', '16', '20', '15'], 'correct_index' => 0],
                ['text' => 'Le pentathlon moderne comprend 5 épreuves', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel sport pratique Serena Williams?', 'type' => 'multiple', 'answers' => ['Tennis', 'Basketball', 'Volleyball', 'Badminton'], 'correct_index' => 0],
                ['text' => 'Combien de disciplines y a-t-il au décathlon?', 'type' => 'multiple', 'answers' => ['10', '8', '12', '9'], 'correct_index' => 0],
                ['text' => 'Le Ballon d\'Or récompense le meilleur footballeur', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Dans quel pays se déroule Roland-Garros?', 'type' => 'multiple', 'answers' => ['France', 'Angleterre', 'USA', 'Australie'], 'correct_index' => 0],
                ['text' => 'Combien de cartons rouges entraînent une expulsion au football?', 'type' => 'multiple', 'answers' => ['1', '2', '3', '0'], 'correct_index' => 0],
                ['text' => 'L\'escrime se pratique avec des épées', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel sport pratique LeBron James?', 'type' => 'multiple', 'answers' => ['Basketball', 'Baseball', 'Football', 'Hockey'], 'correct_index' => 0],
                ['text' => 'Le triathlon comprend natation, vélo et course', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de minutes dure une mi-temps au football?', 'type' => 'multiple', 'answers' => ['45 minutes', '40 minutes', '30 minutes', '50 minutes'], 'correct_index' => 0],
            ],
            'sciences' => [
                ['text' => 'Quel est le symbole chimique de l\'or?', 'type' => 'multiple', 'answers' => ['Au', 'Ag', 'Fe', 'Cu'], 'correct_index' => 0],
                ['text' => 'L\'eau bout à 100°C', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de planètes y a-t-il dans le système solaire?', 'type' => 'multiple', 'answers' => ['8', '9', '7', '10'], 'correct_index' => 0],
                ['text' => 'Quelle est la vitesse de la lumière?', 'type' => 'multiple', 'answers' => ['300 000 km/s', '150 000 km/s', '450 000 km/s', '200 000 km/s'], 'correct_index' => 0],
                ['text' => 'L\'ADN contient l\'information génétique', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel gaz respirons-nous principalement?', 'type' => 'multiple', 'answers' => ['Azote', 'Oxygène', 'CO2', 'Hydrogène'], 'correct_index' => 0],
                ['text' => 'Quel est le symbole chimique du fer?', 'type' => 'multiple', 'answers' => ['Fe', 'Fr', 'F', 'Fi'], 'correct_index' => 0],
                ['text' => 'La Terre tourne autour du Soleil', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de chromosomes a l\'être humain?', 'type' => 'multiple', 'answers' => ['46', '48', '44', '50'], 'correct_index' => 0],
                ['text' => 'Quelle est la formule chimique du sel de table?', 'type' => 'multiple', 'answers' => ['NaCl', 'KCl', 'CaCl2', 'MgCl'], 'correct_index' => 0],
                ['text' => 'Les plantes produisent de l\'oxygène par photosynthèse', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle planète est la plus proche du Soleil?', 'type' => 'multiple', 'answers' => ['Mercure', 'Vénus', 'Mars', 'Terre'], 'correct_index' => 0],
                ['text' => 'Combien d\'os a le corps humain adulte?', 'type' => 'multiple', 'answers' => ['206', '200', '250', '180'], 'correct_index' => 0],
                ['text' => 'Einstein a développé la théorie de la relativité', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est le symbole chimique de l\'argent?', 'type' => 'multiple', 'answers' => ['Ag', 'Au', 'Al', 'Ar'], 'correct_index' => 0],
                ['text' => 'Quelle est la plus grande planète du système solaire?', 'type' => 'multiple', 'answers' => ['Jupiter', 'Saturne', 'Neptune', 'Uranus'], 'correct_index' => 0],
                ['text' => 'Le son se déplace plus vite dans l\'eau que dans l\'air', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de dents a un adulte?', 'type' => 'multiple', 'answers' => ['32', '28', '30', '36'], 'correct_index' => 0],
                ['text' => 'Quel gaz est le plus abondant dans l\'atmosphère?', 'type' => 'multiple', 'answers' => ['Azote', 'Oxygène', 'CO2', 'Argon'], 'correct_index' => 0],
                ['text' => 'Les atomes sont composés de protons, neutrons et électrons', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la température du corps humain normale?', 'type' => 'multiple', 'answers' => ['37°C', '36°C', '38°C', '35°C'], 'correct_index' => 0],
                ['text' => 'Qui a découvert la gravitation?', 'type' => 'multiple', 'answers' => ['Newton', 'Einstein', 'Galilée', 'Copernic'], 'correct_index' => 0],
                ['text' => 'La glace flotte sur l\'eau', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de cœurs a une pieuvre?', 'type' => 'multiple', 'answers' => ['3', '2', '4', '1'], 'correct_index' => 0],
                ['text' => 'Quel est le plus grand organe du corps humain?', 'type' => 'multiple', 'answers' => ['La peau', 'Le foie', 'Le cerveau', 'Le cœur'], 'correct_index' => 0],
                ['text' => 'Les antibiotiques tuent les virus', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quelle vitamine est produite par le soleil?', 'type' => 'multiple', 'answers' => ['Vitamine D', 'Vitamine C', 'Vitamine A', 'Vitamine B12'], 'correct_index' => 0],
                ['text' => 'Combien de chambres a le cœur humain?', 'type' => 'multiple', 'answers' => ['4', '2', '3', '6'], 'correct_index' => 0],
                ['text' => 'Le diamant est composé de carbone', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est le pH de l\'eau pure?', 'type' => 'multiple', 'answers' => ['7', '6', '8', '5'], 'correct_index' => 0],
                ['text' => 'Quelle particule a une charge négative?', 'type' => 'multiple', 'answers' => ['Électron', 'Proton', 'Neutron', 'Photon'], 'correct_index' => 0],
                ['text' => 'Les champignons sont des plantes', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Combien de vertèbres cervicales a l\'homme?', 'type' => 'multiple', 'answers' => ['7', '5', '9', '12'], 'correct_index' => 0],
                ['text' => 'Quel est le symbole chimique du cuivre?', 'type' => 'multiple', 'answers' => ['Cu', 'Co', 'Cr', 'C'], 'correct_index' => 0],
                ['text' => 'La Lune produit sa propre lumière', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quelle est la plus petite unité de vie?', 'type' => 'multiple', 'answers' => ['La cellule', 'L\'atome', 'La molécule', 'Le tissu'], 'correct_index' => 0],
                ['text' => 'Combien de paires de côtes a l\'être humain?', 'type' => 'multiple', 'answers' => ['12', '10', '14', '16'], 'correct_index' => 0],
                ['text' => 'L\'eau est un composé chimique', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel organe produit l\'insuline?', 'type' => 'multiple', 'answers' => ['Pancréas', 'Foie', 'Estomac', 'Rein'], 'correct_index' => 0],
                ['text' => 'Quelle est la durée d\'un jour sur Mars?', 'type' => 'multiple', 'answers' => ['24h 37min', '24h', '20h', '30h'], 'correct_index' => 0],
                ['text' => 'Le mercure est liquide à température ambiante', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de sens a l\'être humain?', 'type' => 'multiple', 'answers' => ['5', '4', '6', '7'], 'correct_index' => 0],
                ['text' => 'Quel est le symbole chimique de l\'hélium?', 'type' => 'multiple', 'answers' => ['He', 'H', 'Hl', 'Ho'], 'correct_index' => 0],
                ['text' => 'Les dinosaures et les humains ont coexisté', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quelle partie du cerveau contrôle l\'équilibre?', 'type' => 'multiple', 'answers' => ['Cervelet', 'Cortex', 'Hippocampe', 'Thalamus'], 'correct_index' => 0],
                ['text' => 'Combien de litres de sang a un adulte moyen?', 'type' => 'multiple', 'answers' => ['5', '3', '7', '10'], 'correct_index' => 0],
                ['text' => 'Les requins ont des os', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel est le métal le plus conducteur?', 'type' => 'multiple', 'answers' => ['Argent', 'Cuivre', 'Or', 'Aluminium'], 'correct_index' => 0],
                ['text' => 'La théorie du Big Bang explique l\'origine de l\'univers', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de molécules d\'eau y a-t-il dans H2O?', 'type' => 'multiple', 'answers' => ['1', '2', '3', '0'], 'correct_index' => 0],
            ],
            'cuisine' => [
                ['text' => 'D\'où vient la pizza?', 'type' => 'multiple', 'answers' => ['Italie', 'France', 'Grèce', 'Espagne'], 'correct_index' => 0],
                ['text' => 'Le champagne vient de France', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays est le plus grand producteur de café?', 'type' => 'multiple', 'answers' => ['Brésil', 'Colombie', 'Vietnam', 'Éthiopie'], 'correct_index' => 0],
                ['text' => 'Combien d\'épices y a-t-il traditionnellement dans le mélange "cinq épices chinoises"?', 'type' => 'multiple', 'answers' => ['5', '4', '6', '7'], 'correct_index' => 0],
                ['text' => 'Quel est l\'ingrédient principal du guacamole?', 'type' => 'multiple', 'answers' => ['Avocat', 'Tomate', 'Piment', 'Citron'], 'correct_index' => 0],
                ['text' => 'Le sushi est un plat japonais', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle est la base de la sauce béchamel?', 'type' => 'multiple', 'answers' => ['Lait', 'Crème', 'Bouillon', 'Vin'], 'correct_index' => 0],
                ['text' => 'D\'où vient le croissant?', 'type' => 'multiple', 'answers' => ['Autriche', 'France', 'Italie', 'Suisse'], 'correct_index' => 0],
                ['text' => 'Le parmesan est un fromage italien', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel pays a inventé les pâtes?', 'type' => 'multiple', 'answers' => ['Chine', 'Italie', 'Grèce', 'France'], 'correct_index' => 0],
                ['text' => 'Combien de calories environ dans un œuf?', 'type' => 'multiple', 'answers' => ['70', '50', '100', '120'], 'correct_index' => 0],
                ['text' => 'Le wasabi est une sauce piquante', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est l\'ingrédient principal du houmous?', 'type' => 'multiple', 'answers' => ['Pois chiches', 'Lentilles', 'Haricots', 'Fèves'], 'correct_index' => 0],
                ['text' => 'Quelle est l\'origine du couscous?', 'type' => 'multiple', 'answers' => ['Afrique du Nord', 'Moyen-Orient', 'Asie', 'Europe'], 'correct_index' => 0],
                ['text' => 'Le tofu est fabriqué à partir de soja', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est le principal ingrédient de la paella?', 'type' => 'multiple', 'answers' => ['Riz', 'Pâtes', 'Semoule', 'Pommes de terre'], 'correct_index' => 0],
                ['text' => 'Combien de temps faut-il cuire un œuf mollet?', 'type' => 'multiple', 'answers' => ['5-6 minutes', '3 minutes', '8 minutes', '10 minutes'], 'correct_index' => 0],
                ['text' => 'Le caviar provient des œufs d\'esturgeon', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle épice donne la couleur jaune au curry?', 'type' => 'multiple', 'answers' => ['Curcuma', 'Safran', 'Paprika', 'Gingembre'], 'correct_index' => 0],
                ['text' => 'D\'où vient le kimchi?', 'type' => 'multiple', 'answers' => ['Corée', 'Japon', 'Chine', 'Vietnam'], 'correct_index' => 0],
                ['text' => 'Le chocolat noir contient plus de cacao que le chocolat au lait', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel fromage est utilisé traditionnellement dans la pizza Margherita?', 'type' => 'multiple', 'answers' => ['Mozzarella', 'Cheddar', 'Gruyère', 'Emmental'], 'correct_index' => 0],
                ['text' => 'Combien de variétés de riz existe-t-il environ?', 'type' => 'multiple', 'answers' => ['40 000', '1 000', '5 000', '100'], 'correct_index' => 0],
                ['text' => 'Le cognac est produit en France', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel légume est la base du ketchup?', 'type' => 'multiple', 'answers' => ['Tomate', 'Carotte', 'Betterave', 'Poivron'], 'correct_index' => 0],
                ['text' => 'D\'où vient le taboulé?', 'type' => 'multiple', 'answers' => ['Liban', 'Turquie', 'Grèce', 'Maroc'], 'correct_index' => 0],
                ['text' => 'La vanille est une orchidée', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel type de pâte est utilisé pour les cannelés?', 'type' => 'multiple', 'answers' => ['Pâte liquide', 'Pâte feuilletée', 'Pâte brisée', 'Pâte sablée'], 'correct_index' => 0],
                ['text' => 'Combien de temps doit reposer une pâte à crêpes?', 'type' => 'multiple', 'answers' => ['1 heure minimum', '30 minutes', '2 heures', 'Pas besoin'], 'correct_index' => 0],
                ['text' => 'Le thé vert et le thé noir viennent de la même plante', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel fruit est utilisé dans le guacamole?', 'type' => 'multiple', 'answers' => ['Avocat', 'Mangue', 'Papaye', 'Kiwi'], 'correct_index' => 0],
                ['text' => 'Quelle est la base du risotto?', 'type' => 'multiple', 'answers' => ['Riz arborio', 'Riz basmati', 'Riz jasmin', 'Riz sauvage'], 'correct_index' => 0],
                ['text' => 'Le foie gras est une spécialité française', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quelle viande est traditionnellement utilisée dans le bœuf bourguignon?', 'type' => 'multiple', 'answers' => ['Bœuf', 'Porc', 'Veau', 'Agneau'], 'correct_index' => 0],
                ['text' => 'D\'où vient le tajine?', 'type' => 'multiple', 'answers' => ['Maroc', 'Tunisie', 'Algérie', 'Égypte'], 'correct_index' => 0],
                ['text' => 'Le mascarpone est un fromage italien', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est l\'ingrédient principal du pesto?', 'type' => 'multiple', 'answers' => ['Basilic', 'Persil', 'Coriandre', 'Menthe'], 'correct_index' => 0],
                ['text' => 'Combien de couches a un mille-feuille traditionnel?', 'type' => 'multiple', 'answers' => ['3', '2', '4', '5'], 'correct_index' => 0],
                ['text' => 'Le vinaigre balsamique vient d\'Italie', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel poisson est utilisé dans les sushis?', 'type' => 'multiple', 'answers' => ['Thon', 'Cabillaud', 'Sardine', 'Maquereau'], 'correct_index' => 0],
                ['text' => 'Quelle est la base de la mayonnaise?', 'type' => 'multiple', 'answers' => ['Œuf et huile', 'Lait et beurre', 'Crème et citron', 'Yaourt et moutarde'], 'correct_index' => 0],
                ['text' => 'Le gorgonzola est un fromage à pâte persillée', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'D\'où vient le borscht?', 'type' => 'multiple', 'answers' => ['Ukraine', 'Pologne', 'Hongrie', 'Roumanie'], 'correct_index' => 0],
                ['text' => 'Quelle est la température idéale pour servir le champagne?', 'type' => 'multiple', 'answers' => ['8-10°C', '4-6°C', '12-14°C', '15-18°C'], 'correct_index' => 0],
                ['text' => 'Le safran est l\'épice la plus chère au monde', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel alcool est la base du mojito?', 'type' => 'multiple', 'answers' => ['Rhum', 'Vodka', 'Gin', 'Tequila'], 'correct_index' => 0],
                ['text' => 'Combien de temps faut-il pour faire du pain au levain?', 'type' => 'multiple', 'answers' => ['24 heures minimum', '4 heures', '8 heures', '2 heures'], 'correct_index' => 0],
                ['text' => 'Le camembert est un fromage normand', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel vin accompagne traditionnellement les huîtres?', 'type' => 'multiple', 'answers' => ['Vin blanc', 'Vin rouge', 'Rosé', 'Champagne'], 'correct_index' => 0],
                ['text' => 'Le roquefort est un fromage de brebis', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
            ],
            'faune' => [
                ['text' => 'Quel est le plus grand animal terrestre?', 'type' => 'multiple', 'answers' => ['Éléphant d\'Afrique', 'Girafe', 'Rhinocéros', 'Hippopotame'], 'correct_index' => 0],
                ['text' => 'Les dauphins sont des mammifères', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Combien de pattes a une araignée?', 'type' => 'multiple', 'answers' => ['8', '6', '10', '12'], 'correct_index' => 0],
                ['text' => 'Quel est l\'animal le plus rapide au monde?', 'type' => 'multiple', 'answers' => ['Guépard', 'Lion', 'Gazelle', 'Léopard'], 'correct_index' => 0],
                ['text' => 'Les pingouins vivent au Pôle Nord', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel est le plus grand animal marin?', 'type' => 'multiple', 'answers' => ['Baleine bleue', 'Requin baleine', 'Cachalot', 'Orque'], 'correct_index' => 0],
                ['text' => 'Les chauves-souris sont des oiseaux', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Combien de bosses a un chameau?', 'type' => 'multiple', 'answers' => ['2', '1', '3', '0'], 'correct_index' => 0],
                ['text' => 'Quel animal est connu pour sa mémoire exceptionnelle?', 'type' => 'multiple', 'answers' => ['Éléphant', 'Dauphin', 'Singe', 'Chien'], 'correct_index' => 0],
                ['text' => 'Les serpents ont des paupières', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel oiseau peut voler en arrière?', 'type' => 'multiple', 'answers' => ['Colibri', 'Aigle', 'Hirondelle', 'Faucon'], 'correct_index' => 0],
                ['text' => 'Combien de temps dort un koala par jour?', 'type' => 'multiple', 'answers' => ['20 heures', '12 heures', '16 heures', '8 heures'], 'correct_index' => 0],
                ['text' => 'Les crocodiles pleurent vraiment', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est le seul mammifère capable de voler?', 'type' => 'multiple', 'answers' => ['Chauve-souris', 'Écureuil volant', 'Poisson volant', 'Lémur volant'], 'correct_index' => 0],
                ['text' => 'Combien de cœurs a un poulpe?', 'type' => 'multiple', 'answers' => ['3', '1', '2', '4'], 'correct_index' => 0],
                ['text' => 'Les flamants roses naissent roses', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel animal a la langue la plus longue?', 'type' => 'multiple', 'answers' => ['Caméléon', 'Girafe', 'Fourmilier', 'Grenouille'], 'correct_index' => 0],
                ['text' => 'Combien de dents a un requin blanc?', 'type' => 'multiple', 'answers' => ['300', '100', '500', '50'], 'correct_index' => 0],
                ['text' => 'Les poissons rouges ont une mémoire de 3 secondes', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel animal peut survivre sans eau le plus longtemps?', 'type' => 'multiple', 'answers' => ['Chameau', 'Kangourou', 'Girafe', 'Rhinocéros'], 'correct_index' => 0],
                ['text' => 'Combien d\'estomacs a une vache?', 'type' => 'multiple', 'answers' => ['4', '2', '3', '1'], 'correct_index' => 0],
                ['text' => 'Les autruches peuvent voler', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel animal a les yeux les plus grands?', 'type' => 'multiple', 'answers' => ['Calmar géant', 'Baleine bleue', 'Éléphant', 'Aigle'], 'correct_index' => 0],
                ['text' => 'Combien de pattes a un crabe?', 'type' => 'multiple', 'answers' => ['10', '8', '6', '12'], 'correct_index' => 0],
                ['text' => 'Les tigres sont des animaux sociaux', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel est le plus grand félin du monde?', 'type' => 'multiple', 'answers' => ['Tigre', 'Lion', 'Jaguar', 'Léopard'], 'correct_index' => 0],
                ['text' => 'Combien de temps vit une tortue géante?', 'type' => 'multiple', 'answers' => ['100-150 ans', '50-70 ans', '200-300 ans', '30-40 ans'], 'correct_index' => 0],
                ['text' => 'Les ours polaires ont la peau noire', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel oiseau pond le plus gros œuf?', 'type' => 'multiple', 'answers' => ['Autruche', 'Émeu', 'Aigle', 'Albatros'], 'correct_index' => 0],
                ['text' => 'Combien de vertèbres a un cou de girafe?', 'type' => 'multiple', 'answers' => ['7', '12', '20', '5'], 'correct_index' => 0],
                ['text' => 'Les kangourous peuvent sauter en arrière', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel animal produit le lait le plus riche?', 'type' => 'multiple', 'answers' => ['Phoque', 'Vache', 'Chèvre', 'Chameau'], 'correct_index' => 0],
                ['text' => 'Combien de temps dort un paresseux par jour?', 'type' => 'multiple', 'answers' => ['15 heures', '8 heures', '20 heures', '10 heures'], 'correct_index' => 0],
                ['text' => 'Les éléphants ont peur des souris', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel animal a le QI le plus élevé après l\'homme?', 'type' => 'multiple', 'answers' => ['Dauphin', 'Chimpanzé', 'Éléphant', 'Corbeau'], 'correct_index' => 0],
                ['text' => 'Combien de fois par minute bat le cœur d\'un colibri?', 'type' => 'multiple', 'answers' => ['1200', '500', '200', '100'], 'correct_index' => 0],
                ['text' => 'Les hippopotames savent nager', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel animal a le venin le plus puissant?', 'type' => 'multiple', 'answers' => ['Méduse-boîte', 'Cobra', 'Scorpion', 'Araignée'], 'correct_index' => 0],
                ['text' => 'Combien de temps gestation d\'un éléphant?', 'type' => 'multiple', 'answers' => ['22 mois', '12 mois', '18 mois', '9 mois'], 'correct_index' => 0],
                ['text' => 'Les pandas sont herbivores', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel animal dort debout?', 'type' => 'multiple', 'answers' => ['Cheval', 'Vache', 'Mouton', 'Chèvre'], 'correct_index' => 0],
                ['text' => 'Combien de rayures a un zèbre?', 'type' => 'multiple', 'answers' => ['Variable selon l\'individu', 'Toujours 50', 'Toujours 100', 'Toujours 30'], 'correct_index' => 0],
                ['text' => 'Les gorilles sont carnivores', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel animal a la morsure la plus puissante?', 'type' => 'multiple', 'answers' => ['Crocodile marin', 'Requin blanc', 'Tigre', 'Ours'], 'correct_index' => 0],
                ['text' => 'Combien pèse un éléphant adulte?', 'type' => 'multiple', 'answers' => ['6 tonnes', '2 tonnes', '10 tonnes', '4 tonnes'], 'correct_index' => 0],
                ['text' => 'Les mouches ont des dents', 'type' => 'true_false', 'answers' => ['Faux', null, 'Vrai', null], 'correct_index' => 0],
                ['text' => 'Quel oiseau ne peut pas marcher?', 'type' => 'multiple', 'answers' => ['Martinet', 'Aigle', 'Moineau', 'Corbeau'], 'correct_index' => 0],
                ['text' => 'Combien de litres de lait produit une vache par jour?', 'type' => 'multiple', 'answers' => ['25 litres', '10 litres', '50 litres', '5 litres'], 'correct_index' => 0],
                ['text' => 'Les escargots ont des dents', 'type' => 'true_false', 'answers' => ['Vrai', null, 'Faux', null], 'correct_index' => 0],
                ['text' => 'Quel est le seul mammifère qui pond des œufs?', 'type' => 'multiple', 'answers' => ['Ornithorynque', 'Échidné', 'Taupe', 'Hérisson'], 'correct_index' => 0],
            ],
        ];

        return $baseQuestions[$theme] ?? $baseQuestions['general'];
    }

    public function checkAnswer($questionData, $answerIndex)
    {
        return $answerIndex == $questionData['correct_index'];
    }
    
    /**
     * Simule le comportement complet de l'adversaire IA
     * Retourne: ['buzzes' => bool, 'is_faster' => bool, 'is_correct' => bool, 'points' => int]
     */
    public function simulateOpponentBehavior($niveau, $questionData, $playerBuzzed = true)
    {
        // ÉTAPE 1: L'IA décide si elle buzz (Sans Réponse)
        $buzzChance = $this->getOpponentBuzzChance($niveau);
        $opponentBuzzes = (rand(1, 100) <= $buzzChance);
        
        // Si l'IA ne buzz pas, elle ne gagne ni ne perd de points
        if (!$opponentBuzzes) {
            return [
                'buzzes' => false,
                'is_faster' => false,
                'is_correct' => false,
                'points' => 0
            ];
        }
        
        // ÉTAPE 2: L'IA détermine sa vitesse (si elle est plus rapide que le joueur)
        $speedChance = $this->getOpponentSpeedChance($niveau);
        $isFaster = (rand(1, 100) <= $speedChance);
        
        // ÉTAPE 3: L'IA répond (Taux de Réussite)
        $successRate = $this->getOpponentSuccessRate($niveau);
        $isCorrect = (rand(1, 100) <= $successRate);
        
        // ÉTAPE 4: Calcul des points
        // 1er + correct = +2 pts
        // 2ème + correct = +1 pt
        // Buzz + incorrect = -2 pts
        $points = 0;
        if ($isCorrect) {
            $points = $isFaster ? 2 : 1; // 1er = 2 pts, 2ème = 1 pt
        } else {
            $points = -2; // Incorrect = -2 pts
        }
        
        return [
            'buzzes' => true,
            'is_faster' => $isFaster,
            'is_correct' => $isCorrect,
            'points' => $points
        ];
    }
    
    /**
     * Détermine la probabilité que l'IA buzz (Sans Réponse inversé)
     */
    private function getOpponentBuzzChance($niveau)
    {
        if ($niveau <= 20) {
            return 65 + ($niveau * 0.5); // 65-75% de chance de buzzer
        } elseif ($niveau <= 60) {
            return 75 + (($niveau - 20) * 0.25); // 75-85% de chance
        } elseif ($niveau <= 90) {
            return 85 + (($niveau - 60) * 0.33); // 85-95% de chance
        } else {
            return 95 + (($niveau - 90) * 0.5); // 95-100% de chance
        }
    }
    
    /**
     * Détermine la probabilité que l'IA soit plus rapide que le joueur
     */
    private function getOpponentSpeedChance($niveau)
    {
        if ($niveau <= 20) {
            return 20 + ($niveau * 0.75); // 20-35% de chance d'être plus rapide
        } elseif ($niveau <= 60) {
            return 35 + (($niveau - 20) * 0.625); // 35-60% de chance
        } elseif ($niveau <= 90) {
            return 60 + (($niveau - 60) * 0.833); // 60-85% de chance
        } else {
            return 85 + (($niveau - 90) * 0.5); // 85-90% de chance
        }
    }
    
    /**
     * Détermine le taux de réussite de l'IA
     */
    private function getOpponentSuccessRate($niveau)
    {
        if ($niveau <= 20) {
            return 60 + ($niveau * 0.5); // 60-70% de réussite
        } elseif ($niveau <= 60) {
            return 70 + (($niveau - 20) * 0.375); // 70-85% de réussite
        } elseif ($niveau <= 90) {
            return 85 + (($niveau - 60) * 0.333); // 85-95% de réussite
        } else {
            return 95 + (($niveau - 90) * 0.5); // 95-100% de réussite
        }
    }
    
    /**
     * Ancienne méthode pour compatibilité (deprecated)
     */
    public function simulateOpponentAnswer($niveau, $questionData)
    {
        $behavior = $this->simulateOpponentBehavior($niveau, $questionData);
        return $behavior['is_correct'];
    }
}
