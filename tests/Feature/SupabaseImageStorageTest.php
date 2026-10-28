<?php

namespace Tests\Feature;

use App\Services\Media\StoredImage;
use App\Services\Media\SupabaseImageStorage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SupabaseImageStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['media.driver' => 'supabase', 'media.supabase.url' => 'https://testproject.supabase.co',
            'media.supabase.bucket' => 'tour-images', 'media.supabase.secret_key' => 'sb_secret_test_only']);
        Http::preventStrayRequests();
    }

    private function photo(): array
    {
        $filename = public_path('images/tours/demo/da-nang-2.jpg');
        $bytes = file_get_contents($filename);
        $hash = hash('sha256', $bytes);
        $path = 'media/'.substr($hash, 0, 2).'/'.$hash.'.jpg';

        return [$filename, $bytes, $hash, $path];
    }

    public function test_upload_uses_server_apikey_only_and_verifies_public_bytes_without_credentials(): void
    {
        [$filename, $bytes, $hash, $path] = $this->photo();
        $uploadedHash = null;
        Http::fake([
            'https://testproject.supabase.co/storage/v1/object/tour-images/*' => function (Request $request) use (&$uploadedHash, $path) {
                $uploadedHash = hash('sha256', $request->body());

                return Http::response(['Key' => 'tour-images/'.$path], 200);
            },
            'https://testproject.supabase.co/storage/v1/object/public/tour-images/*' => Http::response($bytes, 200, ['Content-Type' => 'image/jpeg']),
        ]);
        $stored = app(SupabaseImageStorage::class)->store($filename, $hash);
        $this->assertSame('supabase', $stored->disk);
        $this->assertSame('tour-images', $stored->bucket);
        $this->assertSame($path, $stored->path);
        $this->assertSame('https://testproject.supabase.co/storage/v1/object/public/tour-images/'.$path, $stored->url);
        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->hasHeader('apikey', 'sb_secret_test_only') && ! $request->hasHeader('Authorization')
            && $request->hasHeader('x-upsert', 'false') && $request->hasHeader('Content-Type', 'image/jpeg'));
        $this->assertSame($hash, $uploadedHash);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === $stored->url
            && ! $request->hasHeader('apikey') && ! $request->hasHeader('Authorization'));
        Http::assertSentCount(2);
    }

    public static function duplicateStatuses(): array
    {
        return [[400], [409]];
    }

    #[DataProvider('duplicateStatuses')]
    public function test_existing_content_is_reused_only_after_checksum_verification(int $status): void
    {
        [$filename, $bytes, $hash] = $this->photo();
        Http::fake([
            'https://testproject.supabase.co/storage/v1/object/tour-images/*' => Http::response(['error' => 'Duplicate', 'message' => 'The resource already exists'], $status),
            'https://testproject.supabase.co/storage/v1/object/public/tour-images/*' => Http::response($bytes),
        ]);
        $stored = app(SupabaseImageStorage::class)->store($filename, $hash);
        $this->assertSame($hash, $stored->sha256);
        Http::assertSentCount(2);
        Http::assertNotSent(fn (Request $request) => $request->hasHeader('x-upsert', 'true'));
    }

    public function test_wrong_source_hash_is_rejected_before_contacting_supabase(): void
    {
        Http::fake();
        try {
            app(SupabaseImageStorage::class)->store($this->photo()[0], str_repeat('0', 64));
            $this->fail('Expected checksum rejection');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SHA-256', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_publishable_key_is_rejected_before_network_access(): void
    {
        config(['media.supabase.secret_key' => 'sb_publishable_not_a_server_key']);
        Http::fake();
        try {
            app(SupabaseImageStorage::class)->store($this->photo()[0]);
            $this->fail('Expected key rejection');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SUPABASE_SECRET_KEY', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_bad_public_download_is_rejected_even_when_upload_succeeds(): void
    {
        Http::fake([
            'https://testproject.supabase.co/storage/v1/object/tour-images/*' => Http::response(['Key' => 'ok']),
            'https://testproject.supabase.co/storage/v1/object/public/tour-images/*' => Http::response('incorrect content'),
        ]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Không xác minh được ảnh công khai');
        app(SupabaseImageStorage::class)->store($this->photo()[0]);
    }

    public function test_failed_upload_does_not_publish_a_url_or_expose_response_body(): void
    {
        Http::fake(['*' => Http::response(['message' => 'do not echo secret: sb_secret_test_only'], 403)]);
        try {
            app(SupabaseImageStorage::class)->store($this->photo()[0]);
            $this->fail('Expected upload rejection');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 403', $exception->getMessage());
            $this->assertStringNotContainsString('sb_secret_', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_secret_is_never_sent_to_an_unapproved_host(): void
    {
        config(['media.supabase.url' => 'https://example.test']);
        Http::fake();
        try {
            app(SupabaseImageStorage::class)->store($this->photo()[0]);
            $this->fail('Expected project URL rejection');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('SUPABASE_URL', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_upload_redirect_is_not_followed_with_a_server_secret(): void
    {
        Http::fake([
            'https://testproject.supabase.co/*' => Http::response('', 307, ['Location' => 'https://example.test/leak']),
            'https://example.test/*' => Http::response('must not be requested'),
        ]);
        try {
            app(SupabaseImageStorage::class)->store($this->photo()[0]);
            $this->fail('Expected redirect rejection');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 307', $exception->getMessage());
        }
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $request) => str_starts_with($request->url(), 'https://example.test/'));
    }

    public function test_verification_rejects_a_url_from_a_different_project(): void
    {
        Http::fake();
        $this->expectException(RuntimeException::class);
        app(SupabaseImageStorage::class)->verify(new StoredImage('supabase', 'tour-images', 'media/photo.jpg',
            'https://otherproject.supabase.co/storage/v1/object/public/tour-images/media/photo.jpg', str_repeat('0', 64)));
    }
}
