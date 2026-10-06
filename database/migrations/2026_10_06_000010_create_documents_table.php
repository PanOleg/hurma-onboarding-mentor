<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('original_filename');
            $table->string('mime', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64)->unique();
            $table->string('storage_path');
            $table->string('audience_type', 20);
            $table->string('audience_value', 100)->nullable();
            $table->string('status', 20)->default('uploaded');
            $table->string('failure_code', 40)->nullable();
            $table->text('failure_message')->nullable();
            $table->unsignedSmallInteger('version')->default(1);
            $table->unsignedInteger('chunks_count')->default(0);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
