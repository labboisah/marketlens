<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('support_knowledge_categories')) {
            Schema::create('support_knowledge_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_articles')) {
            Schema::create('support_articles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_knowledge_category_id')->constrained('support_knowledge_categories')->cascadeOnDelete();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('summary')->nullable();
                $table->longText('body');
                $table->boolean('is_faq')->default(false);
                $table->boolean('is_published')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['support_knowledge_category_id', 'is_published'], 'support_article_category_idx');
                $table->index(['is_faq', 'is_published'], 'support_article_faq_idx');
            });
        }

        if (! Schema::hasTable('support_ticket_categories')) {
            Schema::create('support_ticket_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('support_tickets')) {
            Schema::table('support_tickets', function (Blueprint $table): void {
                if (! Schema::hasColumn('support_tickets', 'ticket_number')) {
                    $table->string('ticket_number')->nullable()->unique()->after('uuid');
                }
                if (! Schema::hasColumn('support_tickets', 'support_ticket_category_id')) {
                    $table->unsignedBigInteger('support_ticket_category_id')->nullable()->after('business_id');
                    $table->foreign('support_ticket_category_id', 'support_ticket_category_fk')
                        ->references('id')
                        ->on('support_ticket_categories')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('support_tickets', 'created_by')) {
                    $table->unsignedBigInteger('created_by')->nullable()->after('support_ticket_category_id');
                    $table->foreign('created_by', 'support_ticket_creator_fk')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('support_tickets', 'assigned_to')) {
                    $table->unsignedBigInteger('assigned_to')->nullable()->after('created_by');
                    $table->foreign('assigned_to', 'support_ticket_assignee_fk')
                        ->references('id')
                        ->on('users')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('support_tickets', 'branch_id')) {
                    $table->unsignedBigInteger('branch_id')->nullable()->after('assigned_to');
                    $table->foreign('branch_id', 'support_ticket_branch_fk')
                        ->references('id')
                        ->on('branches')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('support_tickets', 'device_id')) {
                    $table->unsignedBigInteger('device_id')->nullable()->after('branch_id');
                    $table->foreign('device_id', 'support_ticket_device_fk')
                        ->references('id')
                        ->on('devices')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('support_tickets', 'sync_batch_id')) {
                    $table->unsignedBigInteger('sync_batch_id')->nullable()->after('device_id');
                    $table->foreign('sync_batch_id', 'support_ticket_sync_batch_fk')
                        ->references('id')
                        ->on('sync_batches')
                        ->nullOnDelete();
                }
                if (! Schema::hasColumn('support_tickets', 'description')) {
                    $table->text('description')->nullable()->after('priority');
                }
                if (! Schema::hasColumn('support_tickets', 'last_message_at')) {
                    $table->timestamp('last_message_at')->nullable()->after('description');
                }
                if (! Schema::hasColumn('support_tickets', 'closed_at')) {
                    $table->timestamp('closed_at')->nullable()->after('last_message_at');
                }
                if (! Schema::hasColumn('support_tickets', 'metadata')) {
                    $table->json('metadata')->nullable()->after('closed_at');
                }

                $table->index(['business_id', 'status'], 'support_ticket_business_status_idx');
            });
        }

        if (! Schema::hasTable('support_ticket_messages')) {
            Schema::create('support_ticket_messages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->foreign('user_id', 'support_ticket_message_user_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->longText('body');
                $table->boolean('is_staff_note')->default(false);
                $table->timestamps();
                $table->index(['support_ticket_id', 'created_at'], 'support_message_ticket_created_idx');
            });
        }

        if (! Schema::hasTable('support_ticket_attachments')) {
            Schema::create('support_ticket_attachments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('support_ticket_id')->constrained('support_tickets')->cascadeOnDelete();
                $table->unsignedBigInteger('support_ticket_message_id')->nullable();
                $table->foreign('support_ticket_message_id', 'support_attachment_message_fk')
                    ->references('id')
                    ->on('support_ticket_messages')
                    ->cascadeOnDelete();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->foreign('uploaded_by', 'support_attachment_uploader_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->string('disk')->default('local');
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type');
                $table->unsignedBigInteger('size_bytes');
                $table->string('checksum')->nullable();
                $table->timestamps();
                $table->index(['support_ticket_id', 'created_at'], 'support_attachment_ticket_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_articles');
        Schema::dropIfExists('support_knowledge_categories');
        Schema::dropIfExists('support_ticket_categories');
    }
};
