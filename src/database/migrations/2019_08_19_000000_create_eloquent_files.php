<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(null)->create('file_physicals', function (Blueprint $table) {
            $table->id();
            $table->string('visibility', 30)->nullable();
            $table->string('type', 30)->nullable();
            $table->string('disk', 30)->nullable();
            $table->string('path', 200)->nullable();
            $table->string('path_generate', 2000)->nullable();
            $table->string('sha256', 64)->nullable();
            $table->unsignedInteger('size')->nullable()->index();
            $table->string('mime_type', 100)->nullable();
            $table->boolean('linked')->nullable()->index();
            $table->unsignedTinyInteger('build')->nullable();
            $table->timestamps();

            $table->index(['visibility', 'type', 'sha256']);
        });

        Schema::connection(null)->create('file_virtuals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_physical_id')->index();
            $table->string('entity', 30);
            $table->unsignedBigInteger('entity_id');
            $table->string('name', 40)->index();
            $table->string('filename', 100);
            $table->string('content_type', 100)->nullable();
            $table->string('title', 150)->nullable();
            $table->unsignedSmallInteger('weight')->index();
            $table->jsonb('details')->nullable();
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();

            $table->index(['entity', 'entity_id']);
            $table->index('created_at');

            //$table->foreign('file_physical_id')->references('id')->on('file_physicals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection(null)->dropIfExists('file_virtuals');
        Schema::connection(null)->dropIfExists('file_physicals');
    }
};
