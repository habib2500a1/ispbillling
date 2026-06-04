<?php

namespace Tests\Feature;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class ResellerOpenApiDocTest extends TestCase
{
    public function test_reseller_openapi_spec_is_valid_yaml_with_core_paths(): void
    {
        $path = public_path('docs/reseller-openapi.yaml');
        $this->assertFileExists($path);

        $spec = Yaml::parseFile($path);
        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame('1.2.0', $spec['info']['version']);
        $this->assertArrayHasKey('/reseller/login', $spec['paths']);
        $this->assertArrayHasKey('/reseller/dashboard', $spec['paths']);
        $this->assertArrayHasKey('sanctumBearer', $spec['components']['securitySchemes']);
        $this->assertArrayHasKey('apiKeyBearer', $spec['components']['securitySchemes']);
        $this->assertArrayHasKey('CustomerCreateRequest', $spec['components']['schemas']);
        $this->assertArrayHasKey('/reseller/customers/{customer}/payments', $spec['paths']);
        $this->assertArrayHasKey('/reseller/staff/{staffMember}', $spec['paths']);
        $this->assertArrayHasKey('/reseller/wallet/recharge', $spec['paths']);
        $this->assertArrayHasKey('StaffCreateRequest', $spec['components']['schemas']);
    }

    public function test_sdk_generator_outputs_exist(): void
    {
        $this->assertDirectoryExists(base_path('mobile/reseller_sdk/dart/generated/lib'));
        $this->assertDirectoryExists(base_path('mobile/reseller_sdk/kotlin/generated/src/main/kotlin'));
    }

    public function test_reseller_openapi_routes_serve_public_docs(): void
    {
        $this->get(route('docs.reseller-api'))
            ->assertOk()
            ->assertHeader('content-type', 'text/html; charset=UTF-8');

        $this->get(route('docs.reseller-openapi'))
            ->assertOk()
            ->assertHeader('content-type', 'application/yaml');
    }
}
