<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate from used_at, which means the invited person clicked the link -- a
     * different fact. This one means the e-mail itself went out, and is what lets a
     * retried delivery skip a send that already succeeded.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->timestamp('email_sent_at')->nullable();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('invites', function (Blueprint $table) {
            $table->dropColumn('email_sent_at');
        });
    }
};
