<?php

namespace Database\Seeders;

use App\Models\Master\Department;
use App\Models\Master\Employee;
use App\Models\Master\Organization;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $org = Organization::first();
        $orgId = $org?->id;

        $departments = [
            [
                'code' => 'Fin',
                'name' => 'Finance',
                'manager_name' => 'Syaifani Auliana Havid',
            ],
            [
                'code' => 'CPG',
                'name' => 'Campaigner',
                'manager_name' => 'Abil Ahmad Akbar',
            ],
            [
                'code' => 'COM',
                'name' => 'Communication',
                'manager_name' => 'Sarah Rosemery Megumi W',
            ],
            [
                'code' => 'PRC',
                'name' => 'Procurement & Adm',
                'manager_name' => 'Riki Suryandi',
            ],
            [
                'code' => 'OPS',
                'name' => 'Operational',
                'manager_name' => 'Denny Bhatara',
            ],
        ];

        $createdDepts = [];
        foreach ($departments as $data) {
            $createdDepts[$data['code']] = Department::updateOrCreate(
                ['code' => $data['code']],
                [
                    'organization_id' => $orgId,
                    'name' => $data['name'],
                    'manager_name' => $data['manager_name'],
                    'is_active' => true,
                ]
            );
        }

        // Clean up legacy department codes if they exist by deactivating them or migrating employees
        $legacyMappings = [
            'DEPT-FIN' => 'Fin',
            'DEPT-CAMP' => 'CPG',
            'DEPT-COMMS' => 'COM',
            'DEPT-PROG' => 'OPS',
        ];

        foreach ($legacyMappings as $oldCode => $newCode) {
            $oldDept = Department::withTrashed()->where('code', $oldCode)->first();
            if ($oldDept) {
                if (isset($createdDepts[$newCode])) {
                    Employee::where('department_id', $oldDept->id)
                        ->update(['department_id' => $createdDepts[$newCode]->id]);
                }
                $oldDept->forceDelete();
            }
        }

        // Reassign specific employees to their exact department
        $employeeDeptAssignments = [
            'Fin' => [
                'syaifani.havid@kaoemtelapak.org',
                'winda.apriani@kaoemtelapak.org',
                'winda.astuti@kaoemtelapak.org',
            ],
            'CPG' => [
                'abil.akbar@kaoemtelapak.org',
                'agetha@kaoemtelapak.org',
                'febry@kaoemtelapak.org',
                'renaldi.aji@kaoemtelapak.org',
                'veni.siregar@kaoemtelapak.org',
                'ziadatunnisa.latifa@kaoemtelapak.org',
                'zufar.fauzan@kaoemtelapak.org',
                'irbah@kaoemtelapak.org',
                'leona@kaoemtelapak.org',
                'viena.tanjung@kaoemtelapak.org',
                'campaigner.tbc@kaoemtelapak.org',
            ],
            'COM' => [
                'sarah.megumi@kaoemtelapak.org',
                'arif@kaoemtelapak.org',
                'ayu.perwitosari@kaoemtelapak.org',
            ],
            'PRC' => [
                'riki.suryandi@kaoemtelapak.org',
                'munip@kaoemtelapak.org',
            ],
            'OPS' => [
                'denny.bhatara@kaoemtelapak.org',
                'indra@kaoemtelapak.org',
            ],
        ];

        foreach ($employeeDeptAssignments as $deptCode => $emails) {
            if (isset($createdDepts[$deptCode])) {
                Employee::whereIn('email', $emails)
                    ->update(['department_id' => $createdDepts[$deptCode]->id]);
            }
        }
    }
}
