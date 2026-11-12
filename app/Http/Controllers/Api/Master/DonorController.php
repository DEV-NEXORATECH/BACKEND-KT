<?php

namespace App\Http\Controllers\Api\Master;

use App\Models\Master\Donor;
use App\Http\Requests\Master\StoreDonorRequest;
use App\Http\Requests\Master\UpdateDonorRequest;
use App\Http\Resources\Master\DonorResource;

class DonorController extends BaseMasterController
{
    protected string $modelClass = Donor::class;
    protected string $resourceClass = DonorResource::class;
    protected string $storeRequestClass = StoreDonorRequest::class;
    protected string $updateRequestClass = UpdateDonorRequest::class;
    protected array $searchableColumns = ['code', 'name', 'contact_person', 'email', 'country'];
    protected array $defaultWith = [
        'defaultCurrency',
        'donorType',
        'fiscalYear',
        'grantAgreements.currency',
        'grantAgreements.fundingSource',
        'grantAgreements.reportingDeadlines',
        'grantAgreements.projects.grantAgreement',
        'grantAgreements.projects.program',
        'grantAgreements.projects.logframes',
        'grantAgreements.projects.workplans',
        'grantAgreements.projects.budgetLines.budgetCategory',
        'grantAgreements.projects.partners',
        'grantAgreements.projects.fundingSources',
    ];

    protected function defaultRelations(): array
    {
        return [
            'defaultCurrency',
            'donorType',
            'fiscalYear',
            'grantAgreements' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.fiscalYear',
            'grantAgreements.currency',
            'grantAgreements.fundingSource',
            'grantAgreements.reportingDeadlines',
            'grantAgreements.projects' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.projects.fiscalYears',
            'grantAgreements.projects.grantAgreement' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.projects.program',
            'grantAgreements.projects.logframes' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.projects.workplans' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.projects.budgetLines' => fn ($query) => $query->withoutGlobalScope('fiscal_year_context'),
            'grantAgreements.projects.budgetLines.budgetCategory',
            'grantAgreements.projects.partners',
            'grantAgreements.projects.fundingSources',
        ];
    }
}
