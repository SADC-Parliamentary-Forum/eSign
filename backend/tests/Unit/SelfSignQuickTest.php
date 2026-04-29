<?php

// Override Laravel helpers used inside `DocumentController::finishSelfSign()`
// so this test can run as a pure unit test without full app bootstrapping.
namespace App\Http\Controllers;

function response()
{
    return new class {
        public function json($data, $status = 200)
        {
            return ['data' => $data, 'status' => $status];
        }
    };
}

function app()
{
    return new class {
        public function isProduction(): bool
        {
            return false;
        }
    };
}

namespace Tests\Unit;

use App\Http\Controllers\DocumentController;
use App\Services\DocumentConversionService;
use App\Services\DocumentService;
use App\Services\SigningWorkflowService;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

class SelfSignQuickTest extends TestCase
{
    protected $app;

    protected function setUp(): void
    {
        parent::setUp();

        // Pure unit-test setup: no full Laravel bootstrapping.
        // We only provide a container for facades used by `finishSelfSign()`.
        $this->app = new Container();
        Facade::setFacadeApplication($this->app);

        // Auditing package (used by Eloquent models) relies on Config facade.
        $configRepo = new \Illuminate\Config\Repository([
            'audit' => ['enabled' => false],
        ]);
        $this->app->instance('config', $configRepo);
        $this->app->instance(\Illuminate\Contracts\Config\Repository::class, $configRepo);

        $dbMock = Mockery::mock();
        $dbMock->shouldReceive('transaction')
            ->andReturnUsing(function ($callback) {
                return $callback();
            });
        $this->app->instance('db', $dbMock);
    }

    protected function tearDown(): void
    {
        Facade::setFacadeApplication(null);
        Mockery::close();
        parent::tearDown();
    }

    public function test_apply_self_sign_date_field_preserves_custom_value(): void
    {
        $customDate = '2030-01-02';

        $dateField = new class($customDate) {
            public string $type = 'DATE';
            public ?string $text_value;
            public $signed_at = null;
            public array $updateCalls = [];

            public function __construct(string $textValue)
            {
                $this->text_value = $textValue;
            }

            public function update(array $payload): void
            {
                $this->updateCalls[] = $payload;

                if (array_key_exists('text_value', $payload)) {
                    $this->text_value = $payload['text_value'];
                }

                if (array_key_exists('signed_at', $payload)) {
                    $this->signed_at = $payload['signed_at'];
                }
            }
        };

        $documentService = Mockery::mock(DocumentService::class);
        $workflowService = Mockery::mock(SigningWorkflowService::class);
        $conversionService = Mockery::mock(DocumentConversionService::class);

        $controller = new class($documentService, $workflowService, $conversionService) extends DocumentController {
            public function applyDateFieldPublic($field): void
            {
                $this->applySelfSignDateField($field);
            }
        };

        call_user_func([$controller, 'applyDateFieldPublic'], $dateField);

        $this->assertSame($customDate, $dateField->text_value, 'DATE self-sign should preserve provided text_value.');
        $this->assertNotEmpty($dateField->updateCalls, 'Expected DATE field update to be called.');
        $this->assertNotNull($dateField->signed_at, 'Expected signed_at to be set.');
    }
}

