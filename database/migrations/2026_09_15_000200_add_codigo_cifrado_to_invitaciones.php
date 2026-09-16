<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitaciones', function (Blueprint $table) {
            $table->text('codigo_cifrado')->nullable()->after('codigo_hash');
        });
    }

    public function down(): void
    {
        Schema::table('invitaciones', fn (Blueprint $table) => $table->dropColumn('codigo_cifrado'));
    }
};
