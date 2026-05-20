<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('documents', function (Blueprint $table) {
            if (!Schema::hasColumn('documents', 'current_version')) {
                $table->integer('current_version')->default(1)->after('content');
            }
            if (!Schema::hasColumn('documents', 'status')) {
                $table->string('status')->default('available')->after('current_version');
            }
        });
    }

    public function down()
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['current_version', 'status']);
        });
    }
};