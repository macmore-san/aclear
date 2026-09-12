<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_punches', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('emp_code', 50);
            $table->dateTime('punch_time');
            $table->string('source_file', 255)->nullable();
            $table->timestamp('uploaded_at')->useCurrent();

            $table->unique(['emp_code', 'punch_time'], 'uq_emp_punch');
            $table->index('punch_time', 'idx_punch_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_punches');
    }
};
