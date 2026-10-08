<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
            $table->index(['category_id', 'sort_order']);
        });

        Schema::table('lessons', function (Blueprint $table) {
            $table->foreignId('lesson_section_id')
                ->nullable()
                ->after('category_id')
                ->constrained('lesson_sections')
                ->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->after('slug');
        });

        $now = now();
        $categoryIds = DB::table('lessons')
            ->select('category_id')
            ->distinct()
            ->orderBy('category_id')
            ->pluck('category_id');

        foreach ($categoryIds as $categoryId) {
            $sectionId = DB::table('lesson_sections')->insertGetId([
                'category_id' => $categoryId,
                'name' => 'Alapok',
                'slug' => 'alapok',
                'description' => 'A kategória meglévő, alapozó leckéi.',
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $lessonIds = DB::table('lessons')
                ->where('category_id', $categoryId)
                ->orderBy('id')
                ->pluck('id');

            foreach ($lessonIds as $index => $lessonId) {
                DB::table('lessons')
                    ->where('id', $lessonId)
                    ->update([
                        'lesson_section_id' => $sectionId,
                        'sort_order' => ($index + 1) * 10,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lesson_section_id');
            $table->dropColumn('sort_order');
        });

        Schema::dropIfExists('lesson_sections');
    }
};
