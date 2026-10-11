<?php

namespace Tests\Feature;

use App\Support\ImagePath;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicImageTest extends TestCase
{
    public function test_avatar_is_available_without_a_public_storage_symlink(): void
    {
        Storage::fake('public');
        config()->set('imagepath.disk', 'public');
        config()->set('imagepath.public_base_url', 'http://localhost/storage');
        config()->set('app.url', 'http://localhost');

        Storage::disk('public')->put('users/avatars/customer.png', 'image-bytes');

        $this->assertSame(
            'http://localhost/media/users/avatars/customer.png',
            ImagePath::url('users/avatars/customer.png')
        );

        $this->get('/media/users/avatars/customer.png')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_private_and_missing_files_are_not_served(): void
    {
        Storage::fake('public');
        config()->set('imagepath.disk', 'public');

        Storage::disk('public')->put('support/messages/private.png', 'private-image');

        $this->get('/media/support/messages/private.png')->assertNotFound();
        $this->get('/media/users/avatars/missing.png')->assertNotFound();
    }
}
