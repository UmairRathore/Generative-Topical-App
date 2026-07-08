<?php

namespace Tests\Feature\V2;

use App\Models\V2\Student;
use App\Support\SignedImage;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The signed-image endpoint: HMAC integrity, expiry, viewer binding and the
 * storage-path allowlist. A harvested/forged/expired URL must never serve.
 */
class SecureImageTest extends TestCase
{
    use DatabaseMigrations;

    private const PATH = 'v2/questions/9702/fig1.png';

    private int $schoolId;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();
        Storage::fake('public');
        Storage::disk('public')->put(self::PATH, 'fake-png-bytes');

        $this->schoolId  = DB::table('v2_schools')->insertGetId(['name' => 'Sch', 'contact_email' => 's@s.edu', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = DB::table('v2_students')->insertGetId(['school_id' => $this->schoolId, 'name' => 'Stu', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function actingAsStudent(): static
    {
        return $this->actingAs(Student::withoutGlobalScopes()->findOrFail($this->studentId), 'v2_student');
    }

    /** Signed params for an arbitrary token - lets each test tamper one field. */
    private function params(array $overrides = []): array
    {
        $p = array_merge([
            'p' => self::PATH,
            'u' => (string) $this->studentId,
            'r' => 'student',
            'e' => '',
            'q' => '7',
            'x' => time() + 600,
        ], $overrides);

        $p['s'] = $overrides['s'] ?? SignedImage::sign($p['p'], $p['u'], $p['r'], $p['e'], $p['q'], $p['x']);

        return array_filter($p, fn ($v) => $v !== '');
    }

    private function url(array $params): string
    {
        return route('v2.secure_image').'?'.http_build_query($params);
    }

    public function test_valid_viewer_bound_token_serves_the_image(): void
    {
        $this->actingAsStudent()->get($this->url($this->params()))->assertOk();
    }

    public function test_tampered_signature_is_rejected(): void
    {
        $params = $this->params();
        $params['s'] = str_repeat('0', 64); // forged HMAC

        $this->actingAsStudent()->get($this->url($params))->assertForbidden();
    }

    public function test_tampered_path_is_rejected(): void
    {
        // Valid signature for one path, then swap the path - HMAC must fail.
        $params = $this->params();
        $params['p'] = 'v2/questions/9702/other.png';

        $this->actingAsStudent()->get($this->url($params))->assertForbidden();
    }

    public function test_expired_token_is_rejected(): void
    {
        $this->actingAsStudent()
            ->get($this->url($this->params(['x' => time() - 10])))
            ->assertForbidden();
    }

    public function test_foreign_users_token_is_rejected_for_this_session(): void
    {
        // Correctly signed for user 999, but the session belongs to $studentId.
        $this->actingAsStudent()
            ->get($this->url($this->params(['u' => '999'])))
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_rejected_even_with_valid_token(): void
    {
        $this->get($this->url($this->params()))->assertForbidden();
    }

    public function test_path_outside_allowlist_is_rejected_even_when_signed(): void
    {
        $this->actingAsStudent()
            ->get($this->url($this->params(['p' => 'avatars/secret.png'])))
            ->assertForbidden();
    }

    public function test_path_traversal_is_rejected(): void
    {
        $this->actingAsStudent()
            ->get($this->url($this->params(['p' => 'v2/questions/../../.env'])))
            ->assertForbidden();
    }
}
