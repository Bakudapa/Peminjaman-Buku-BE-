<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


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
}
