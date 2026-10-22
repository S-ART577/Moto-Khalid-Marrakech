<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seasonal dates, performance metrics, activity history and browser push subscriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seasonal_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete(); // empty = all brands
            $table->string('name_fr');
            $table->string('name_en');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('category');                      // religious | commercial | national | school | season
            $table->boolean('is_approximate')->default(false); // Hijri dates depend on the moon sighting
            $table->unsignedSmallInteger('shoot_lead_days')->default(14);
            $table->unsignedSmallInteger('design_lead_days')->default(7);
            $table->text('guidance')->nullable();
            $table->timestamps();
            $table->index('starts_on');
        });

        // One row per platform variant, overwritten when updated.
        Schema::create('metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_variant_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('saves')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('whatsapp_orders')->default(0);
            $table->string('source')->default('manual'); // manual | csv
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->string('action');
            $table->json('details')->nullable(); // what changed, e.g. {"to": "28 Oct"}
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->string('public_key');
            $table->string('auth_token');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('metrics');
        Schema::dropIfExists('seasonal_events');
    }
};
