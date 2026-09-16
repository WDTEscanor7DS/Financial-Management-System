<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    public function upload(UploadedFile $file, string $sourceModule, ?int $sourceId, int $userId): Document
    {
        $storedFilename = Str::uuid() . '.' . $file->getClientOriginalExtension();

        Storage::disk('public')->putFileAs('documents', $file, $storedFilename);

        return Document::create([
            'source_module' => $sourceModule,
            'source_id' => $sourceId,
            'original_filename' => $file->getClientOriginalName(),
            'stored_filename' => $storedFilename,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);
    }

    public function delete(Document $document): void
    {
        Storage::disk('public')->delete('documents/' . $document->stored_filename);
        $document->delete();
    }
}