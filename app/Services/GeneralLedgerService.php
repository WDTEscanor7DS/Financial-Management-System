<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GeneralLedgerService
{
    public function createEntry(array $data): JournalEntry
    {
        $lines = $data['lines'];

        $totalDebit = array_sum(array_column($lines, 'debit'));
        $totalCredit = array_sum(array_column($lines, 'credit'));

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw new InvalidArgumentException(
                "Unbalanced entry: total debit ({$totalDebit}) does not equal total credit ({$totalCredit})."
            );
        }

        if (count($lines) < 2) {
            throw new InvalidArgumentException("A journal entry needs at least 2 lines.");
        }

        return DB::transaction(function () use ($data, $lines) {
            $entry = JournalEntry::create([
                'entry_date' => $data['entry_date'],
                'reference_no' => $data['reference_no'] ?? null,
                'description' => $data['description'],
                'source_module' => $data['source_module'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'created_by' => $data['created_by'],
            ]);

            foreach ($lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $line['debit'] ?? 0,
                    'credit' => $line['credit'] ?? 0,
                    'description' => $line['description'] ?? null,
                ]);
            }

            return $entry->load('lines.account');
        });
    }

        public function reverseEntry(JournalEntry $original, int $userId): JournalEntry
    {
        if ($original->reversal()->exists()) {
            throw new InvalidArgumentException("This entry has already been reversed.");
        }

        $original->load('lines');

        $reversedLines = $original->lines->map(fn ($line) => [
            'account_id' => $line->account_id,
            'debit' => $line->credit,
            'credit' => $line->debit,
            'description' => 'Reversal: ' . ($line->description ?? ''),
        ])->toArray();

        return DB::transaction(function () use ($original, $reversedLines, $userId) {
            $entry = JournalEntry::create([
                'entry_date' => now(),
                'reference_no' => 'REV-' . $original->id,
                'description' => 'Reversal of JE-' . str_pad($original->id, 5, '0', STR_PAD_LEFT) . ': ' . $original->description,
                'source_module' => 'GeneralLedger',
                'source_id' => $original->id,
                'reverses_entry_id' => $original->id,
                'created_by' => $userId,
            ]);

            foreach ($reversedLines as $line) {
                JournalEntryLine::create(array_merge($line, ['journal_entry_id' => $entry->id]));
            }

            return $entry->load('lines.account');
        });
    }
}