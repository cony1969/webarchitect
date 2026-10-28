<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['projects', 'components', 'sections', 'pages'] as $kind) {
            Schema::create('cms_'.$kind, function (Blueprint $table) use ($kind) {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->nullable(in_array($kind, ['components', 'sections']))->constrained()->restrictOnDelete();
                $table->string('owner_key', 80);
                if ($kind !== 'projects') {
                    $table->uuid('project_id')->nullable();
                }
                if ($kind === 'pages') {
                    $table->uuid('domain_id')->nullable();
                }
                $table->string('legacy_id', 160)->nullable();
                $table->string('source_key', 400)->nullable();
                $table->string('scope_key', 80)->default('');
                $table->string('name', 160);
                $table->string('slug', 180);
                $table->string('kind', 40)->default(rtrim($kind, 's'));
                $table->string('status', 24)->default('draft');
                $table->integer('position')->default(0);
                $table->unsignedInteger('revision')->default(1);
                $table->jsonb('document');
                $table->jsonb('settings');
                $table->jsonb('source_payload')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['owner_key', 'source_key']);
                $table->unique(['owner_key', 'legacy_id']);
                $table->index(['owner_key', 'scope_key', 'slug']);
            });
        }
        Schema::create('cms_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('cms_projects')->restrictOnDelete();
            $table->string('legacy_id', 160);
            $table->string('name', 253);
            $table->integer('position')->default(0);
            $table->jsonb('settings');
            $table->jsonb('source_payload')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'legacy_id']);
        });
        foreach (['components', 'sections', 'pages'] as $kind) {
            Schema::table('cms_'.$kind, fn (Blueprint $table) => $table->foreign('project_id')->references('id')->on('cms_projects')->restrictOnDelete());
        }
        Schema::table('cms_pages', fn (Blueprint $table) => $table->foreign('domain_id')->references('id')->on('cms_domains')->restrictOnDelete());
        Schema::create('cms_component_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('owner_key', 80);
            $table->foreignUuid('component_id')->constrained('cms_components')->restrictOnDelete();
            $table->string('entry_key', 64);
            $table->string('tab', 80);
            $table->string('category', 160);
            $table->integer('category_position')->default(0);
            $table->integer('position')->default(0);
            $table->jsonb('source_payload');
            $table->timestamps();
            $table->unique(['owner_key', 'entry_key']);
        });
        Schema::create('cms_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['user_id', 'name']);
        });
        Schema::create('cms_document_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('document_type', 24);
            $table->uuid('document_id');
            $table->unsignedInteger('revision');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('payload');
            $table->timestamp('created_at');
            $table->unique(['document_type', 'document_id', 'revision'], 'cms_revision_unique');
        });
        Schema::create('cms_imports', function (Blueprint $table) {
            $table->id();
            $table->string('source', 120)->unique();
            $table->jsonb('manifest');
            $table->timestamp('created_at');
        });
        if (DB::getDriverName() === 'pgsql') {
            foreach (['components', 'sections', 'pages'] as $kind) {
                DB::statement("CREATE INDEX cms_{$kind}_document_gin ON cms_{$kind} USING GIN (document)");
            }
        }
    }

    public function down(): void
    {
        foreach (['cms_imports', 'cms_document_revisions', 'cms_collections', 'cms_component_entries', 'cms_pages', 'cms_sections', 'cms_components', 'cms_domains', 'cms_projects'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
