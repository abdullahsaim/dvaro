<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('return_inspections', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            // restrictOnDelete: an agreement's return record must outlive it —
            // this IS part of the agreement's permanent history. UNIQUE: one
            // agreement is returned at most once — a second attempt is a bug,
            // not a legitimate re-inspection (the app layer also guards this,
            // this is the belt-and-suspenders floor).
            $table->foreignId('agreement_id')->unique()->constrained('agreements')->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            // The reading at return — fed into RecordOdometerReadingAction
            // (source: return_inspection) so it becomes the vehicle's new
            // current_odometer through the ONE sanctioned path, same as every
            // other odometer entry.
            $table->integer('odometer_reading');

            // empty | quarter | half | three_quarter | full
            $table->string('fuel_level');

            $table->text('condition_notes')->nullable();
            $table->boolean('damage_found')->default(false);
            $table->text('damage_description')->nullable();
            // Ticked when the damage is more than cosmetic — the vehicle goes
            // to Maintenance instead of back to Available, and a workshop job
            // is opened automatically.
            $table->boolean('needs_workshop')->default(false);

            // Cents. Whatever is withheld from the bond for fuel/damage/other
            // — a staff judgement call, never auto-computed from damage alone.
            $table->integer('deduction_amount')->default(0);
            $table->text('deduction_reason')->nullable();
            // bond_amount (frozen from the agreement at this moment) minus
            // deduction_amount, floored at 0 — stored rather than recomputed
            // later so a subsequent change to the agreement's bond_amount
            // (there isn't one today, but agreements can version) never
            // silently rewrites what was actually refunded.
            $table->integer('bond_amount');
            $table->integer('refund_amount');

            $table->foreignId('inspected_by')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->timestamp('completed_at');

            // created_at only — this is the permanent record of what happened
            // at return, append-only like the ledger and the audit log.
            $table->timestamp('created_at')->nullable();

            $table->index(['tenant_id', 'agreement_id']);
            $table->index(['tenant_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_inspections');
    }
};
