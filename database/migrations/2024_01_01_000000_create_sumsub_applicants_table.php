<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sumsub_applicants', function (Blueprint $table): void {
            $table->increments('id');

            // Must match the type of users.id — unsignedInteger for increments(), unsignedBigInteger for id().
            $table->unsignedInteger('user_id')->index();
            $table->string('applicant_id')->unique()->comment('Sumsub applicant ID (e.g. 5cb56e8e0a975a35f333cb83)');
            $table->string('level_name')->comment('KYC verification level name in Sumsub');
            $table->string('review_status')->nullable()->comment('e.g. init, pending, prechecked, queued, completed, onHold');
            $table->string('review_answer')->nullable()->comment('e.g. GREEN, RED, RETRY');
            $table->json('raw_data')->nullable()->comment('Full webhook or API payload for debugging');

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sumsub_applicants');
    }
};
