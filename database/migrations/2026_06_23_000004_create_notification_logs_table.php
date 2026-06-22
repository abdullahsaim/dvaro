<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // Polymorphic-ish target stored as plain strings (NOT a Laravel
            // morphTo): the notifiable may be a Customer ('customer') or a
            // TenantUser ('tenant_user'). Kept loose on purpose — a log row must
            // survive even if the underlying record is later removed, and the
            // notifiable lives in a different module guard.
            $table->string('notifiable_type');
            $table->unsignedBigInteger('notifiable_id');

            // email / sms / whatsapp (NotificationLog::CHANNEL_*).
            $table->string('channel');

            // The domain event that triggered this, e.g. 'invoice.generated'.
            $table->string('event_type');

            // Where it was sent — an email address or a phone number.
            $table->string('recipient');

            // Subject only applies to email; null for sms/whatsapp.
            $table->string('subject')->nullable();

            $table->text('body');

            // pending / sent / failed (NotificationLog::STATUS_*).
            $table->string('status');

            // Concrete provider used, e.g. 'mailgun', 'clicksend', 'log'.
            $table->string('provider');

            // Provider's own message id when it returns one; null otherwise.
            $table->string('provider_message_id')->nullable();

            // Captured failure reason when status = failed.
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // Dashboard/queue queries filter by tenant + status; the second index
            // serves "all notifications for this customer/user" lookups.
            $table->index(['tenant_id', 'status']);
            $table->index(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
