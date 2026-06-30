<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_content_blocks', function (Blueprint $table) {
            $table->id();

            // PLATFORM-WIDE content — deliberately NO tenant_id. The landing
            // website is the platform's own marketing site, controlled by the
            // super admin, never by a tenant.
            //
            // key: stable, unique identifier the code/seeder references, e.g.
            //      'hero_heading', 'feature_1_title'.
            $table->string('key')->unique();

            // text | richtext | image — drives how the value is edited/rendered.
            $table->string('type')->default('text');

            // content holds text/richtext values; image_path holds the S3 path
            // for image blocks. A block uses one or the other by its type.
            $table->text('content')->nullable();
            $table->string('image_path')->nullable();

            // Logical grouping for the super admin editor + public pages:
            // hero | features | pricing | about | contact | testimonials.
            $table->string('section');
            $table->integer('sort_order')->default(0);

            $table->timestamps();

            $table->index('section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_content_blocks');
    }
};
