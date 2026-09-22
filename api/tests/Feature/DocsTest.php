<?php

namespace Tests\Feature;

use Tests\TestCase;

class DocsTest extends TestCase
{
    public function test_swagger_ui_page_loads(): void
    {
        $this->get('/docs')->assertStatus(200)->assertSee('swagger-ui', false);
    }

    public function test_openapi_spec_is_served_and_well_formed(): void
    {
        $response = $this->get('/docs/openapi.yaml');
        $response->assertStatus(200);

        $contents = $response->streamedContent();
        $this->assertStringContainsString('openapi: 3.0.3', $contents);
        $this->assertStringContainsString('/buckets/{bucket}/objects', $contents);
    }
}
