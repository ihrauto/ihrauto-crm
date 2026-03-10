<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->default('default');
            $table->string('token_prefix', 32);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'revoked_at']);
        });

        $tenants = DB::table('tenants')
            ->select(['id', 'api_key'])
            ->whereNotNull('api_key')
            ->get();

        foreach ($tenants as $tenant) {
            DB::table('tenant_api_tokens')->insert([
                'tenant_id' => $tenant->id,
                'name' => 'migrated-default',
                'token_prefix' => substr($tenant->api_key, 0, 12),
                'token_hash' => hash('sha256', $tenant->api_key),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('tenants')->whereNotNull('api_key')->update(['api_key' => null]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_tokens');
    }
};
