<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per processed message. The unique on message_id is what makes the
     * assistant idempotent: the same message is never answered twice.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assistant_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('message_id')->unique();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->string('provider', 30);
            $table->string('model', 100)->nullable();
            // running | completed | fallback
            $table->string('status', 20)->default('running');
            $table->unsignedSmallInteger('iterations')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('message_id')->references('id')->on('messages')->cascadeOnDelete();
            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // The per-user rate limit counts runs of a user within a window.
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assistant_runs');
    }
};
