<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountClass;
use App\Models\Company;
use App\Models\JournalType;
use Illuminate\Database\Seeder;

class SyscohadaChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::firstOrFail();

        $this->seedAccountClasses($company);
        $this->seedAccounts($company);
        $this->seedJournalTypes($company);

        $this->command->info('Plan comptable SYSCOHADA créé avec succès.');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Classes
    // ─────────────────────────────────────────────────────────────────────────
    private function seedAccountClasses(Company $company): void
    {
        $classes = [
            [1, 'Comptes de ressources durables',           'Capitaux propres et assimilés, emprunts et dettes financières'],
            [2, 'Comptes d\'actif immobilisé',              'Immobilisations incorporelles, corporelles et financières'],
            [3, 'Comptes de stocks',                         'Marchandises, matières premières, produits finis'],
            [4, 'Comptes de tiers',                          'Fournisseurs, clients, impôts, personnel'],
            [5, 'Comptes de trésorerie',                     'Titres de placement, comptes bancaires, caisse'],
            [6, 'Comptes de charges des activités ordinaires','Achats de marchandises et matières, charges de personnel'],
            [7, 'Comptes de produits des activités ordinaires','Ventes de marchandises et produits, prestations de services'],
            [8, 'Comptes des autres charges et produits',    'Charges et produits hors activités ordinaires'],
            [9, 'Comptes des engagements hors bilan et comptes analytiques', 'Comptes de situation et analytique'],
        ];

        foreach ($classes as [$number, $name, $desc]) {
            AccountClass::firstOrCreate(
                ['company_id' => $company->id, 'number' => $number],
                ['name' => $name, 'description' => $desc]
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Accounts — abridged SYSCOHADA chart (key accounts for a trading company)
    // ─────────────────────────────────────────────────────────────────────────
    private function seedAccounts(Company $company): void
    {
        $classIds = AccountClass::where('company_id', $company->id)
            ->pluck('id', 'number')
            ->all();

        // Format: [code, name, type, is_detail, class_number, parent_code|null]
        $accounts = [
            // ── Classe 1 ──────────────────────────────────────────────────────
            ['10',   'Capital',                                'passif', false, 1, null],
            ['101',  'Capital social',                         'passif', false, 1, '10'],
            ['1011', 'Capital souscrit, appelé, versé',        'passif', true,  1, '101'],
            ['11',   'Réserves',                               'passif', false, 1, null],
            ['111',  'Réserve légale',                         'passif', true,  1, '11'],
            ['12',   'Report à nouveau',                       'passif', true,  1, null],
            ['13',   'Résultat net de l\'exercice',            'passif', true,  1, null],
            ['16',   'Emprunts et dettes financières diverses','passif', false, 1, null],
            ['161',  'Emprunts ordinaires',                    'passif', true,  1, '16'],
            ['162',  'Avances reçues',                         'passif', true,  1, '16'],

            // ── Classe 2 ──────────────────────────────────────────────────────
            ['20',   'Charges immobilisées',                   'actif', false, 2, null],
            ['201',  'Frais de constitution',                  'actif', true,  2, '20'],
            ['21',   'Immobilisations incorporelles',          'actif', false, 2, null],
            ['211',  'Frais de recherche et développement',    'actif', true,  2, '21'],
            ['212',  'Brevets, licences, logiciels',           'actif', true,  2, '21'],
            ['22',   'Terrains',                               'actif', false, 2, null],
            ['221',  'Terrains agricoles et forestiers',       'actif', true,  2, '22'],
            ['222',  'Terrains nus',                           'actif', true,  2, '22'],
            ['23',   'Bâtiments, installations techniques',    'actif', false, 2, null],
            ['231',  'Bâtiments sur sol propre',               'actif', true,  2, '23'],
            ['24',   'Matériel',                               'actif', false, 2, null],
            ['241',  'Matériel et outillage industriel',       'actif', true,  2, '24'],
            ['244',  'Matériel de transport',                  'actif', true,  2, '24'],
            ['245',  'Matériel de bureau, informatique',       'actif', true,  2, '24'],
            ['28',   'Amortissements',                         'actif', false, 2, null],
            ['281',  'Amortissements des immob. incorporelles','actif', true,  2, '28'],
            ['284',  'Amortissements du matériel',             'actif', true,  2, '28'],

            // ── Classe 3 ──────────────────────────────────────────────────────
            ['30',   'Stocks de marchandises',                 'actif', false, 3, null],
            ['301',  'Marchandises A',                         'actif', true,  3, '30'],
            ['302',  'Marchandises B',                         'actif', true,  3, '30'],
            ['31',   'Matières premières',                     'actif', false, 3, null],
            ['311',  'Matières premières A',                   'actif', true,  3, '31'],
            ['36',   'Produits finis',                         'actif', false, 3, null],
            ['361',  'Produits finis A',                       'actif', true,  3, '36'],

            // ── Classe 4 ──────────────────────────────────────────────────────
            ['40',   'Fournisseurs et comptes rattachés',      'passif', false, 4, null],
            ['401',  'Fournisseurs, dettes en compte',         'passif', true,  4, '40'],
            ['408',  'Fournisseurs, factures non parvenues',   'passif', true,  4, '40'],
            ['409',  'Fournisseurs débiteurs',                 'actif',  true,  4, '40'],
            ['41',   'Clients et comptes rattachés',           'actif', false, 4, null],
            ['411',  'Clients',                                'actif', true,  4, '41'],
            ['412',  'Clients — effets à recevoir',            'actif', true,  4, '41'],
            ['418',  'Clients — produits non encore facturés', 'actif', true,  4, '41'],
            ['44',   'État et collectivités publiques',        'passif', false, 4, null],
            ['4411', 'TVA facturée sur ventes',                'passif', true,  4, '44'],
            ['4452', 'TVA due intracommunautaire',             'passif', true,  4, '44'],
            ['4455', 'TVA à décaisser',                        'passif', true,  4, '44'],
            ['4456', 'TVA déductible',                         'actif',  true,  4, '44'],
            ['4457', 'TVA collectée',                          'passif', true,  4, '44'],
            ['4458', 'TVA récupérable sur immobilisations',    'actif',  true,  4, '44'],
            ['447',  'État — impôts retenus à la source',      'passif', true,  4, '44'],
            ['4473', 'État — retenues IS',                     'passif', true,  4, '44'],
            ['42',   'Personnel',                              'passif', false, 4, null],
            ['421',  'Personnel, avances et acomptes',         'actif',  true,  4, '42'],
            ['422',  'Personnel, rémunérations dues',          'passif', true,  4, '42'],
            ['428',  'Personnel, charges à payer',             'passif', true,  4, '42'],
            ['45',   'Organismes sociaux',                     'passif', false, 4, null],
            ['451',  'Caisse nationale de sécurité sociale',   'passif', true,  4, '45'],
            ['46',   'Associés et groupe',                     'passif', false, 4, null],
            ['461',  'Associés — opérations sur le capital',   'passif', true,  4, '46'],
            ['47',   'Débiteurs et créditeurs divers',         'actif', false, 4, null],
            ['471',  'Débiteurs divers',                       'actif', true,  4, '47'],
            ['472',  'Créditeurs divers',                      'passif', true,  4, '47'],
            ['48',   'Créances et dettes hors activités ord.', 'actif', false, 4, null],
            ['481',  'Créances HAO',                           'actif', true,  4, '48'],
            ['49',   'Dépréciations et provisions — tiers',    'actif', false, 4, null],
            ['491',  'Dépréciations des comptes clients',      'actif', true,  4, '49'],

            // ── Classe 5 ──────────────────────────────────────────────────────
            ['51',   'Valeurs à encaisser',                    'actif', false, 5, null],
            ['511',  'Effets à l\'encaissement',               'actif', true,  5, '51'],
            ['52',   'Banques',                                'actif', false, 5, null],
            ['521',  'Banque principale',                      'actif', true,  5, '52'],
            ['522',  'Banque secondaire',                      'actif', true,  5, '52'],
            ['53',   'Établissements financiers',              'actif', false, 5, null],
            ['571',  'Caisse siège social',                    'actif', true,  5, null],
            ['572',  'Caisse agence',                          'actif', true,  5, null],

            // ── Classe 6 ──────────────────────────────────────────────────────
            ['60',   'Achats et variations de stocks',         'charge', false, 6, null],
            ['601',  'Achats de marchandises',                 'charge', true,  6, '60'],
            ['6011', 'Achats de marchandises — groupe A',      'charge', true,  6, '601'],
            ['602',  'Achats de matières premières',           'charge', true,  6, '60'],
            ['604',  'Achats stockés — autres approv.',        'charge', true,  6, '60'],
            ['605',  'Autres achats',                          'charge', true,  6, '60'],
            ['608',  'Frais accessoires d\'achats',            'charge', true,  6, '60'],
            ['61',   'Transports',                             'charge', false, 6, null],
            ['611',  'Transports sur achats',                  'charge', true,  6, '61'],
            ['612',  'Transports sur ventes',                  'charge', true,  6, '61'],
            ['63',   'Autres charges externes',                'charge', false, 6, null],
            ['631',  'Frais bancaires',                        'charge', true,  6, '63'],
            ['632',  'Loyers et charges locatives',            'charge', true,  6, '63'],
            ['633',  'Redevances pour brevets, licences',      'charge', true,  6, '63'],
            ['634',  'Entretien, réparations, maintenance',    'charge', true,  6, '63'],
            ['635',  'Primes d\'assurance',                    'charge', true,  6, '63'],
            ['64',   'Impôts et taxes',                        'charge', false, 6, null],
            ['641',  'Impôts et taxes directs',                'charge', true,  6, '64'],
            ['642',  'Droits d\'enregistrement',               'charge', true,  6, '64'],
            ['65',   'Autres charges',                         'charge', false, 6, null],
            ['651',  'Pertes sur créances irrécouvrables',     'charge', true,  6, '65'],
            ['658',  'Charges diverses',                       'charge', true,  6, '65'],
            ['66',   'Charges de personnel',                   'charge', false, 6, null],
            ['661',  'Appointements et salaires',              'charge', true,  6, '66'],
            ['662',  'Commissions et courtages sur achats',    'charge', true,  6, '66'],
            ['663',  'Indemnités forfaitaires versées',        'charge', true,  6, '66'],
            ['664',  'Charges sociales patronales',            'charge', true,  6, '66'],
            ['67',   'Frais financiers',                       'charge', false, 6, null],
            ['671',  'Intérêts des emprunts',                  'charge', true,  6, '67'],
            ['672',  'Intérêts des comptes courants créditeurs','charge', true, 6, '67'],
            ['68',   'Dotations aux amortissements',           'charge', false, 6, null],
            ['681',  'DAP sur immob. incorporelles',           'charge', true,  6, '68'],
            ['684',  'DAP sur matériels',                      'charge', true,  6, '68'],
            ['69',   'Charges provisionnées d\'exploitation',  'charge', false, 6, null],
            ['691',  'Dotations aux provisions pour créances', 'charge', true,  6, '69'],

            // ── Classe 7 ──────────────────────────────────────────────────────
            ['70',   'Ventes',                                 'produit', false, 7, null],
            ['701',  'Ventes de marchandises',                 'produit', true,  7, '70'],
            ['702',  'Ventes de produits finis',               'produit', true,  7, '70'],
            ['703',  'Ventes de produits intermédiaires',      'produit', true,  7, '70'],
            ['706',  'Prestations de services',                'produit', true,  7, '70'],
            ['707',  'Rabais, remises et ristournes accordés', 'produit', true,  7, '70'],
            ['71',   'Subventions d\'exploitation',            'produit', true,  7, null],
            ['72',   'Production immobilisée',                 'produit', true,  7, null],
            ['73',   'Variations de stocks de biens prod.',    'produit', false, 7, null],
            ['731',  'Variation stock de marchandises',        'produit', true,  7, '73'],
            ['75',   'Autres produits',                        'produit', false, 7, null],
            ['751',  'Revenus des immeubles',                  'produit', true,  7, '75'],
            ['758',  'Produits divers',                        'produit', true,  7, '75'],
            ['77',   'Revenus financiers',                     'produit', false, 7, null],
            ['771',  'Intérêts de prêts',                      'produit', true,  7, '77'],
            ['772',  'Revenus des titres de participation',    'produit', true,  7, '77'],
            ['78',   'Reprises de provisions',                 'produit', false, 7, null],
            ['781',  'Reprises de provisions d\'exploitation', 'produit', true,  7, '78'],

            // ── Classe 8 ──────────────────────────────────────────────────────
            ['81',   'Valeurs comptables des cessions d\'immo','charge', false, 8, null],
            ['811',  'Valeurs comptables des immo. cédées',    'charge', true,  8, '81'],
            ['82',   'Produits des cessions d\'immobilisations','produit', false, 8, null],
            ['821',  'Produits des cessions d\'immo. corp.',   'produit', true,  8, '82'],
            ['83',   'Charges HAO',                            'charge', false, 8, null],
            ['831',  'Charges HAO courantes',                  'charge', true,  8, '83'],
            ['84',   'Produits HAO',                           'produit', false, 8, null],
            ['841',  'Produits HAO courants',                  'produit', true,  8, '84'],
            ['86',   'Reprises de provisions HAO',             'produit', true,  8, null],
            ['89',   'Impôts sur le résultat',                 'charge', false, 8, null],
            ['891',  'Impôts sur les bénéfices',               'charge', true,  8, '89'],
        ];

        // Build parent lookup
        $parentMap = [];

        foreach ($accounts as [$code, $name, $type, $is_detail, $classNum, $parentCode]) {
            $classId  = $classIds[$classNum] ?? null;
            $parentId = $parentCode ? ($parentMap[$parentCode] ?? null) : null;

            $account = Account::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'account_class_id' => $classId,
                    'parent_id'        => $parentId,
                    'name'             => $name,
                    'type'             => $type,
                    'is_detail'        => $is_detail,
                    'is_active'        => true,
                ]
            );

            $parentMap[$code] = $account->id;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Journal types
    // ─────────────────────────────────────────────────────────────────────────
    private function seedJournalTypes(Company $company): void
    {
        $types = [
            ['AC', 'Journal des Achats',              'achat'],
            ['VE', 'Journal des Ventes',              'vente'],
            ['BQ', 'Journal de Banque',               'banque'],
            ['CA', 'Journal de Caisse',               'caisse'],
            ['OD', 'Journal des Opérations Diverses', 'operations_diverses'],
            ['AN', 'Journal d\'À-Nouveau',             'a_nouveau'],
        ];

        foreach ($types as [$code, $name, $type]) {
            JournalType::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                ['name' => $name, 'type' => $type, 'is_active' => true]
            );
        }
    }
}
