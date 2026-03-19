<?php

namespace App\Domain\Commercial\Repositories;

use App\Domain\Commercial\Contracts\LeadRepositoryInterface;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Collection;

class LeadRepository implements LeadRepositoryInterface
{
    public function findById(int $id): ?Lead
    {
        return Lead::find($id);
    }

    public function getAll(): Collection
    {
        return Lead::all();
    }

    public function create(array $data): Lead
    {
        return Lead::create($data);
    }

    public function update(int $id, array $data): bool
    {
        $lead = $this->findById($id);
        if (!$lead) return false;
        
        return $lead->update($data);
    }

    public function delete(int $id): bool
    {
        $lead = $this->findById($id);
        if (!$lead) return false;
        
        return $lead->delete();
    }

    public function convertToClient(int $leadId): bool
    {
        $lead = $this->findById($leadId);
        if (!$lead) return false;

        $lead->status = 'converted';
        return $lead->save();
    }
}
