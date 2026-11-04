<?php

namespace Javaabu\BulkActions\Tests\Unit;

use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_default_config(): void
    {
        $this->assertEquals('blade', config('bulk-actions.views'));
    }
}
