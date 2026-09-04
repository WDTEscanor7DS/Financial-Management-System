<?php

namespace App\Services;

use App\Models\Revenue;
use Illuminate\Support\Facades\DB;

class RevenueService
{
    public function create(array $data, int $userId): Revenue
    {
        return DB::transaction(function () use ($data, $userId) {
            $revenue = Revenue::create([...$data, 'recorded_by' => $userId]);

            AuditService::log('Created Revenue', 'Revenue Management', (string) $revenue->id);
            NotificationService::push(sprintf(
                'New revenue recorded: REV-%05d (\u20b1%s)',
                $revenue->id,
                number_format((float) $revenue->amount, 2)
            ));

            return $revenue;
        });
    }

    public function update(Revenue $revenue, array $data): Revenue
    {
        return DB::transaction(function () use ($revenue, $data) {
            $revenue->update($data);
            AuditService::log('Updated Revenue', 'Revenue Management', (string) $revenue->id);

            return $revenue->refresh();
        });
    }

    public function delete(Revenue $revenue): void
    {
        DB::transaction(function () use ($revenue) {
            $id = $revenue->id;
            $revenue->delete();
            AuditService::log('Deleted Revenue', 'Revenue Management', (string) $id);
        });
    }
}
