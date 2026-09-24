<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The only effect of handed_off is that the assistant stops answering. Why it
     * stopped is kept in handoff_reason.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('assistant_status', 20)->default('active');
            $table->timestamp('handed_off_at')->nullable();
            $table->string('handoff_reason', 50)->nullable();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['assistant_status', 'handed_off_at', 'handoff_reason']);
        });
    }
};
