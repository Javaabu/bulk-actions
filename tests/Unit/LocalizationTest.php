<?php

namespace Javaabu\BulkActions\Tests\Unit;

use Illuminate\Support\Arr;
use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LocalizationTest extends TestCase
{
    #[Test]
    public function it_has_a_dhivehi_line_for_every_english_line(): void
    {
        $en = Arr::dot(require __DIR__ . '/../../lang/en/strings.php');
        $dv = Arr::dot(require __DIR__ . '/../../lang/dv/strings.php');

        $this->assertEquals(array_keys($en), array_keys($dv));

        foreach ($dv as $key => $line) {
            $this->assertMatchesRegularExpression('/\p{Thaana}/u', $line, "lang/dv/strings.php [{$key}] isn't in Dhivehi.");

            preg_match_all('/:[a-z_]+/', $en[$key], $placeholders);
            foreach ($placeholders[0] as $placeholder) {
                $this->assertStringContainsString($placeholder, $line, "lang/dv/strings.php [{$key}] is missing {$placeholder}.");
            }

            $this->assertEquals(substr_count($en[$key], '|'), substr_count($line, '|'), "lang/dv/strings.php [{$key}] has different plural forms.");
        }
    }

    #[Test]
    public function it_translates_the_permission_descriptions(): void
    {
        $dv = json_decode(file_get_contents(__DIR__ . '/../../lang/dv.json'), true);

        $this->assertArrayHasKey("View other users' bulk actions", $dv);
        $this->assertArrayHasKey("Cancel other users' bulk actions", $dv);
        $this->assertArrayHasKey('Bulk actions', $dv);
    }

    #[Test]
    public function it_builds_results_in_dhivehi(): void
    {
        $this->app->setLocale('dv');

        $this->assertEquals('2 ރެކޯޑު ފޮހެލެވިއްޖެ.', trans_choice('bulk-actions::strings.actions.delete.done', 2, ['count' => 2]));
        $this->assertEquals('1 ރެކޯޑެއް ނާކާމިޔާބުވެއްޖެ.', trans_choice('bulk-actions::strings.failed', 1, ['count' => 1]));
        $this->assertEquals('ފޮހެލާ', __('bulk-actions::strings.actions.delete.label'));
    }

    #[Test]
    public function it_only_uses_logical_directions_in_views_and_components(): void
    {
        $files = [
            ...glob(__DIR__ . '/../../resources/views/*.blade.php'),
            ...glob(__DIR__ . '/../../resources/js/*.tsx'),
        ];

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/\\b(ml|mr|pl|pr|left|right|text-left|text-right|border-l|border-r|rounded-l|rounded-r)-[0-9a-z]/',
                file_get_contents($file),
                basename($file) . ' uses a physical direction; use ms-, me-, ps-, pe-, start-, end-, text-start or text-end.',
            );
        }
    }
}
