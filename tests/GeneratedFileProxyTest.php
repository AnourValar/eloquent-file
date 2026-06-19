<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Jobs\GenerateJob;
use AnourValar\EloquentFile\Services\FileService;
use Illuminate\Support\Facades\Bus;

/**
 * Covers the proxying of generated "side files" (a private preview), which exercises
 * the "generate" branches across FileVirtual::url_generate, PrivateVisibility::proxyUrl
 * and ControllerTrait::proxy().
 */
class GeneratedFileProxyTest extends AbstractSuite
{
    /**
     * @return \AnourValar\EloquentFile\FileVirtual
     */
    private function uploadWithPrivatePreview(): FileVirtual
    {
        // Force the generated preview onto the private (proxied) visibility
        config()->set('eloquent_file.file_physical.type.image.generate.preview.visibility', 'private');

        Bus::fake();
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        (new GenerateJob($fileVirtual->filePhysical))->handle(\App::make(FileService::class));

        return FileVirtual::with('filePhysical')->find($fileVirtual->id);
    }

    public function test_url_generate_uses_proxy_for_private_preview(): void
    {
        $fileVirtual = $this->uploadWithPrivatePreview();

        $this->assertArrayHasKey('preview', $fileVirtual->url_generate);
        // Proxied (signed) URL, not a direct storage URL
        $this->assertStringContainsString('/download/', $fileVirtual->url_generate['preview']);
        $this->assertStringContainsString('signature=', $fileVirtual->url_generate['preview']);
        $this->assertStringContainsString('generate=preview', $fileVirtual->url_generate['preview']);
    }

    public function test_proxy_download_of_generated_file(): void
    {
        $fileVirtual = $this->uploadWithPrivatePreview();

        $response = $this->get($fileVirtual->url_generate['preview']);

        $response->assertOk();
        $info = getimagesizefromstring($response->streamedContent());
        $this->assertSame('image/webp', $info['mime']);
    }
}
