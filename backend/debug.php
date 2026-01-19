$d = App\Models\Document::orderBy('created_at', 'desc')->first();
if (!$d) { echo "No document found.\n"; exit; }
echo "Document: " . $d->id . PHP_EOL;

try {
    $service = app(App\Services\DocumentService::class);
    $path = $service->createEvidenceBundle($d);
    echo "Bundle Created at: " . $path . PHP_EOL;
    
    $exists = Illuminate\Support\Facades\Storage::disk('minio')->exists($path);
    echo "File Exists in MinIO: " . ($exists ? 'YES' : 'NO') . PHP_EOL;
    
    if ($exists) {
        echo "Attempting to create download response..." . PHP_EOL;
        $response = Illuminate\Support\Facades\Storage::disk('minio')->download($path);
        echo "Download response created: " . get_class($response) . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "Exception: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString();
}
exit;