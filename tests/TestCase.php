<?php

namespace Javaabu\BulkActions\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Javaabu\BulkActions\BulkActionsServiceProvider;
use Javaabu\BulkActions\Routing\BulkActionRoutes;
use Javaabu\BulkActions\Tests\Fixtures\Models\Article;
use Javaabu\BulkActions\Tests\Fixtures\Models\User;
use Javaabu\BulkActions\Tests\Fixtures\Policies\ArticlePolicy;
use Javaabu\Forms\FormsServiceProvider;
use Javaabu\Manifest\ManifestServiceProvider;
use Javaabu\Permissions\PermissionsServiceProvider;
use Javaabu\Stencil\StencilServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        BulkActionRoutes::flush();

        Gate::policy(Article::class, ArticlePolicy::class);
    }

    protected function getPackageProviders($app): array
    {
        return [
            ManifestServiceProvider::class,
            FormsServiceProvider::class,
            StencilServiceProvider::class,
            PermissionServiceProvider::class,
            PermissionsServiceProvider::class,
            BulkActionsServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:yWa/ByhLC/GUvfToOuaPD7zDwB64qkc/QkaQOrT5IpE=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default('draft');
            $table->foreignId('user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function admin(array $attributes = []): User
    {
        return User::query()->create([
            'name' => 'އަޙްމަދު',
            'is_admin' => true,
            ...$attributes,
        ]);
    }

    protected function author(array $attributes = []): User
    {
        return User::query()->create([
            'name' => 'ޢާއިޝާ',
            'is_admin' => false,
            ...$attributes,
        ]);
    }

    /**
     * @return Collection<int, Article>
     */
    protected function articles(int $count, array $attributes = []): Collection
    {
        return collect(range(1, $count))->map(fn (int $i) => Article::query()->create([
            'title' => "ލިޔުން {$i}",
            ...$attributes,
        ]));
    }
}
