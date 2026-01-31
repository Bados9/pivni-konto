<?php

namespace App\DataFixtures;

use App\Entity\Beer;
use App\Repository\BeerRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class BeerFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $beers = [
            // === VELKÉ PIVOVARY ===
            // Plzeňský Prazdroj (značky jako samostatné pivovary)
            ['name' => 'Pilsner Urquell', 'brewery' => 'Plzeňský Prazdroj', 'style' => 'Pilsner', 'abv' => 4.4],
            ['name' => 'Gambrinus Originál 10°', 'brewery' => 'Gambrinus', 'style' => 'Lager', 'abv' => 4.3],
            ['name' => 'Gambrinus Plná 12°', 'brewery' => 'Gambrinus', 'style' => 'Lager', 'abv' => 5.2],
            ['name' => 'Gambrinus Nepasterizovaná', 'brewery' => 'Gambrinus', 'style' => 'Lager', 'abv' => 4.8],
            ['name' => 'Radegast Rázná 12°', 'brewery' => 'Radegast', 'style' => 'Lager', 'abv' => 5.1],
            ['name' => 'Radegast Originál 10°', 'brewery' => 'Radegast', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Kozel 10°', 'brewery' => 'Kozel', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Kozel 11°', 'brewery' => 'Kozel', 'style' => 'Lager', 'abv' => 4.6],
            ['name' => 'Kozel 12°', 'brewery' => 'Kozel', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Kozel Černý', 'brewery' => 'Kozel', 'style' => 'Dark Lager', 'abv' => 3.8],
            ['name' => 'Birell Světlý', 'brewery' => 'Birell', 'style' => 'Non-alcoholic', 'abv' => 0.5],

            // Budějovický Budvar
            ['name' => 'Budvar Original', 'brewery' => 'Budějovický Budvar', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Budvar 33', 'brewery' => 'Budějovický Budvar', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Budvar Kroužkovaný', 'brewery' => 'Budějovický Budvar', 'style' => 'Lager', 'abv' => 4.7],
            ['name' => 'Budvar Reserve', 'brewery' => 'Budějovický Budvar', 'style' => 'Lager', 'abv' => 7.5],
            ['name' => 'Budvar Tmavý ležák', 'brewery' => 'Budějovický Budvar', 'style' => 'Dark Lager', 'abv' => 4.7],
            ['name' => 'Budvar Nealko', 'brewery' => 'Budějovický Budvar', 'style' => 'Non-alcoholic', 'abv' => 0.5],
            ['name' => 'Pardál Echt', 'brewery' => 'Budějovický Budvar', 'style' => 'Lager', 'abv' => 4.0],

            // Staropramen
            ['name' => 'Staropramen Ležák', 'brewery' => 'Staropramen', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Staropramen Premium', 'brewery' => 'Staropramen', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Staropramen Neflitrovaný', 'brewery' => 'Staropramen', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Staropramen Černý', 'brewery' => 'Staropramen', 'style' => 'Dark Lager', 'abv' => 4.4],
            ['name' => 'Ostravar Originál', 'brewery' => 'Staropramen', 'style' => 'Lager', 'abv' => 4.3],
            ['name' => 'Braník 10°', 'brewery' => 'Staropramen', 'style' => 'Lager', 'abv' => 4.1],

            // Heineken ČR
            ['name' => 'Krušovice 10°', 'brewery' => 'Krušovice', 'style' => 'Lager', 'abv' => 4.2],
            ['name' => 'Krušovice 12°', 'brewery' => 'Krušovice', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Krušovice Černé', 'brewery' => 'Krušovice', 'style' => 'Dark Lager', 'abv' => 3.8],
            ['name' => 'Zlatopramen 11°', 'brewery' => 'Zlatopramen', 'style' => 'Lager', 'abv' => 4.9],
            ['name' => 'Březňák 11°', 'brewery' => 'Březňák', 'style' => 'Lager', 'abv' => 4.8],
            ['name' => 'Starobrno Medium', 'brewery' => 'Starobrno', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Starobrno Ležák', 'brewery' => 'Starobrno', 'style' => 'Lager', 'abv' => 5.0],

            // === REGIONÁLNÍ PIVOVARY ===
            // Bernard
            ['name' => 'Bernard 10°', 'brewery' => 'Bernard', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Bernard 11°', 'brewery' => 'Bernard', 'style' => 'Lager', 'abv' => 4.5],
            ['name' => 'Bernard 12°', 'brewery' => 'Bernard', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Bernard Černý ležák', 'brewery' => 'Bernard', 'style' => 'Dark Lager', 'abv' => 5.1],
            ['name' => 'Bernard Jantarový ležák', 'brewery' => 'Bernard', 'style' => 'Amber Lager', 'abv' => 5.0],
            ['name' => 'Bernard Sváteční ležák', 'brewery' => 'Bernard', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Bernard Bohemian Ale', 'brewery' => 'Bernard', 'style' => 'Ale', 'abv' => 8.2],
            ['name' => 'Bernard Free', 'brewery' => 'Bernard', 'style' => 'Non-alcoholic', 'abv' => 0.5],

            // Svijany
            ['name' => 'Svijanský Máz', 'brewery' => 'Svijany', 'style' => 'Lager', 'abv' => 4.8],
            ['name' => 'Svijanská Kněžna', 'brewery' => 'Svijany', 'style' => 'Lager', 'abv' => 5.2],
            ['name' => 'Svijanský Rytíř', 'brewery' => 'Svijany', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Svijanský Baron', 'brewery' => 'Svijany', 'style' => 'Dark Lager', 'abv' => 4.8],
            ['name' => 'Svijany 450', 'brewery' => 'Svijany', 'style' => 'Lager', 'abv' => 4.5],

            // Primátor
            ['name' => 'Primátor Premium', 'brewery' => 'Primátor', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Primátor Exkluziv 16°', 'brewery' => 'Primátor', 'style' => 'Strong Lager', 'abv' => 7.5],
            ['name' => 'Primátor Double 24°', 'brewery' => 'Primátor', 'style' => 'Strong Lager', 'abv' => 10.5],
            ['name' => 'Primátor Stout', 'brewery' => 'Primátor', 'style' => 'Stout', 'abv' => 4.7],
            ['name' => 'Primátor Weizenbier', 'brewery' => 'Primátor', 'style' => 'Wheat', 'abv' => 4.8],
            ['name' => 'Primátor English Pale Ale', 'brewery' => 'Primátor', 'style' => 'Pale Ale', 'abv' => 5.0],

            // Další regionální
            ['name' => 'Rohozec Skalák', 'brewery' => 'Rohozec', 'style' => 'Lager', 'abv' => 4.7],
            ['name' => 'Rohozec Podskalák', 'brewery' => 'Rohozec', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Konrad 11°', 'brewery' => 'Konrad', 'style' => 'Lager', 'abv' => 4.6],
            ['name' => 'Konrad 14°', 'brewery' => 'Konrad', 'style' => 'Lager', 'abv' => 6.0],
            ['name' => 'Holba Šerák', 'brewery' => 'Holba', 'style' => 'Lager', 'abv' => 5.2],
            ['name' => 'Holba Premium', 'brewery' => 'Holba', 'style' => 'Lager', 'abv' => 5.2],
            ['name' => 'Zubr Classic', 'brewery' => 'Zubr', 'style' => 'Lager', 'abv' => 4.1],
            ['name' => 'Zubr Gold', 'brewery' => 'Zubr', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Lobkowicz Premium', 'brewery' => 'Lobkowicz', 'style' => 'Lager', 'abv' => 4.7],
            ['name' => 'Lobkowicz Démon', 'brewery' => 'Lobkowicz', 'style' => 'Dark Lager', 'abv' => 4.7],
            ['name' => 'Polička Hradební 12°', 'brewery' => 'Polička', 'style' => 'Lager', 'abv' => 5.2],
            ['name' => 'Polička Záviš', 'brewery' => 'Polička', 'style' => 'Dark Lager', 'abv' => 5.7],
            ['name' => 'Regent Bohemia', 'brewery' => 'Regent', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Regent Tmavý 12°', 'brewery' => 'Regent', 'style' => 'Dark Lager', 'abv' => 4.7],
            ['name' => 'Dudák Premium 12°', 'brewery' => 'Dudák', 'style' => 'Lager', 'abv' => 5.1],
            ['name' => 'Černá Hora Tas', 'brewery' => 'Černá Hora', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Černá Hora Ležák', 'brewery' => 'Černá Hora', 'style' => 'Lager', 'abv' => 4.8],
            ['name' => 'Chotěboř Premium', 'brewery' => 'Chotěboř', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Nymburk Postřižinské', 'brewery' => 'Nymburk', 'style' => 'Lager', 'abv' => 5.2],
            ['name' => 'Platan Prácheňská perla', 'brewery' => 'Platan', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Podkováň 11°', 'brewery' => 'Podkováň', 'style' => 'Lager', 'abv' => 4.6],
            ['name' => 'Rychtář Premium', 'brewery' => 'Rychtář', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Samson 1795', 'brewery' => 'Samson', 'style' => 'Lager', 'abv' => 4.7],

            // === MINIPIVOVARY & CRAFT ===
            // Matuška
            ['name' => 'Matuška Raptor IPA', 'brewery' => 'Matuška', 'style' => 'IPA', 'abv' => 6.3],
            ['name' => 'Matuška Apollo Galaxy', 'brewery' => 'Matuška', 'style' => 'APA', 'abv' => 5.5],
            ['name' => 'Matuška Zlatá Raketa', 'brewery' => 'Matuška', 'style' => 'IPA', 'abv' => 5.8],
            ['name' => 'Matuška California', 'brewery' => 'Matuška', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Matuška Černá Raketa', 'brewery' => 'Matuška', 'style' => 'Black IPA', 'abv' => 6.0],
            ['name' => 'Matuška Weizen', 'brewery' => 'Matuška', 'style' => 'Wheat', 'abv' => 4.5],

            // Zichovec
            ['name' => 'Zichovec Nectar of Happiness', 'brewery' => 'Zichovec', 'style' => 'NEIPA', 'abv' => 6.5],
            ['name' => 'Zichovec Hop Haze', 'brewery' => 'Zichovec', 'style' => 'Hazy IPA', 'abv' => 6.0],
            ['name' => 'Zichovec Sluneční Paprsek', 'brewery' => 'Zichovec', 'style' => 'APA', 'abv' => 5.0],
            ['name' => 'Zichovec Brutal Panda', 'brewery' => 'Zichovec', 'style' => 'Double IPA', 'abv' => 8.0],
            ['name' => 'Zichovec Ležák', 'brewery' => 'Zichovec', 'style' => 'Lager', 'abv' => 4.8],

            // Clock
            ['name' => 'Clock Sour Power', 'brewery' => 'Clock', 'style' => 'Sour', 'abv' => 4.5],
            ['name' => 'Clock V Lese', 'brewery' => 'Clock', 'style' => 'IPA', 'abv' => 6.1],
            ['name' => 'Clock Bastard', 'brewery' => 'Clock', 'style' => 'APA', 'abv' => 5.5],
            ['name' => 'Clock Ležák', 'brewery' => 'Clock', 'style' => 'Lager', 'abv' => 4.5],

            // Raven
            ['name' => 'Raven Side Bitch', 'brewery' => 'Raven', 'style' => 'IPA', 'abv' => 6.3],
            ['name' => 'Raven Blood Red Sky', 'brewery' => 'Raven', 'style' => 'Red IPA', 'abv' => 6.5],
            ['name' => 'Raven Tokyo Stealth', 'brewery' => 'Raven', 'style' => 'Black IPA', 'abv' => 6.5],
            ['name' => 'Raven Stout', 'brewery' => 'Raven', 'style' => 'Stout', 'abv' => 5.5],

            // Sibeeria
            ['name' => 'Sibeeria Easy Drinker', 'brewery' => 'Sibeeria', 'style' => 'Session IPA', 'abv' => 4.5],
            ['name' => 'Sibeeria Maracuja', 'brewery' => 'Sibeeria', 'style' => 'Milkshake IPA', 'abv' => 6.0],
            ['name' => 'Sibeeria Frozen', 'brewery' => 'Sibeeria', 'style' => 'NEIPA', 'abv' => 6.5],

            // Falkon
            ['name' => 'Falkon Houba', 'brewery' => 'Falkon', 'style' => 'APA', 'abv' => 5.3],
            ['name' => 'Falkon Dark Ale', 'brewery' => 'Falkon', 'style' => 'Dark Ale', 'abv' => 5.5],
            ['name' => 'Falkon Strawberry Blond', 'brewery' => 'Falkon', 'style' => 'Fruit Ale', 'abv' => 4.5],

            // Další craft
            ['name' => 'Únětický 10°', 'brewery' => 'Únětice', 'style' => 'Lager', 'abv' => 4.0],
            ['name' => 'Únětický 12°', 'brewery' => 'Únětice', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Kocour Catfish', 'brewery' => 'Kocour', 'style' => 'APA', 'abv' => 5.2],
            ['name' => 'Kocour Samurai IPA', 'brewery' => 'Kocour', 'style' => 'IPA', 'abv' => 6.5],
            ['name' => 'Permon IPA', 'brewery' => 'Permon', 'style' => 'IPA', 'abv' => 6.1],
            ['name' => 'Permon Bock', 'brewery' => 'Permon', 'style' => 'Bock', 'abv' => 7.0],
            ['name' => 'Jihoměstský Pale Ale', 'brewery' => 'Jihoměstský pivovar', 'style' => 'Pale Ale', 'abv' => 5.0],
            ['name' => 'Mazák 11°', 'brewery' => 'Mazák', 'style' => 'Lager', 'abv' => 4.7],
            ['name' => 'Frýdlant 12°', 'brewery' => 'Frýdlant', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Volt 10°', 'brewery' => 'Volt', 'style' => 'Lager', 'abv' => 4.3],
            ['name' => 'Purkmistr 12°', 'brewery' => 'Purkmistr', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Chříč Hradní', 'brewery' => 'Chříč', 'style' => 'Lager', 'abv' => 4.5],
            ['name' => 'Nomád West Coast IPA', 'brewery' => 'Nomád', 'style' => 'IPA', 'abv' => 6.2],
            ['name' => 'Zhůřák IPA', 'brewery' => 'Zhůřák', 'style' => 'IPA', 'abv' => 6.0],
            ['name' => 'Albrecht 12°', 'brewery' => 'Albrecht', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Cvikov Kulér', 'brewery' => 'Cvikov', 'style' => 'Lager', 'abv' => 5.0],
            ['name' => 'Vratislavice Vratislav', 'brewery' => 'Vratislavice', 'style' => 'Lager', 'abv' => 4.8],
        ];

        // Check for existing beers to avoid duplicates on re-run
        $existingBeers = [];
        $beerRepo = $manager->getRepository(Beer::class);
        foreach ($beerRepo->findAll() as $beer) {
            $existingBeers[$beer->getName() . '|' . $beer->getBrewery()] = true;
        }

        $added = 0;
        foreach ($beers as $data) {
            $key = $data['name'] . '|' . $data['brewery'];
            if (isset($existingBeers[$key])) {
                continue;
            }

            $beer = new Beer();
            $beer->setName($data['name']);
            $beer->setBrewery($data['brewery']);
            $beer->setStyle($data['style']);
            $beer->setAbv($data['abv']);

            $manager->persist($beer);
            $added++;
        }

        $manager->flush();
    }
}
