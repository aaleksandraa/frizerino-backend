<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Helper function to check if index exists (database-agnostic)
        $indexExists = function($table, $indexName) {
            try {
                $connection = DB::connection()->getDriverName();

                if ($connection === 'pgsql') {
                    $result = DB::select("SELECT 1 FROM pg_indexes WHERE indexname = ?", [$indexName]);
                    return count($result) > 0;
                } elseif ($connection === 'mysql') {
                    $result = DB::select("SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?", [$table, $indexName]);
                    return count($result) > 0;
                } elseif ($connection === 'sqlite') {
                    $result = DB::select("SELECT 1 FROM sqlite_master WHERE type='index' AND name = ?", [$indexName]);
                    return count($result) > 0;
                }
            } catch (\Exception $e) {
                // If query fails, assume index doesn't exist
                return false;
            }

            return false;
        };

        // Salons - pretraga po gradu i statusu
        Schema::table('salons', function (Blueprint $table) use ($indexExists) {
            // Only add indexes if columns exist
            if (Schema::hasColumn('salons', 'city') && !$indexExists('salons', 'idx_salons_city')) {
                $table->index('city', 'idx_salons_city');
            }
            if (Schema::hasColumn('salons', 'status') && !$indexExists('salons', 'idx_salons_status')) {
                $table->index('status', 'idx_salons_status');
            }
            if (Schema::hasColumn('salons', 'slug') && !$indexExists('salons', 'idx_salons_slug')) {
                $table->index('slug', 'idx_salons_slug');
            }
            if (Schema::hasColumn('salons', 'city') && Schema::hasColumn('salons', 'status') && !$indexExists('salons', 'idx_salons_city_status')) {
                $table->index(['city', 'status'], 'idx_salons_city_status');
            }
        });

        // Appointments - najčešće pretraživani
        Schema::table('appointments', function (Blueprint $table) use ($indexExists) {
            if (Schema::hasColumn('appointments', 'date') && !$indexExists('appointments', 'idx_appointments_date')) {
                $table->index('date', 'idx_appointments_date');
            }
            if (Schema::hasColumn('appointments', 'status') && !$indexExists('appointments', 'idx_appointments_status')) {
                $table->index('status', 'idx_appointments_status');
            }
            if (Schema::hasColumn('appointments', 'salon_id') && !$indexExists('appointments', 'idx_appointments_salon_id')) {
                $table->index('salon_id', 'idx_appointments_salon_id');
            }
            if (Schema::hasColumn('appointments', 'staff_id') && !$indexExists('appointments', 'idx_appointments_staff_id')) {
                $table->index('staff_id', 'idx_appointments_staff_id');
            }
            if (Schema::hasColumn('appointments', 'client_id') && !$indexExists('appointments', 'idx_appointments_client_id')) {
                $table->index('client_id', 'idx_appointments_client_id');
            }

            // Composite indeksi za složene upite
            if (Schema::hasColumn('appointments', 'salon_id') && Schema::hasColumn('appointments', 'date') && !$indexExists('appointments', 'idx_appointments_salon_date')) {
                $table->index(['salon_id', 'date'], 'idx_appointments_salon_date');
            }
            if (Schema::hasColumn('appointments', 'staff_id') && Schema::hasColumn('appointments', 'date') && !$indexExists('appointments', 'idx_appointments_staff_date')) {
                $table->index(['staff_id', 'date'], 'idx_appointments_staff_date');
            }
            if (Schema::hasColumn('appointments', 'salon_id') && Schema::hasColumn('appointments', 'date') && Schema::hasColumn('appointments', 'status') && !$indexExists('appointments', 'idx_appointments_search')) {
                $table->index(['salon_id', 'date', 'status'], 'idx_appointments_search');
            }
        });

        // Reviews
        Schema::table('reviews', function (Blueprint $table) use ($indexExists) {
            if (Schema::hasColumn('reviews', 'salon_id') && !$indexExists('reviews', 'idx_reviews_salon_id')) {
                $table->index('salon_id', 'idx_reviews_salon_id');
            }
            if (Schema::hasColumn('reviews', 'client_id') && !$indexExists('reviews', 'idx_reviews_client_id')) {
                $table->index('client_id', 'idx_reviews_client_id');
            }
            if (Schema::hasColumn('reviews', 'created_at') && !$indexExists('reviews', 'idx_reviews_created_at')) {
                $table->index('created_at', 'idx_reviews_created_at');
            }
        });

        // Services
        Schema::table('services', function (Blueprint $table) use ($indexExists) {
            if (Schema::hasColumn('services', 'salon_id') && !$indexExists('services', 'idx_services_salon_id')) {
                $table->index('salon_id', 'idx_services_salon_id');
            }
            if (Schema::hasColumn('services', 'category') && !$indexExists('services', 'idx_services_category')) {
                $table->index('category', 'idx_services_category');
            }
        });

        // Staff
        Schema::table('staff', function (Blueprint $table) use ($indexExists) {
            if (Schema::hasColumn('staff', 'salon_id') && !$indexExists('staff', 'idx_staff_salon_id')) {
                $table->index('salon_id', 'idx_staff_salon_id');
            }
            if (Schema::hasColumn('staff', 'user_id') && !$indexExists('staff', 'idx_staff_user_id')) {
                $table->index('user_id', 'idx_staff_user_id');
            }
        });

        // Favorites
        if (Schema::hasTable('favorites')) {
            Schema::table('favorites', function (Blueprint $table) use ($indexExists) {
                if (Schema::hasColumn('favorites', 'user_id') && Schema::hasColumn('favorites', 'salon_id') && !$indexExists('favorites', 'idx_favorites_user_salon')) {
                    $table->index(['user_id', 'salon_id'], 'idx_favorites_user_salon');
                }
            });
        }

        // Notifications
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) use ($indexExists) {
                if (Schema::hasColumn('notifications', 'recipient_id') && !$indexExists('notifications', 'idx_notifications_recipient_id')) {
                    $table->index('recipient_id', 'idx_notifications_recipient_id');
                }
                if (Schema::hasColumn('notifications', 'recipient_id') && Schema::hasColumn('notifications', 'is_read') && !$indexExists('notifications', 'idx_notifications_read')) {
                    $table->index(['recipient_id', 'is_read'], 'idx_notifications_read');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safely drop indexes if they exist
        Schema::table('salons', function (Blueprint $table) {
            try { $table->dropIndex('idx_salons_city'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_salons_status'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_salons_slug'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_salons_city_status'); } catch (\Exception $e) {}
        });

        Schema::table('appointments', function (Blueprint $table) {
            try { $table->dropIndex('idx_appointments_date'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_appointments_status'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_appointments_salon_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_appointments_staff_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_appointments_client_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_appointments_salon_date'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_appointments_staff_date'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_appointments_search'); } catch (\Exception $e) {}
        });

        Schema::table('reviews', function (Blueprint $table) {
            try { $table->dropIndex('idx_reviews_salon_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_reviews_client_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_reviews_created_at'); } catch (\Exception $e) {}
        });

        Schema::table('services', function (Blueprint $table) {
            try { $table->dropIndex('idx_services_salon_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_services_category'); } catch (\Exception $e) {}
        });

        Schema::table('staff', function (Blueprint $table) {
            try { $table->dropIndex('idx_staff_salon_id'); } catch (\Exception $e) {}
            try { $table->dropIndex('idx_staff_user_id'); } catch (\Exception $e) {}
        });

        if (Schema::hasTable('favorites')) {
            Schema::table('favorites', function (Blueprint $table) {
                try { $table->dropIndex('idx_favorites_user_salon'); } catch (\Exception $e) {}
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                try { $table->dropIndex('idx_notifications_recipient_id'); } catch (\Exception $e) {}
                try { $table->dropIndex('idx_notifications_read'); } catch (\Exception $e) {}
            });
        }
    }
};
