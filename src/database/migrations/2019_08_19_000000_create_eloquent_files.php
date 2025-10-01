<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    //protected $connection = 'pgsql';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection(null)->create('file_physicals', function (Blueprint $table) {
            $table->id();
            $table->string('visibility')->nullable();
            $table->string('type')->nullable();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('path_generate')->nullable();
            $table->string('sha256')->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->string('mime_type')->nullable();
            $table->boolean('linked')->index();
            $table->timestamps();

            $table->index(['visibility', 'type', 'sha256']);
        });

        Schema::connection(null)->create('file_virtuals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_physical_id')->index();
            $table->string('entity');
            $table->unsignedBigInteger('entity_id');
            $table->string('name')->index();
            $table->string('filename');
            $table->string('title')->nullable();
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
