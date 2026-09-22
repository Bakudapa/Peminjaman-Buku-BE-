<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->index('category');
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->index('book_id');
            $table->index(['member_id', 'status']);
            $table->index(['status', 'due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['category']);
        });

        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndex(['book_id']);
            $table->dropIndex(['member_id', 'status']);
            $table->dropIndex(['status', 'due_at']);
        });
    }
};