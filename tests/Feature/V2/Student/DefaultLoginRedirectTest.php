<?php

namespace Tests\Feature\V2\Student;

use App\Models\V2\Student;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * V2 student login is the default entry point, and an already-authenticated
 * user is never left staring at a login form - they're sent to their
 * dashboard (RedirectIfAuthenticated in AppServiceProvider).
 */
class DefaultLoginRedirectTest extends TestCase
{
    use DatabaseMigrations;

    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::disableForeignKeyConstraints();

        $schoolId = DB::table('v2_schools')->insertGetId(['name' => 'Sch', 'contact_email' => 's@s.edu', 'created_at' => now(), 'updated_at' => now()]);
        $this->studentId = DB::table('v2_students')->insertGetId(['school_id' => $schoolId, 'name' => 'Stu', 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function student(): Student
    {
        return Student::withoutGlobalScopes()->findOrFail($this->studentId);
    }

    public function test_root_redirects_to_the_student_login(): void
    {
        $this->get('/')->assertRedirect(route('v2.student.login'));
    }

    public function test_generic_login_name_redirects_to_the_student_login(): void
    {
        $this->get('/login')->assertRedirect(route('v2.student.login'));
    }

    public function test_guest_can_see_the_student_login(): void
    {
        $this->get(route('v2.student.login'))->assertOk();
    }

    public function test_authenticated_student_visiting_login_is_sent_to_dashboard(): void
    {
        $this->actingAs($this->student(), 'v2_student')
            ->get(route('v2.student.login'))
            ->assertRedirect(route('v2.student.dashboard'));
    }

    public function test_authenticated_student_visiting_root_is_sent_to_dashboard(): void
    {
        // / -> student login -> (already authed) -> dashboard
        $this->actingAs($this->student(), 'v2_student')
            ->get('/')
            ->assertRedirect(route('v2.student.dashboard'));
    }
}
