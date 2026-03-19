<?php

namespace App\Domain\Commercial\Contracts;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;

interface LeadServiceInterface
{
    public function getLead(int $id): ?Lead;
    public function getAllLeads(): Collection;
    public function registerLead(array $data): Lead;
    public function convertLeadToClient(int $leadId, array $clientData): bool;
}
