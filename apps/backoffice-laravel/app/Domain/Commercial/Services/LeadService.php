<?php

namespace App\Domain\Commercial\Services;

use App\Domain\Commercial\Contracts\LeadServiceInterface;
use App\Domain\Commercial\Contracts\LeadRepositoryInterface;
use App\Domain\Commercial\Contracts\ClientRepositoryInterface;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class LeadService implements LeadServiceInterface
{
    public function __construct(
        private LeadRepositoryInterface $leadRepository,
        private ClientRepositoryInterface $clientRepository
    ) {}

    public function getLead(int $id): ?Lead
    {
        return $this->leadRepository->findById($id);
    }

    public function getAllLeads(): Collection
    {
        return $this->leadRepository->getAll();
    }

    public function registerLead(array $data): Lead
    {
        return $this->leadRepository->create($data);
    }

    public function convertLeadToClient(int $leadId, array $clientData): bool
    {
        DB::beginTransaction();
        try {
            $clientData['lead_id'] = $leadId;
            $this->clientRepository->create($clientData);
            $this->leadRepository->convertToClient($leadId);
            
            DB::commit();
            return true;
        } catch (Exception $e) {
            DB::rollBack();
            return false;
        }
    }
}
