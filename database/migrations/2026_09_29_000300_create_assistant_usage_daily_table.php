<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily counters, incremented atomically with an upsert. In the database and not
     * in the cache because the file driver has no atomic increment and the number has
     * to survive a cache flush.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('assistant_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('provider', 30);
            $table->unsignedInteger('calls')->default(0);
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->timestamps();

            $table->unique(['date', 'provider']);
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('assistant_usage_daily');
    }
};
