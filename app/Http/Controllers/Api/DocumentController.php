<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentUploadRequest;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $service) {}

    public function index(Request $request)
    {
        $documents = Document::with('uploader')
            ->when($request->query('source_module'), fn ($q, $v) => $q->where('source_module', $v))
            ->when($request->query('source_id'), fn ($q, $v) => $q->where('source_id', $v))
            ->orderByDesc('created_at')
            ->get()
            ->map($this->transform(...));

        return response()->json(['data' => $documents]);
    }

    public function store(DocumentUploadRequest $request)
    {
        $document = $this->service->upload(
            $request->file('file'),
            $request->validated('source_module'),
            $request->validated('source_id'),
            $request->user()->id
        );

        return response()->json(['data' => $this->transform($document)], 201);
    }

    public function destroy(Document $document)
    {
        $this->service->delete($document);

        return response()->json(null, 204);
    }

    private function transform(Document $d): array
    {
        return [
            'id' => $d->id,
            'sourceModule' => $d->source_module,
            'sourceId' => $d->source_id,
            'filename' => $d->original_filename,
            'mimeType' => $d->mime_type,
            'fileSize' => $d->file_size,
            'url' => asset('storage/documents/' . $d->stored_filename),
            'uploadedBy' => $d->uploader?->name,
            'uploadedAt' => $d->created_at->toDateTimeString(),
        ];
    }
}