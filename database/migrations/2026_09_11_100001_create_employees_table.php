<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('emp_code', 50)->unique(); // Ac-No from the CSV export
            $table->string('name', 150);
            $table->string('position', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->time('start_time')->nullable(); // used only for tardy; no start_time = no tardy
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
