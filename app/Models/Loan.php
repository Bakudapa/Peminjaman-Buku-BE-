<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\LoanStatus;


class Loan extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'borrowed_at',
        'due_at',
        'returned_at',
        'status',
    ];
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    protected function casts(): array
    {
        return [
            'borrowed_at' => 'immutable_datetime',
            'due_at'      => 'immutable_datetime',
            'returned_at' => 'immutable_datetime',
            'status'      => LoanStatus::class,
        ];
    }

    public function isOverdue(): bool
    {
        return $this->status === \App\Enums\LoanStatus::Active
            && $this->due_at?->isPast();
    }
}
