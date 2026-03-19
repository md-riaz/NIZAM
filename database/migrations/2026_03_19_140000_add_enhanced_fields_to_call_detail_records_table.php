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
        Schema::table('call_detail_records', function (Blueprint $table) {
            // Quality metrics
            $table->decimal('mos_score', 3, 2)->nullable()->after('negotiated_codec');
            $table->decimal('packet_loss', 5, 2)->nullable()->after('mos_score');
            $table->integer('jitter')->nullable()->after('packet_loss')->comment('Jitter in milliseconds');
            $table->integer('latency')->nullable()->after('jitter')->comment('Latency in milliseconds');
            $table->integer('quality_score')->nullable()->after('latency')->comment('Call quality score 0-100');
            
            // SIP and media information
            $table->string('sip_user_agent')->nullable()->after('quality_score');
            $table->string('remote_media_ip')->nullable()->after('sip_user_agent');
            
            // Call classification
            $table->enum('call_type', ['inbound', 'outbound', 'internal', 'conference'])->nullable()->after('direction');
            
            // Custom metadata
            $table->json('tags')->nullable()->after('call_type')->comment('Custom tags for categorization');
            $table->json('metadata')->nullable()->after('tags')->comment('Additional custom metadata');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('call_detail_records', function (Blueprint $table) {
            $table->dropColumn([
                'mos_score',
                'packet_loss',
                'jitter',
                'latency',
                'quality_score',
                'sip_user_agent',
                'remote_media_ip',
                'call_type',
                'tags',
                'metadata',
            ]);
        });
    }
};
