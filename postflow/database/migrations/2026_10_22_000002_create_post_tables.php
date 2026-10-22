<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A post is one master idea. Each platform gets its own variant
 * (caption, hashtags, placement, ratio, publish time, posted URL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('series_id')->nullable()->constrained('series')->nullOnDelete();
            $table->foreignId('product_family_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('brief')->nullable();
            $table->string('format');                  // video | static
            $table->boolean('is_offer')->default(false);
            $table->dateTime('planned_at')->nullable(); // empty = idea not scheduled yet
            $table->string('stage')->default('brief');  // brief, shoot, edit, design, review, scheduled, posted
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();         // production deadline
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['brand_id', 'planned_at']);
        });

        Schema::create('post_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('placement')->nullable();    // reel, post, carousel, story, spotlight, video
            $table->string('aspect_ratio', 5)->nullable(); // 9:16, 4:5, 1:1, 16:9
            $table->text('caption')->nullable();
            $table->text('hashtags')->nullable();
            $table->dateTime('publish_at')->nullable();
            $table->string('status')->default('planned'); // planned | posted | skipped
            $table->string('post_url', 2048)->nullable();
            $table->dateTime('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reminded_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->timestamps();
            $table->unique(['post_id', 'platform_id']);
            $table->index(['publish_at', 'status']);
        });

        // Links to Google Drive (or any URL). No file storage in v1.
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url', 2048);
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->boolean('done')->default(false);
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('assets');
        Schema::dropIfExists('post_variants');
        Schema::dropIfExists('posts');
    }
};
