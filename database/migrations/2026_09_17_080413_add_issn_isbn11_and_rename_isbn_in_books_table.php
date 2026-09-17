<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // Rename existing isbn column to isbn13
            $table->renameColumn('isbn', 'isbn13');

            // Add new columns (nullable by default to avoid issues with existing records)
            $table->string('isbn11', 11)->nullable()->after('isbn13');
            $table->string('issn', 9)->nullable()->after('isbn11');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['issn', 'isbn11']);
            $table->renameColumn('isbn13', 'isbn');
        });
    }
};
