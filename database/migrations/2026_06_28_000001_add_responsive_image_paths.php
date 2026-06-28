<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_images') && ! Schema::hasColumn('product_images', 'responsive_paths')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->json('responsive_paths')->nullable()->after('path');
            });
        }

        if (Schema::hasTable('variant_images') && ! Schema::hasColumn('variant_images', 'responsive_paths')) {
            Schema::table('variant_images', function (Blueprint $table) {
                $table->json('responsive_paths')->nullable()->after('path');
            });
        }

        if (Schema::hasTable('brands') && ! Schema::hasColumn('brands', 'logo_responsive_paths')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->json('logo_responsive_paths')->nullable()->after('logo');
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                if (! Schema::hasColumn('categories', 'banner_responsive_paths')) {
                    $table->json('banner_responsive_paths')->nullable()->after('banner');
                }

                if (! Schema::hasColumn('categories', 'icon_responsive_paths')) {
                    $table->json('icon_responsive_paths')->nullable()->after('icon');
                }

                if (! Schema::hasColumn('categories', 'cover_image_responsive_paths')) {
                    $table->json('cover_image_responsive_paths')->nullable()->after('cover_image');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                foreach (['banner_responsive_paths', 'icon_responsive_paths', 'cover_image_responsive_paths'] as $column) {
                    if (Schema::hasColumn('categories', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('brands') && Schema::hasColumn('brands', 'logo_responsive_paths')) {
            Schema::table('brands', function (Blueprint $table) {
                $table->dropColumn('logo_responsive_paths');
            });
        }

        if (Schema::hasTable('variant_images') && Schema::hasColumn('variant_images', 'responsive_paths')) {
            Schema::table('variant_images', function (Blueprint $table) {
                $table->dropColumn('responsive_paths');
            });
        }

        if (Schema::hasTable('product_images') && Schema::hasColumn('product_images', 'responsive_paths')) {
            Schema::table('product_images', function (Blueprint $table) {
                $table->dropColumn('responsive_paths');
            });
        }
    }
};
