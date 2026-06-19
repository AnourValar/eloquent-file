<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Handlers\Models\FilePhysical\Visibility\PrivateVisibility;
use AnourValar\EloquentFile\Tests\Support\TestController;

class ControllerTraitTest extends AbstractSuite
{
    public function test_upload_endpoint(): void
    {
        $user = $this->createUser();

        $response = $this->actingAs($user)->post(
            "/file/upload/user/{$user->id}/note",
            ['file' => $this->fixtureFile('document.txt')],
            ['Accept' => 'application/json']
        );

        $response->assertOk();
        $this->assertDatabaseHas('file_virtuals', ['entity' => 'user', 'entity_id' => $user->id, 'name' => 'note']);
        $this->assertNotNull($response->json('file_virtual.id'));
    }

    public function test_upload_requires_authorization(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();

        $this->actingAs($stranger)->post(
            "/file/upload/user/{$owner->id}/note",
            ['file' => $this->fixtureFile('document.txt')],
            ['Accept' => 'application/json']
        )->assertForbidden();

        $this->assertDatabaseCount('file_virtuals', 0);
    }

    public function test_upload_without_file_fails(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post(
            "/file/upload/user/{$user->id}/note",
            [],
            ['Accept' => 'application/json']
        )->assertStatus(422);
    }

    public function test_upload_rejects_multiple_files(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->post(
            "/file/upload/user/{$user->id}/note",
            ['a' => $this->fixtureFile('document.txt'), 'b' => $this->fixtureFile('document2.txt')],
            ['Accept' => 'application/json']
        )->assertStatus(422);
    }

    public function test_upload_from_url_endpoint(): void
    {
        $user = $this->createUser();
        TestController::$remoteContent = file_get_contents($this->fixturePath('document.txt'));

        $this->actingAs($user)->postJson('/file/upload-url', [
            'url' => 'http://example.com/files/document.txt',
            'entity' => 'user',
            'entity_id' => $user->id,
            'name' => 'note',
        ])->assertOk();

        $this->assertDatabaseHas('file_virtuals', ['entity_id' => $user->id, 'name' => 'note', 'filename' => 'document.txt']);
    }

    public function test_upload_from_url_missing_remote(): void
    {
        $user = $this->createUser();
        TestController::$remoteContent = false;

        $this->actingAs($user)->postJson('/file/upload-url', [
            'url' => 'http://example.com/files/document.txt',
            'entity' => 'user',
            'entity_id' => $user->id,
            'name' => 'note',
        ])->assertStatus(422);
    }

    public function test_delete_endpoint(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
        ]);

        $this->actingAs($user)->post(
            "/file/delete/{$fileVirtual->id}",
            [],
            ['Accept' => 'application/json']
        )->assertOk();

        $this->assertDatabaseMissing('file_virtuals', ['id' => $fileVirtual->id]);
    }

    public function test_delete_requires_authorization(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $owner->id, 'name' => 'note',
        ]);

        $this->actingAs($stranger)->post(
            "/file/delete/{$fileVirtual->id}",
            [],
            ['Accept' => 'application/json']
        )->assertForbidden();

        $this->assertDatabaseHas('file_virtuals', ['id' => $fileVirtual->id]);
    }

    public function test_download_signed_url(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document', 'title' => 'Doc',
        ]);
        $url = FileVirtual::with('filePhysical')->find($fileVirtual->id)->url;

        $response = $this->get($url);

        $response->assertOk();
        $this->assertSame(file_get_contents($this->fixturePath('document.txt')), $response->streamedContent());
    }

    public function test_download_signed_url_invalid_signature(): void
    {
        $user = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document', 'title' => 'Doc',
        ]);

        // No signature at all
        $this->getJson("/file/{$fileVirtual->id}/download/doc.txt")->assertForbidden();
    }

    public function test_download_signed_url_decrypts_adapter_content(): void
    {
        $user = $this->createUser();
        // "scan" -> private_encrypt (AdapterInterface) signed download
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'scan',
        ]);
        $url = FileVirtual::with('filePhysical')->find($fileVirtual->id)->url;

        $response = $this->get($url);

        $response->assertOk();
        $this->assertSame(file_get_contents($this->fixturePath('document.txt')), $response->streamedContent());
    }

    public function test_delete_missing_returns_404(): void
    {
        $user = $this->createUser();

        $this->actingAs($user)->postJson('/file/delete/999999')->assertNotFound();
    }

    public function test_download_missing_returns_404(): void
    {
        $this->getJson('/file/999999/download/x.txt')->assertNotFound();
    }

    public function test_upload_from_url_rejects_non_http(): void
    {
        $user = $this->createUser();
        TestController::$remoteContent = 'whatever';

        // Not an http(s) url -> treated as missing
        $this->actingAs($user)->postJson('/file/upload-url', [
            'url' => '/etc/passwd',
            'entity' => 'user',
            'entity_id' => $user->id,
            'name' => 'note',
        ])->assertStatus(422);
    }

    public function test_download_generate_unsupported(): void
    {
        config()->set('eloquent_file.file_physical.visibility.private.proxy_route', 'file.download.auth');
        config()->set(
            'eloquent_file.file_physical.visibility.private.proxy_route_method',
            PrivateVisibility::METHOD_USER_AUTHORIZE
        );

        $owner = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $owner->id, 'name' => 'document', 'title' => 'Doc',
        ]);

        // Request a non-existent generated variant
        $this->actingAs($owner)
            ->getJson("/file-auth/{$fileVirtual->id}/download/doc.txt?generate=missing")
            ->assertForbidden();
    }

    public function test_download_user_authorize(): void
    {
        config()->set('eloquent_file.file_physical.visibility.private.proxy_route', 'file.download.auth');
        config()->set(
            'eloquent_file.file_physical.visibility.private.proxy_route_method',
            PrivateVisibility::METHOD_USER_AUTHORIZE
        );

        $owner = $this->createUser();
        $fileVirtual = $this->uploadFixture('document.txt', [
            'entity' => 'user', 'entity_id' => $owner->id, 'name' => 'document', 'title' => 'Doc',
        ]);
        $url = FileVirtual::with('filePhysical')->find($fileVirtual->id)->url;

        // Owner is authorized
        $this->actingAs($owner)->get($url)->assertOk();

        // Stranger is not
        $stranger = $this->createUser();
        $this->actingAs($stranger)->getJson($url)->assertForbidden();
    }
}
