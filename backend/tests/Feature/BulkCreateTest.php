<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Template;
use App\Models\TemplateRole;
use App\Models\OrganizationalRole;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkCreateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_upload_csv_for_bulk_creation()
    {
        Storage::fake('minio');
        $user = User::factory()->create();

        // 1. Setup Template with Roles
        $template = Template::factory()->create(['user_id' => $user->id]);
        $orgRole = OrganizationalRole::create(['name' => 'Vendor', 'slug' => 'vendor', 'code' => 'VEND']);

        $role = TemplateRole::create([
            'template_id' => $template->id,
            'organizational_role_id' => $orgRole->id,
            'signing_order' => 1,
            'is_required' => true,
            'role' => 'Vendor Signer' // Display name
        ]);

        // 2. Create CSV
        // Header: Vendor Signer_Name, Vendor Signer_Email
        $content = "Vendor Signer_Name,Vendor Signer_Email\nJohn Doe,john@example.com\nJane Doe,jane@example.com";
        $file = UploadedFile::fake()->createWithContent('bulk.csv', $content);

        // 3. Call Endpoint
        $response = $this->actingAs($user)->postJson("/api/templates/{$template->id}/bulk-create", [
            'file' => $file
        ]);

        $response->dump();
        $response->assertStatus(202);
        $response->assertJsonStructure(['batch' => ['id', 'status', 'total_count']]);

        $batchId = $response->json('batch.id');

        // 4. Verify Batch Created
        $this->assertDatabaseHas('bulk_batches', [
            'id' => $batchId,
            'total_count' => 2,
            'processed_count' => 2,
            'success_count' => 2 // Since synchronous logic for small files usually completes
        ]);

        // 5. Verify Documents Created
        $this->assertDatabaseHas('documents', [
            'user_id' => $user->id,
            'bulk_batch_id' => $batchId,
            'title' => $template->name
        ]);

        // Verify 2 docs
        $this->assertEquals(2, \App\Models\Document::where('bulk_batch_id', $batchId)->count());

        // Verify Signers on one doc
        $doc = \App\Models\Document::where('bulk_batch_id', $batchId)->first();
        $this->assertDatabaseHas('document_signers', [
            'document_id' => $doc->id,
            'email' => 'john@example.com' // First row
        ]);
    }
}
