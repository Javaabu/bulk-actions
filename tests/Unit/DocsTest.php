<?php

namespace Javaabu\BulkActions\Tests\Unit;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Javaabu\BulkActions\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class DocsTest extends TestCase
{
    protected function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<int, string>
     */
    protected function pages(): array
    {
        $pages = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root() . '/docs')) as $file) {
            if ($file->getExtension() === 'md') {
                $pages[] = Str::after($file->getPathname(), $this->root() . '/');
            }
        }

        sort($pages);

        return $pages;
    }

    #[Test]
    public function it_lists_every_docs_page_in_llms_txt(): void
    {
        preg_match_all('/\]\((docs\/[^)]+\.md)\)/', file_get_contents($this->root() . '/llms.txt'), $matches);

        $listed = $matches[1];
        sort($listed);

        $this->assertEquals($this->pages(), $listed);
    }

    #[Test]
    public function it_only_links_to_pages_that_exist(): void
    {
        foreach ($this->pages() as $page) {
            preg_match_all('/\]\((\.{1,2}\/[^)#]+)(#[^)]*)?\)/', file_get_contents($this->root() . "/{$page}"), $matches);

            foreach ($matches[1] as $link) {
                $target = realpath(dirname($this->root() . "/{$page}") . '/' . $link);

                $this->assertNotFalse($target, "{$page} links to {$link}, which doesn't exist.");
            }
        }
    }

    #[Test]
    public function it_has_front_matter_on_every_page(): void
    {
        foreach ($this->pages() as $page) {
            $this->assertMatchesRegularExpression('/^---\ntitle: .+\n/', file_get_contents($this->root() . "/{$page}"), "{$page} has no title.");
        }
    }

    #[Test]
    public function it_documents_every_config_option(): void
    {
        $docs = file_get_contents($this->root() . '/docs/advanced-usage/configuration.md');

        foreach (array_keys(Arr::dot(config('bulk-actions'))) as $key) {
            $key = preg_replace('/\.\d+$/', '', $key);

            $this->assertStringContainsString("`{$key}`", $docs, "config/bulk-actions.php [{$key}] isn't documented.");
        }
    }
}
