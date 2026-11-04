<?php

namespace Javaabu\BulkActions\Tests\Unit;

use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class BoostTest extends TestCase
{
    #[Test]
    public function it_ships_the_boost_guideline_and_skill(): void
    {
        $root = dirname(__DIR__, 2) . '/resources/boost';

        $this->assertFileExists("{$root}/guidelines/core.blade.php");

        $skill = file_get_contents("{$root}/skills/javaabu-bulk-actions/SKILL.md");

        $this->assertMatchesRegularExpression('/^---\nname: javaabu-bulk-actions\ndescription: .+\n/', $skill);

        preg_match_all('/\]\((references\/[^)]+)\)/', $skill, $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $reference) {
            $this->assertFileExists("{$root}/skills/javaabu-bulk-actions/{$reference}");
        }
    }

    #[Test]
    public function it_compiles_the_guideline(): void
    {
        $html = view()->file(dirname(__DIR__, 2) . '/resources/boost/guidelines/core.blade.php')->render();

        $this->assertStringContainsString('Route::bulkActions', $html);
        $this->assertStringContainsString('javaabu-bulk-actions', $html);
    }
}
