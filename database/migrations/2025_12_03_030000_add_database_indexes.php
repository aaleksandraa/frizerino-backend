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
        // Appointments indexes
        Schema::table('appointments', function (Blueprint $table) {
            $table->index(['salon_id', 'date', 'status'], 'idx_appointments_salon_date_status');
            $table->index(['staff_id', 'date', 'status'], 'idx_appointments_staff_date_status');
            $table->index(['client_id', 'status', 'date'], 'idx_appointments_client_status_date');
            $table->index('created_at', 'idx_appointments_created');
        });

        // Reviews indexes
        Schema::table('reviews', function (Blueprint $table) {
            $table->index(['salon_id', 'rating'], 'idx_reviews_salon_rating');
            $table->index(['staff_id', 'rating'], 'idx_reviews_staff_rating');
            $table->index('created_at', 'idx_reviews_created');
        });

        // Notifications indexes
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['recipient_id', 'is_read', 'created_at'], 'idx_notifications_user_unread');
        });

        // Services indexes
        Schema::table('services', function (Blueprint $table) {
            $table->index(['salon_id', 'is_active'], 'idx_services_salon_active');
            $table->index('category', 'idx_services_category');
        });

        // Staff indexes
        Schema::table('staff', function (Blueprint $table) {
            $table->index(['salon_id', 'is_active'], 'idx_staff_salon_active');
            $table->index('user_id', 'idx_staff_user');
        });

        // Salons indexes
        Schema::table('salons', function (Blueprint $table) {
            $table->index(['status', 'is_verified'], 'idx_salons_status_verified');
            $table->index('city', 'idx_salons_city');
            $table->index('owner_id', 'idx_salons_owner');
        });

        // Favorites indexes
        Schema::table('favorites', function (Blueprint $table) {
            $table->index(['user_id', 'salon_id'], 'idx_favorites_user_salon');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('idx_appointments_salon_date_status');
            $table->dropIndex('idx_appointments_staff_date_status');
            $table->dropIndex('idx_appointments_client_status_date');
            $table->dropIndex('idx_appointments_created');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('idx_reviews_salon_rating');
            $table->dropIndex('idx_reviews_staff_rating');
            $table->dropIndex('idx_reviews_created');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('idx_notifications_user_unread');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex('idx_services_salon_active');
            $table->dropIndex('idx_services_category');
        });

        Schema::table('staff', function (Blueprint $table) {
            $table->dropIndex('idx_staff_salon_active');
            $table->dropIndex('idx_staff_user');
        });

        Schema::table('salons', function (Blueprint $table) {
            $table->dropIndex('idx_salons_status_verified');
            $table->dropIndex('idx_salons_city');
            $table->dropIndex('idx_salons_owner');
        });

        Schema::table('favorites', function (Blueprint $table) {
            $table->dropIndex('idx_favorites_user_salon');
        });
    }
};
