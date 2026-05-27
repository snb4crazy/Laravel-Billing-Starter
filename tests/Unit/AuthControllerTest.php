<?php

namespace Tests\Unit;

use App\Http\Controllers\Auth\AuthController;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    public function test_logout_does_not_fail_when_current_access_token_is_null(): void
    {
        $user = new User();
        $user->id = 123;
        $user->email = 'session-user@example.com';

        $request = Request::create('/api/auth/logout', 'POST');
        $request->setUserResolver(static fn () => $user);

        $response = (new AuthController())->logout($request);

        $this->assertEquals(200, $response->status());
    }

    public function test_sanctum_guard_is_configured(): void
    {
        $this->assertSame('sanctum', config('auth.guards.sanctum.driver'));
        $this->assertSame('users', config('auth.guards.sanctum.provider'));
    }
}

