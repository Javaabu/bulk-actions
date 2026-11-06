<?php

namespace Javaabu\BulkActions\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Javaabu\BulkActions\Tests\TestCase;
use Javaabu\Manifest\Enums\NpmScopes;
use Javaabu\Manifest\Manifest;
use Javaabu\Manifest\Package;
use PHPUnit\Framework\Attributes\Test;

class AssetsTest extends TestCase
{
    protected function package(): Package
    {
        return app(Manifest::class)->package('bulk-actions');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function check(array $options = []): array
    {
        Artisan::call('manifest:check', ['--json' => true, ...$options]);

        return json_decode(Artisan::output(), true);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    protected function neededByBulkActions(array $rows): array
    {
        return array_values(array_map(
            fn (array $row) => $row['name'],
            array_filter($rows, fn (array $row) => in_array('bulk-actions', $row['needed_by'], true)),
        ));
    }

    #[Test]
    public function it_registers_the_blade_script_and_the_react_components(): void
    {
        $package = $this->package();

        $this->assertNotNull($package->asset('bulk-actions'));
        $this->assertEquals('@javaabu/bulk-actions', $package->importAlias());
    }

    #[Test]
    public function it_only_asks_inertia_apps_for_the_react_dependencies(): void
    {
        $package = $this->package();

        $this->assertEquals([], $package->npmDependencies(NpmScopes::ASSETS));
        $this->assertEquals([], $package->npmPeerDependencies(NpmScopes::ASSETS));
        $this->assertArrayHasKey('@javaabu/forms', $package->npmDependencies(NpmScopes::PAGES));
        $this->assertArrayHasKey('react', $package->npmPeerDependencies(NpmScopes::PAGES));
    }

    #[Test]
    public function it_asks_a_blade_only_app_for_no_react_packages_in_manifest_check(): void
    {
        // The testbench app's package.json has no Inertia adapter: a Blade-only app.
        foreach ([$this->check(), $this->check(['--scope' => ['assets']])] as $rows) {
            $needed = $this->neededByBulkActions($rows);

            $this->assertNotContains('@javaabu/forms', $needed);
            $this->assertNotContains('react', $needed);
            $this->assertNotContains('react-dom', $needed);

            // Only what building the Blade script takes (manifest adds Vite for any registered asset).
            $this->assertEmpty(array_diff($needed, ['vite', 'laravel-vite-plugin']));
        }
    }

    #[Test]
    public function it_asks_inertia_apps_for_the_react_dependencies_in_manifest_check(): void
    {
        $needed = $this->neededByBulkActions($this->check(['--scope' => ['pages']]));

        $this->assertContains('@javaabu/forms', $needed);
        $this->assertContains('react', $needed);
        $this->assertContains('react-dom', $needed);
    }
}
