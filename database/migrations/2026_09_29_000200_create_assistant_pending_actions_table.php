<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A confirmation the user still has to give. It lives in its own table, not in
     * the message, because a message is immutable and this is mutable state with an
     * expiry and a single use.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assistant_pending_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('conversation_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('delivery_id');
            // The message that asked "do you confirm?".
            $table->unsignedBigInteger('message_id')->nullable();
            $table->string('action', 30)->default('cancel_delivery');
            // pending | confirmed | rejected | expired | failed | superseded
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('conversations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('delivery_id')->references('id')->on('deliveries')->cascadeOnDelete();
            $table->foreign('message_id')->references('id')->on('messages')->nullOnDelete();

            $table->index(['user_id', 'delivery_id', 'status']);
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assistant_pending_actions');
    }
};
