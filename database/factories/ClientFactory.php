<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        static $counter = 1;

        return [
            'code'        => 'CLI-' . str_pad($counter++, 4, '0', STR_PAD_LEFT),
            'type'        => 'entreprise',
            'name'        => $this->faker->company(),
            'trade_name'  => null,
            'email'       => $this->faker->companyEmail(),
            'phone'       => '+226 ' . $this->faker->numerify('## ## ## ##'),
            'city'        => $this->faker->randomElement(['Ouagadougou', 'Bobo-Dioulasso', 'Koudougou']),
            'country'     => 'Burkina Faso',
            // [Stabilité] Valeur FIXE, plus de tirage au sort.
            //
            // Le défaut était `randomElement([500 000, 1 000 000, 2 000 000, 5 000 000])`.
            // 73 fichiers de test sur 96 créent un client sans fixer ce champ ;
            // chacun soumettant une commande de plus de 500 000 FCFA TTC jouait donc
            // à pile ou face avec la garde d'encours. Constaté sur
            // OrderToCashFullChainTest : commande à 590 000 TTC, échec une fois sur
            // quatre, sur n'importe quel moteur — instabilité facile à prendre pour
            // un écart SQLite/MySQL.
            //
            // 5 000 000 est le PLUS HAUT de l'ancien tirage : aucun test qui passait
            // ne peut se mettre à échouer, seuls des blocages aléatoires disparaissent.
            // Les tests qui éprouvent le plafond le fixent explicitement — 23 le font
            // déjà — et c'est la seule façon correcte de le faire.
            'credit_limit'=> 5_000_000,
            // Même raison : une échéance tirée au sort fausse toute assertion sur une
            // date d'exigibilité ou une balance âgée. 30 jours est la valeur usuelle.
            'payment_days'=> 30,
            'balance'        => 0,
            'is_active'      => true,
            'is_tax_exempt'  => false,
        ];
    }
}
