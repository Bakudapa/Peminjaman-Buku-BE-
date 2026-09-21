<?php
// tests/Feature/UserCrudTest.php

use App\Models\User;

it('allows admin to list users', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    User::factory()->count(3)->create();

    $this->actingAs($admin)->getJson('/api/users')
        ->assertStatus(200);
});

it('rejects non-admin from listing users', function () {
    $member = User::factory()->create(['role' => 'member']);

    $this->actingAs($member)->getJson('/api/users')
        ->assertStatus(403);
});

it('allows admin to create a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->postJson('/api/users', [
        'name'     => 'Anggota Baru',
        'email'    => 'anggota@mail.com',
        'password' => 'password123',
        'role'     => 'member',
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('users', ['email' => 'anggota@mail.com']);
});

it('hashes the password when a user is created', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)->postJson('/api/users', [
        'name'     => 'Cek Hash',
        'email'    => 'hash@mail.com',
        'password' => 'password123',
        'role'     => 'member',
    ]);

    $user = User::where('email', 'hash@mail.com')->first();

    expect($user->password)->not->toBe('password123');
});

it('allows admin to update a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create(['name' => 'Nama Lama']);

    $this->actingAs($admin)->putJson("/api/users/{$target->id}", [
        'name' => 'Nama Baru',
    ])->assertStatus(200);

    $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => 'Nama Baru']);
});

it('allows admin to delete a user', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $target = User::factory()->create();

    $this->actingAs($admin)->deleteJson("/api/users/{$target->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});