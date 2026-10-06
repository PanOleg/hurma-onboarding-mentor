<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_gaps', function (Blueprint $table) {
            $table->id();
            $table->string('question_normalized')->unique();
            $table->text('question_example');
            $table->unsignedInteger('occurrences')->default(1);
            $table->string('status', 15)->default('open');
            $table->foreignId('resolved_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->timestamp('last_asked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_gaps');
    }
};
