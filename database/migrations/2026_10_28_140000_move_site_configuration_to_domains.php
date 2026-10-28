<?php

use App\Services\Cms\DomainConfigurationMigration;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cms_domains', function (Blueprint $table) {
            $table->unsignedInteger('revision')->default(1);
            $table->string('status', 24)->default('draft');
        });
        app(DomainConfigurationMigration::class)->run();
    }

    public function down(): void
    {
        // Historical project/domain settings remain available in the private import journal.
        Schema::table('cms_domains', fn (Blueprint $table) => $table->dropColumn(['revision', 'status']));
    }
};
