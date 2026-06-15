<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'invited_by')) {
                $table->foreignId('invited_by')->nullable()->after('staff_member_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'invited_at')) {
                $table->timestamp('invited_at')->nullable()->after('can_access_sppra');
            }

            if (! Schema::hasColumn('users', 'invitation_accepted_at')) {
                $table->timestamp('invitation_accepted_at')->nullable()->after('invited_at');
            }
        });

        DB::table('users')
            ->whereNotNull('email')
            ->whereNull('invited_at')
            ->update([
                'invited_at' => now(),
                'invitation_accepted_at' => now(),
            ]);

        Schema::create('user_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->string('status')->default('Pending');
            $table->timestamp('expires_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('password_reset_otps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['email', 'used_at']);
            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
        Schema::dropIfExists('user_invitations');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['invited_by', 'invited_at', 'invitation_accepted_at'] as $column) {
                if (! Schema::hasColumn('users', $column)) {
                    continue;
                }

                if ($column === 'invited_by') {
                    $table->dropConstrainedForeignId($column);
                } else {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
