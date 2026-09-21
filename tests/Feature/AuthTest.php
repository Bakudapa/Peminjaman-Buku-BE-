<?php
// tests/Feature/AuthTest.php

use App\Models\User;

// FR-5
it('registers a new member and returns a token', function () {
    $response = $this->postJson('/api/register', [
        'name'                  => 'Budi Anggota',
        'email'                 => 'budi@mail.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['token']); // sesuaikan key sesuai response AuthController-mu

    $this->assertDatabaseHas('users', ['email' => 'budi@mail.com']);
});

it('hashes password on registration', function () {
    $this->postJson('/api/register', [
        'name'                  => 'Cek Hash',
        'email'                 => 'cekhash@mail.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'cekhash@mail.com')->first();

    expect($user->password)->not->toBe('password123');
});

it('logs in with correct credentials and returns a token', function () {
    User::factory()->create([
        'email'    => 'login@mail.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'email'    => 'login@mail.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token']);
});

it('rejects login with wrong password', function () {
    User::factory()->create([
        'email'    => 'salah@mail.com',
        'password' => bcrypt('password123'),
    ]);

    $this->postJson('/api/login', [
        'email'    => 'salah@mail.com',
        'password' => 'passwordsalah',
    ])->assertStatus(401); // sesuaikan kalau AuthController-mu return status beda
});

// FR-6
it('rejects requests without a valid token', function () {
    $this->getJson('/api/loans')
        ->assertStatus(401);
});

it('rejects requests with an invalid token', function () {
    $this->withHeader('Authorization', 'Bearer token-ngasal')
        ->getJson('/api/loans')
        ->assertStatus(401);
});