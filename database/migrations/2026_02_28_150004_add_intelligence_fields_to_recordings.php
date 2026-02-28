<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('sentiment')->nullable()->after('outcome');
            $table->json('keywords')->nullable()->after('sentiment');
            $table->boolean('needs_review')->default(false)->after('keywords');
            $table->json('review_reasons')->nullable()->after('needs_review');
            $table->decimal('silence_ratio', 5, 2)->nullable()->after('review_reasons');
            $table->integer('transfer_count')->default(0)->after('silence_ratio');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn([
                'sentiment',
                'keywords',
                'needs_review',
                'review_reasons',
                'silence_ratio',
                'transfer_count',
            ]);
        });
    }
};
