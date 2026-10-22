<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything that describes a brand: identity, platforms, series,
 * product families, weekly rhythm, quotas and monthly notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            // Colour tokens. Defaults are the GROSHOP identity.
            $table->string('color_bg', 7)->default('#102533');
            $table->string('color_bg_2', 7)->default('#17344c');
            $table->string('color_primary', 7)->default('#f02443');
            $table->string('color_primary_text', 7)->default('#d61e3a');
            $table->string('color_light', 7)->default('#dfebf7');
            $table->string('color_accent', 7)->default('#43dde6');
            $table->text('tone_notes')->nullable();
            $table->string('tagline')->nullable();   // e.g. "Produits vérifiés. Fiabilité garantie."
            $table->string('signature')->nullable(); // e.g. "Think GRO. Grow More."
            $table->string('whatsapp')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('brand_user', function (Blueprint $table) {
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['brand_id', 'user_id']);
        });

        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // tiktok, instagram, facebook, snapchat
            $table->string('name');
            $table->string('short', 3);      // TT, IG, FB, SC
            $table->string('open_url');
            $table->unsignedSmallInteger('position')->default(0);
        });

        Schema::create('brand_platform', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('handle')->nullable();
            $table->string('profile_url')->nullable();
            $table->unique(['brand_id', 'platform_id']);
        });

        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 7)->nullable();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('product_families', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Warn when this family has not been featured for this many weeks.
            $table->unsignedTinyInteger('max_gap_weeks')->default(3);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });

        // The weekly rhythm, e.g. Tue video 20:00, Thu static 20:00, Sat video 20:00.
        Schema::create('posting_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 1 = Monday ... 7 = Sunday
            $table->string('format');               // video | static
            $table->string('time', 5)->default('20:00');
            $table->timestamps();
        });

        // Targets. Examples:
        //   week  / total          / null        / 3
        //   week  / format         / video       / 2
        //   month / series         / {series id} / 2
        //   month / product_family / {family id} / 2
        Schema::create('quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('period');      // week | month
            $table->string('dimension');   // total | format | offer | platform | series | product_family
            $table->string('dimension_ref')->nullable();
            $table->unsignedSmallInteger('target');
            $table->timestamps();
        });

        // The three boxes under the monthly calendar: theme, offer focus, key dates.
        Schema::create('month_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->date('month'); // always the 1st of the month
            $table->string('theme_title')->nullable();
            $table->text('theme_body')->nullable();
            $table->string('offer_title')->nullable();
            $table->text('offer_body')->nullable();
            $table->string('dates_title')->nullable();
            $table->text('dates_body')->nullable();
            $table->timestamps();
            $table->unique(['brand_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('month_notes');
        Schema::dropIfExists('quotas');
        Schema::dropIfExists('posting_slots');
        Schema::dropIfExists('product_families');
        Schema::dropIfExists('series');
        Schema::dropIfExists('brand_platform');
        Schema::dropIfExists('platforms');
        Schema::dropIfExists('brand_user');
        Schema::dropIfExists('brands');
    }
};
