<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per recipient, separate from read_at (which is about the in-app notification, not
     * the e-mail). A notification fans out to several users in one loop; a retry that
     * failed partway through must not re-mail whoever already got theirs.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable();
        });
    }

    /**
     * @return void
     */
    public function down()
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropColumn('emailed_at');
        });
    }
};
