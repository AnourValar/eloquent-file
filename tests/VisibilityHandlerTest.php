<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FilePhysical;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\AdapterInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\DirectAccessInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateEncryptVisibility;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateVisibility;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\ProxyAccessInterface;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PublicVisibility;
use Illuminate\Support\Facades\Storage;

class VisibilityHandlerTest extends AbstractSuite
{
    public function test_get_disk_picks_from_list(): void
    {
        $disk = (new PublicVisibility())->getDisk(['s3_public']);
        $this->assertSame('s3_public', $disk);

        $disk = (new PublicVisibility())->getDisk(['a', 'b', 'c']);
        $this->assertContains($disk, ['a', 'b', 'c']);
    }

    public function test_get_path_builds_sharded_path(): void
    {
        $sha = str_repeat('ab', 32); // 64 chars
        $filePhysical = (new FilePhysical())->forceFill(['sha256' => $sha]);
        $filePhysical->id = 7;

        $path = (new PublicVisibility())->getPath($filePhysical, $this->fixtureFile('image.png'));

        $this->assertSame('ab/ab/ab/'.$sha.'7.png', $path);
    }

    public function test_get_path_without_extension(): void
    {
        $sha = str_repeat('cd', 32);
        $filePhysical = (new FilePhysical())->forceFill(['sha256' => $sha]);
        $filePhysical->id = 3;

        $path = (new PublicVisibility())->getPath($filePhysical, $this->fixtureFile('image.png', 'noext'));

        $this->assertSame('cd/cd/cd/'.$sha.'3', $path);
    }

    public function test_get_path_requires_sha_and_id(): void
    {
        $this->expectException(\LogicException::class);
        (new PublicVisibility())->getPath(new FilePhysical(), $this->fixtureFile('image.png'));
    }

    public function test_public_is_direct_access(): void
    {
        $handler = (new PublicVisibility());
        $this->assertInstanceOf(DirectAccessInterface::class, $handler);

        Storage::fake('s3_public');
        Storage::disk('s3_public')->put('foo/bar.txt', 'x');

        $url = $handler->directUrl('s3_public', 'foo/bar.txt');
        $this->assertStringContainsString('foo/bar.txt', $url);
    }

    public function test_private_proxy_url_signed(): void
    {
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $this->createUser()->id, 'name' => 'document', 'title' => 'My doc',
        ]);
        $fileVirtual->load('filePhysical');

        $url = (new PrivateVisibility())->proxyUrl($fileVirtual);

        $this->assertStringContainsString('/file/'.$fileVirtual->id.'/download/', $url);
        $this->assertStringContainsString('signature=', $url);
    }

    public function test_private_proxy_url_user_authorize(): void
    {
        config()->set('eloquent_file.file_physical.visibility.private.proxy_route', 'file.download.auth');
        config()->set(
            'eloquent_file.file_physical.visibility.private.proxy_route_method',
            PrivateVisibility::METHOD_USER_AUTHORIZE
        );

        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $this->createUser()->id, 'name' => 'document', 'title' => 'My doc',
        ]);
        $fileVirtual->load('filePhysical');

        $url = (new PrivateVisibility())->proxyUrl($fileVirtual);

        $this->assertStringContainsString('/file-auth/'.$fileVirtual->id.'/download/', $url);
        $this->assertStringNotContainsString('signature=', $url);
    }

    public function test_private_proxy_url_signed_direct(): void
    {
        config()->set(
            'eloquent_file.file_physical.visibility.private.proxy_route_method',
            PrivateVisibility::METHOD_URL_SIGNED_DIRECT
        );

        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $this->createUser()->id, 'name' => 'document', 'title' => 'My doc',
        ]);
        $fileVirtual->load('filePhysical');

        $url = (new PrivateVisibility())->proxyUrl($fileVirtual);

        // Storage::fake temporaryUrl returns a url containing an expiration timestamp
        $this->assertStringContainsString('expiration=', $url);
    }

    public function test_private_proxy_url_invalid_method(): void
    {
        config()->set('eloquent_file.file_physical.visibility.private.proxy_route_method', 'nonsense');

        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $this->createUser()->id, 'name' => 'document', 'title' => 'My doc',
        ]);
        $fileVirtual->load('filePhysical');

        $this->expectException(\LogicException::class);
        (new PrivateVisibility())->proxyUrl($fileVirtual);
    }

    public function test_private_encrypt_is_adapter(): void
    {
        $handler = new PrivateEncryptVisibility();
        $this->assertInstanceOf(AdapterInterface::class, $handler);
        $this->assertInstanceOf(ProxyAccessInterface::class, $handler);

        Storage::fake('s3_private');
        $handler->putFile('s3_private', 'secret/file.bin', 'plain-content');

        // Content on disk is encrypted (not equal to plaintext) ...
        $this->assertNotSame('plain-content', Storage::disk('s3_private')->get('secret/file.bin'));
        // ... but the adapter decrypts it back
        $this->assertSame('plain-content', $handler->getFile('s3_private', 'secret/file.bin'));
    }
}
