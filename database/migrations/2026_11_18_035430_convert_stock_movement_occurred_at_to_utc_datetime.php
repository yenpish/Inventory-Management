<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Convert stored TIMESTAMP instants to UTC wall-clock DATETIME values.
     *
     * MariaDB converts TIMESTAMP values through the session timezone during
     * ALTER TABLE, so the conversion must run in UTC even for existing rows.
     */
    public function up(): void
    {
        if (Schema::getColumnType('stock_movements', 'occurred_at') === 'datetime') {
            return;
        }

        $connection = Schema::getConnection();
        $isMariaDbOrMySql = in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
        $previousTimezone = $isMariaDbOrMySql
            ? $connection->selectOne('SELECT @@session.time_zone AS timezone')->timezone
            : null;

        try {
            if ($isMariaDbOrMySql) {
                $connection->statement("SET SESSION time_zone = '+00:00'");
            }

            Schema::table('stock_movements', function (Blueprint $table) {
                $table->dateTime('occurred_at')->change();
            });
        } finally {
            if ($previousTimezone !== null) {
                $connection->statement('SET SESSION time_zone = ?', [$previousTimezone]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        throw new LogicException('The UTC datetime correction is forward-only; reverting would narrow the date range and risk historical timestamps.');
    }
};
