<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_owner_can_login_read_session_and_logout(): void
    {
        $response = $this->withHeaders(['Origin'=>'http://localhost:8080','Referer'=>'http://localhost:8080/'])->postJson('/api/login', [
            'email'=>'owner@example.com','password'=>'password','remember'=>true,
        ]);
        $response->assertOk()->assertJsonPath('user.name','DerNait');
        $this->getJson('/api/me')->assertOk()->assertJsonPath('user.email','owner@example.com');
        $this->postJson('/api/logout')->assertOk();
        $this->getJson('/api/me')->assertUnauthorized();
    }

    public function test_invalid_login_is_rejected(): void
    {
        $this->withHeaders(['Origin'=>'http://localhost:8080','Referer'=>'http://localhost:8080/'])->postJson('/api/login',['email'=>'owner@example.com','password'=>'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
    }
}
