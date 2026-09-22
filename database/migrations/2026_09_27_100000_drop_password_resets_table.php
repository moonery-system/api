<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The table came with the Laravel skeleton and is referenced nowhere: password
     * reset runs on the invites table, which already carries token, expiry and use.
     * Keeping it around suggests a mechanism that is not the one in use.
     *
     * @return void
     */
    public function up()
    {
        Schema::dropIfExists('password_resets');
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::create('password_resets', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
