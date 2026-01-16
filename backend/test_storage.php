<?php
use Illuminate\Support\Facades\Storage;
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $result = Storage::disk('local')->put('temp/test.txt', 'contents');
    echo $result ? "Written successfully\n" : "Failed to write\n";

    $storagePathApp = storage_path('app');
    echo "storage_path('app'): $storagePathApp\n";

    $diskPath = Storage::disk('local')->path('temp/test.txt');
    echo "Disk path: $diskPath\n";

    $path = storage_path('app/temp/test.txt');
    if (file_exists($path)) {
        echo "File exists at $path\n";
    } else {
        echo "File NOT found at $path\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
