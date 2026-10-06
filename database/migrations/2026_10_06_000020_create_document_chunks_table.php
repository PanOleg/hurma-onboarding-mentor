<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE document_chunks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                document_id BIGINT UNSIGNED NOT NULL,
                position SMALLINT UNSIGNED NOT NULL,
                page SMALLINT UNSIGNED NULL,
                heading VARCHAR(255) NULL,
                content TEXT NOT NULL,
                token_count SMALLINT UNSIGNED NOT NULL,
                embedding VECTOR(384) NOT NULL,
                created_at TIMESTAMP NULL,
                updated_at TIMESTAMP NULL,
                VECTOR INDEX (embedding) M=8 DISTANCE=cosine,
                INDEX idx_document_position (document_id, position),
                CONSTRAINT fk_chunks_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('document_chunks');
    }
};
