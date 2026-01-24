<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

// Create/Get inactive user
$user = User::where('email', 'test_inactive@example.com')->first();
if (!$user) {
    $user = User::create([
        'name' => 'Test Inactive',
        'email' => 'test_inactive@example.com',
        'password' => bcrypt('password'),
        'status' => 'INACTIVE'
    ]);
} else {
    $user->status = 'INACTIVE';
    $user->save();
}

echo "Testing login for user: " . $user->email . " (Status: " . $user->status . ")\n";

// We can't easily use Http::post here because we are inside the app itself and it might try to route internally in a way that doesn't trigger the controller if not set up correctly for testing.
// However, we can just call the controller method directly or use Request::create.

$request = Illuminate\Http\Request::create('/api/login', 'POST', [
    'email' => $user->email,
    'password' => 'password'
]);

$response = $app->handle($request);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Response Body: " . $response->getContent() . "\n";

if ($response->getStatusCode() === 403 && strpos($response->getContent(), 'Your account is suspended or inactive.') !== false) {
    echo "\nSUCCESS: Inactive user login correctly blocked with 403.\n";
} else {
    echo "\nFAILURE: Inactive user login NOT correctly blocked.\n";
}

// Cleanup
$user->delete();
