<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Fetch existing roles
        $roles = Role::all()->keyBy('slug');
        $users = [
            // Super Admin Account
            [
                'name' => 'Super Admin',
                'email' => 'admin@kaoemtelapak.org',
                'password' => 'password123',
                'role_slug' => 'super-admin',
            ],
            [
                'name' => 'Super Admin (Dev)',
                'email' => 'admin@kaoemtelapak.test',
                'password' => 'password123',
                'role_slug' => 'super-admin',
            ],

            // Sample Row from Screenshot (Yellow Highlight)
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@contoh.org',
                'password' => 'Welcome123!',
                'role_slug' => 'finance-manager',
            ],

            // Top Management / TBC Roles
            [
                'name' => 'Presiden KT (TBC)',
                'email' => 'presiden@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'management',
            ],
            [
                'name' => 'Wapres KT (TBC)',
                'email' => 'wapres@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'management',
            ],

            // Specific Users from Screenshot
            [
                'name' => 'Syaifani Auliana Havid',
                'email' => 'syaifani.havid@kaoemtelapak.org',
                'password' => 'FinKT123**',
                'role_slug' => 'finance-manager', // Peran Akses: Finance Manager
            ],
            [
                'name' => 'Winda Apriyani',
                'email' => 'winda.apriani@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'finance-officer', // Peran Akses: Accounting/Finance Officer
            ],
            [
                'name' => 'Denny Bhatara',
                'email' => 'denny.bhatara@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'budget-holder-project-manager', // Peran Akses: PoM (Program & Operational Manager)
            ],
            [
                'name' => 'Abil Ahmad Akbar',
                'email' => 'abil.akbar@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'budget-holder-project-manager', // Peran Akses: Campaigns Lead (Budget Holder)
            ],
            [
                'name' => 'Kwee Viena Lestari Tanjung',
                'email' => 'viena.tanjung@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Sr. Campaigner
            ],
            [
                'name' => 'Veni O Siregar',
                'email' => 'veni.siregar@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Sr. Campaigner
            ],
            [
                'name' => 'Sarah Megumi',
                'email' => 'sarah.megumi@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'budget-holder-project-manager', // Peran Akses: Manager Comms
            ],
            [
                'name' => 'Senior Campaigner (TBC)',
                'email' => 'campaigner.tbc@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Sr. Campaigner
            ],
            [
                'name' => 'Riki Suryandi',
                'email' => 'riki.suryandi@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'procurement-officer', // Peran Akses: Procurement & Adm Officer
            ],
            [
                'name' => 'Siti Aminah',
                'email' => 'siti@contoh.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'finance-officer', // Peran Akses: Finance Staff
            ],
            [
                'name' => 'Agetha Tri Lestari',
                'email' => 'agetha@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Youth Engagement Officer
            ],
            [
                'name' => 'Ayu Perwitosari',
                'email' => 'ayu.perwitosari@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Multi Media Content Creator
            ],
            [
                'name' => 'Febry Ronaldo Ginting',
                'email' => 'febry@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Youth Engagement and Advocacy
            ],
            [
                'name' => 'Indra Nuwinata',
                'email' => 'indra@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Satpam & OB
            ],
            [
                'name' => 'Munip',
                'email' => 'munip@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'administration-hr', // Peran Akses: Senior Administrative Officer
            ],
            [
                'name' => 'Renaldi Sastra Kusumah Aji',
                'email' => 'renaldi.aji@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Campaigner
            ],
            [
                'name' => 'Ziadatunnisa Ilmi Latifa',
                'email' => 'ziadatunnisa.latifa@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Campaigner
            ],
            [
                'name' => 'Zufar Fauzan Erimant',
                'email' => 'zufar.fauzan@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Researcher
            ],
            [
                'name' => 'M. Irbah Miftakhul Huda',
                'email' => 'irbah@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: GTID Administrator
            ],
            [
                'name' => 'Arif Candra Prasetya',
                'email' => 'arif@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Communication Officer
            ],
            [
                'name' => 'Leorana Sihotang',
                'email' => 'leona@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'program-project-staff', // Peran Akses: Campaigner
            ],
            [
                'name' => 'Winda Astuti',
                'email' => 'winda.astuti@kaoemtelapak.org',
                'password' => 'S3mpur#05',
                'role_slug' => 'finance-officer', // Peran Akses: Finance Officer
            ],
        ];

        foreach ($users as $userData) {
            $role = $roles->get($userData['role_slug']) 
                ?? Role::where('slug', $userData['role_slug'])->first();

            User::updateOrCreate(
                ['email' => strtolower($userData['email'])],
                [
                    'name' => $userData['name'],
                    'role_id' => $role?->id,
                    'password' => Hash::make($userData['password']),
                    'must_change_password' => true,
                ]
            );
        }
    }
}
