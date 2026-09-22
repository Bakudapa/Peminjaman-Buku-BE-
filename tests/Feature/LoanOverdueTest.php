<?php

use App\Enums\LoanStatus;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

afterEach(fn () => Carbon::setTestNow());

it('overdue scope agrees with isOverdue()', function () {
    Carbon::setTestNow('2026-09-21 12:00:00');

    $overdue = Loan::factory()->create([
        'status' => LoanStatus::Active,
        'due_at' => now()->subDay(),
        'returned_at' => null,
    ]);

    Loan::factory()->create([               // belum jatuh tempo
        'status' => LoanStatus::Active,
        'due_at' => now()->addDay(),
        'returned_at' => null,
    ]);

    Loan::factory()->create([               // sudah dikembalikan, walau lewat due_at
        'status' => LoanStatus::Returned,
        'due_at' => now()->subDay(),
        'returned_at' => now()->subHours(2),
    ]);

    Loan::factory()->create([               // batas: due_at tepat sekarang
        'status' => LoanStatus::Active,
        'due_at' => now(),
        'returned_at' => null,
    ]);

    $ids = Loan::overdue()->pluck('id');

    expect($ids->all())->toEqual([$overdue->id]);

    // dua sumber kebenaran harus sepakat untuk SETIAP loan
    Loan::all()->each(function (Loan $loan) use ($ids) {
        expect($loan->isOverdue())->toBe($ids->contains($loan->id));
    });
});

it('lets an admin filter overdue loans over HTTP', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

    $overdue = Loan::factory()->create([
        'status' => LoanStatus::Active,
        'due_at' => now()->subDay(),
        'returned_at' => null,
    ]);
    Loan::factory()->create([
        'status' => LoanStatus::Active,
        'due_at' => now()->addDay(),
        'returned_at' => null,
    ]);

    $response = $this->getJson('/api/loans?overdue=true');



    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $overdue->id);

    $this->getJson('/api/loans?overdue=abc')->assertStatus(422);
});

it('forbids members from listing all loans', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'member']));

    $this->getJson('/api/loans')->assertForbidden();
});

it('lists only my own loans', function () {
    $me = User::factory()->create(['role' => 'member']);
    $other = User::factory()->create(['role' => 'member']);

    $mine = Loan::factory()->create(['member_id' => $me->id]);
    Loan::factory()->create(['member_id' => $other->id]);

    Sanctum::actingAs($me);

    $this->getJson('/api/loans/me')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id);
});

it('returns 404 for a non-numeric loan id', function () {
    Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

    $this->getJson('/api/loans/abc')->assertNotFound();
});