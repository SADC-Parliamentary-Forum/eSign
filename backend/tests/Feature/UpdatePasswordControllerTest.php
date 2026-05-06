<?php

namespace App\Http\Controllers;

function response()
{
    return new class {
        public function json($data, $status = 200)
        {
            return new \Illuminate\Http\JsonResponse($data, $status);
        }
    };
}

namespace Tests\Feature;

use App\Http\Controllers\AuthController;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidationFactory;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class UpdatePasswordControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        $application = new class {
            public function runningInConsole()
            {
                return false;
            }
        };
        $app->instance('app', $application);
        $app->instance(\Illuminate\Contracts\Foundation\Application::class, $application);

        $app->instance('config', new Repository([
            'app' => ['key' => 'base64:testing-key-for-password-controller-tests='],
            'hashing' => ['driver' => 'bcrypt'],
            'audit' => ['enabled' => true],
        ]));

        $app->singleton('hash', fn () => new \Illuminate\Hashing\HashManager($app));

        $translator = new Translator(new ArrayLoader(), 'en');
        $validator = new ValidationFactory($translator, $app);

        $app->instance('translator', $translator);
        $app->instance('validator', $validator);
        $app->instance(\Illuminate\Contracts\Validation\Factory::class, $validator);

        $logger = new class {
            public array $errors = [];

            public function error(string $message, array $context = []): void
            {
                $this->errors[] = compact('message', 'context');
            }
        };
        $app->instance('log', $logger);

        if (!Request::hasMacro('validate')) {
            Request::macro('validate', function (array $rules, ...$params) {
                return Validator::make($this->all(), $rules, ...$params)->validate();
            });
        }
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
        Facade::setFacadeApplication(null);

        parent::tearDown();
    }

    public function test_password_update_succeeds_and_logs_security_audit(): void
    {
        $user = new class extends User {
            public bool $saved = false;

            public function save(array $options = [])
            {
                $this->saved = true;

                return true;
            }
        };
        $user->id = 'user-123';
        $user->email = 'user@example.com';
        $user->password = Hash::make('CurrentPassword1!');

        $auditService = new class extends AuditService {
            public array $calls = [];

            public function log($user, string $event, string $resourceType, $resourceId = null, array $details = [])
            {
                $this->calls[] = compact('user', 'event', 'resourceType', 'resourceId', 'details');

                return null;
            }
        };

        $controller = new AuthController($auditService);
        $request = Request::create('/api/auth/password', 'PUT', [
            'current_password' => 'CurrentPassword1!',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->updatePassword($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['message' => 'Password updated successfully'], $response->getData(true));
        $this->assertTrue($user->saved);
        $this->assertTrue(Hash::check('NewPassword1!', $user->password));
        $this->assertCount(1, $auditService->calls);
        $this->assertSame('password_changed', $auditService->calls[0]['event']);
        $this->assertSame('user-123', $auditService->calls[0]['resourceId']);
    }

    public function test_password_update_rejects_wrong_current_password(): void
    {
        $user = new class extends User {
            public bool $saved = false;

            public function save(array $options = [])
            {
                $this->saved = true;

                return true;
            }
        };
        $user->id = 'user-456';
        $user->email = 'user@example.com';
        $user->password = Hash::make('CurrentPassword1!');

        $auditService = new class extends AuditService {
            public array $calls = [];

            public function log($user, string $event, string $resourceType, $resourceId = null, array $details = [])
            {
                $this->calls[] = compact('user', 'event', 'resourceType', 'resourceId', 'details');

                return null;
            }
        };

        $controller = new AuthController($auditService);
        $request = Request::create('/api/auth/password', 'PUT', [
            'current_password' => 'WrongPassword1!',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'NewPassword1!',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->updatePassword($request);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame(['message' => 'Current password is incorrect'], $response->getData(true));
        $this->assertFalse($user->saved);
        $this->assertTrue(Hash::check('CurrentPassword1!', $user->password));
        $this->assertCount(0, $auditService->calls);
    }

    public function test_password_update_requires_confirmation_match(): void
    {
        $user = new class extends User {
            public function save(array $options = [])
            {
                return true;
            }
        };
        $user->id = 'user-789';
        $user->email = 'user@example.com';
        $user->password = Hash::make('CurrentPassword1!');

        $auditService = new class extends AuditService {
            public array $calls = [];

            public function log($user, string $event, string $resourceType, $resourceId = null, array $details = [])
            {
                $this->calls[] = compact('user', 'event', 'resourceType', 'resourceId', 'details');

                return null;
            }
        };

        $controller = new AuthController($auditService);
        $request = Request::create('/api/auth/password', 'PUT', [
            'current_password' => 'CurrentPassword1!',
            'password' => 'NewPassword1!',
            'password_confirmation' => 'DifferentPassword1!',
        ]);
        $request->setUserResolver(fn () => $user);

        $this->expectException(ValidationException::class);

        $controller->updatePassword($request);
    }
}
