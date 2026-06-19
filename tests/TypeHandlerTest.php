<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\ImageType;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Type\SimpleType;
use AnourValar\EloquentFile\Jobs\GenerateJob;
use AnourValar\EloquentFile\Jobs\OnZeroJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

class TypeHandlerTest extends AbstractSuite
{
    public function test_simple_type_validate_is_noop(): void
    {
        $validator = \Validator::make([], []);
        (new SimpleType())->validate([], $validator);
        $this->assertTrue($validator->passes());
    }

    public function test_simple_type_on_zero_deletes_physical(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $filePhysical = $fileVirtual->filePhysical;
        $fileVirtual->delete(); // unlink so it can be deleted by onZero

        $filePhysical->refresh();
        (new SimpleType())->onZero($filePhysical);

        $this->assertDatabaseMissing('file_physicals', ['id' => $filePhysical->id]);
    }

    public function test_simple_type_dispatch_on_zero(): void
    {
        Bus::fake();
        $filePhysical = (new FilePhysical())->forceFill([
            'visibility' => 'private_encrypt', 'type' => 'simple', 'sha256' => str_repeat('a', 64),
        ]);

        (new SimpleType())->dispatchOnZero($filePhysical);

        Bus::assertDispatched(OnZeroJob::class);
    }

    public function test_image_type_keep_original(): void
    {
        $filePhysical = (new FilePhysical())->forceFill(['type' => 'image']);
        $this->assertTrue((new ImageType())->keepOriginal($filePhysical));
    }

    public function test_image_type_dispatch_generate(): void
    {
        Bus::fake();
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        (new ImageType())->dispatchGenerate($fileVirtual->filePhysical);

        Bus::assertDispatched(GenerateJob::class);
    }

    public function test_image_type_generate_produces_preview(): void
    {
        Bus::fake(); // suppress the automatic GenerateJob from the upload
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);
        $filePhysical = $fileVirtual->filePhysical;

        $pathGenerate = (new ImageType())->generate($filePhysical);

        $this->assertArrayHasKey('preview', $pathGenerate);
        $this->assertSame('public', $pathGenerate['preview']['visibility']);
        $this->assertSame('image/webp', $pathGenerate['preview']['mime_type']);
        $this->assertStringEndsWith('_preview.webp', $pathGenerate['preview']['path']);

        Storage::disk($pathGenerate['preview']['disk'])->assertExists($pathGenerate['preview']['path']);

        // generated file must be a valid webp image
        $content = Storage::disk($pathGenerate['preview']['disk'])->get($pathGenerate['preview']['path']);
        $info = getimagesizefromstring($content);
        $this->assertSame('image/webp', $info['mime']);
        $this->assertLessThanOrEqual(1000, $info[0]);
        $this->assertLessThanOrEqual(1000, $info[1]);
    }

    public function test_image_type_generate_jpg_format(): void
    {
        Bus::fake();
        config()->set('eloquent_file.file_physical.type.image.generate.preview.format', 'jpg');

        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        $pathGenerate = (new ImageType())->generate($fileVirtual->filePhysical);

        $this->assertSame('image/jpeg', $pathGenerate['preview']['mime_type']);
        $this->assertStringEndsWith('_preview.jpg', $pathGenerate['preview']['path']);

        $content = Storage::disk($pathGenerate['preview']['disk'])->get($pathGenerate['preview']['path']);
        $this->assertSame('image/jpeg', getimagesizefromstring($content)['mime']);
    }

    public function test_image_type_generate_png_format(): void
    {
        Bus::fake();
        config()->set('eloquent_file.file_physical.type.image.generate.preview.format', 'png');

        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        $pathGenerate = (new ImageType())->generate($fileVirtual->filePhysical);

        $this->assertSame('image/png', $pathGenerate['preview']['mime_type']);
    }

    public function test_image_type_generate_unsupported_format(): void
    {
        Bus::fake();
        config()->set('eloquent_file.file_physical.type.image.generate.preview.format', 'tiff');

        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('image.png', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'gallery',
        ]);

        $this->expectException(\LogicException::class);
        (new ImageType())->generate($fileVirtual->filePhysical);
    }
}
