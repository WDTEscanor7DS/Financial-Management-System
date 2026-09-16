<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
    }

    private function accountant(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', 'accountant')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_uploading_a_document_stores_the_file_and_record(): void
    {
        $accountant = $this->accountant();
        $file = UploadedFile::fake()->create('receipt.pdf', 500, 'application/pdf');

        $response = $this->actingAs($accountant)->post('/api/documents', [
            'file' => $file,
            'source_module' => 'Tax',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('documents', [
            'source_module' => 'Tax',
            'original_filename' => 'receipt.pdf',
        ]);

        $document = Document::first();
        Storage::disk('public')->assertExists('documents/' . $document->stored_filename);
    }

    public function test_rejects_disallowed_file_type(): void
    {
        $accountant = $this->accountant();
        $file = UploadedFile::fake()->create('script.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($accountant)->post('/api/documents', [
            'file' => $file,
            'source_module' => 'General',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('documents', 0);
    }

    public function test_deleting_a_document_removes_the_file(): void
    {
        $accountant = $this->accountant();
        $file = UploadedFile::fake()->create('receipt.pdf', 500, 'application/pdf');

        $upload = $this->actingAs($accountant)->post('/api/documents', [
            'file' => $file,
            'source_module' => 'Tax',
        ]);
        $documentId = $upload->json('data.id');
        $document = Document::find($documentId);
        $storedPath = 'documents/' . $document->stored_filename;

        Storage::disk('public')->assertExists($storedPath);

        $response = $this->actingAs($accountant)->deleteJson("/api/documents/{$documentId}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('documents', ['id' => $documentId]);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_filtering_by_source_module_only_returns_matching_documents(): void
    {
        $accountant = $this->accountant();

        $this->actingAs($accountant)->post('/api/documents', [
            'file' => UploadedFile::fake()->create('tax-doc.pdf', 100, 'application/pdf'),
            'source_module' => 'Tax',
        ]);
        $this->actingAs($accountant)->post('/api/documents', [
            'file' => UploadedFile::fake()->create('po-doc.pdf', 100, 'application/pdf'),
            'source_module' => 'PurchaseOrder',
        ]);

        $response = $this->actingAs($accountant)->getJson('/api/documents?source_module=Tax');

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.sourceModule', 'Tax');
    }
}