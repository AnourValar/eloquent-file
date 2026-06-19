<?php

namespace AnourValar\EloquentFile\Tests;

use AnourValar\EloquentFile\FileVirtual;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleName;
use AnourValar\EloquentFile\Handlers\Models\FileVirtual\Name\SimpleTitleName;
use AnourValar\EloquentFile\Tests\Models\User;
use AnourValar\EloquentValidation\Exceptions\ValidationException;

class NameHandlerTest extends AbstractSuite
{
    public function test_simple_name_canonize_details_passthrough(): void
    {
        $this->assertSame(['a' => 1], (new SimpleName())->canonizeDetails(['a' => 1]));
        $this->assertNull((new SimpleName())->canonizeDetails(null));
    }

    public function test_simple_name_generate_fake_is_empty(): void
    {
        $user = $this->createUser();
        $this->assertSame([], (new SimpleName())->generateFake('user', 'avatar', $user));
    }

    public function test_simple_name_prohibits_title(): void
    {
        $user = $this->createUser();

        // "note" uses SimpleName -> title must be prohibited
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'note',
            'file_physical_id' => 1, 'filename' => 'x.txt', 'title' => 'not allowed',
        ]);

        $this->expectException(ValidationException::class);
        $fileVirtual->validate();
    }

    public function test_simple_title_name_generate_fake_has_title(): void
    {
        $user = $this->createUser();
        $fake = (new SimpleTitleName())->generateFake('user', 'document', $user);

        $this->assertArrayHasKey('title', $fake);
        $this->assertNotEmpty($fake['title']);
    }

    public function test_simple_title_name_requires_title(): void
    {
        $user = $this->createUser();

        // "document" uses SimpleTitleName -> title is required
        $fileVirtual = (new FileVirtual())->forceFill([
            'entity' => 'user', 'entity_id' => $user->id, 'name' => 'document',
            'file_physical_id' => 1, 'filename' => 'x.txt',
        ]);

        $this->expectException(ValidationException::class);
        $fileVirtual->validate();
    }

    public function test_simple_title_name_canonize_details_passthrough(): void
    {
        $this->assertSame('abc', (new SimpleTitleName())->canonizeDetails('abc'));
    }
}
