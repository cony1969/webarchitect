<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name')->index(); // napr. sidebar_width, sidebar_active_tab...
            $table->text('value')->nullable();
            $table->timestamps();
            
            // Zabezpečí, že jeden používateľ má pre jeden názov nastavenia iba jeden riadok
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
