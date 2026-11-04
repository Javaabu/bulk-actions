<?php

namespace Javaabu\BulkActions\Tests\Unit;

use Illuminate\Support\Facades\Schema;
use Javaabu\BulkActions\Tests\TestCase;
use Javaabu\Permissions\Permissions;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_default_config(): void
    {
        $this->assertEquals('blade', config('bulk-actions.views'));
        $this->assertEquals(500, config('bulk-actions.queue_after'));
        $this->assertEquals(100, config('bulk-actions.chunk_size'));
    }

    #[Test]
    public function it_loads_the_runs_migration(): void
    {
        $this->assertTrue(Schema::hasTable('bulk_action_runs'));
    }

    #[Test]
    public function it_registers_the_permissions(): void
    {
        $this->assertTrue(Permissions::has('view_others_bulk_action_runs'));
        $this->assertTrue(Permissions::has('cancel_others_bulk_action_runs'));
    }
}
