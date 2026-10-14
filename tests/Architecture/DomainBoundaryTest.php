<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DomainBoundaryTest extends TestCase
{
    public function test_domain_only_references_its_own_namespace_and_native_php_types(): void
    {
        $directory = new \RecursiveDirectoryIterator(__DIR__.'/../../app/Library/Domain');
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                self::assertSame([], DependencyInspector::core(file_get_contents($file->getPathname()), ['App\\Library\\Domain']), $file->getPathname());
            }
        }
    }

    public function test_boundary_check_detects_unused_framework_and_application_imports(): void
    {
        self::assertNotEmpty(DependencyInspector::core('<?php use Illuminate\\Support\\Facades\\DB; use App\\Library\\Application\\Ports\\Clock;', ['App\\Library\\Domain']));
        self::assertSame([], DependencyInspector::core('<?php use DateTimeImmutable; use App\\Library\\Domain\\Book;', ['App\\Library\\Domain']));
    }

    public function test_domain_runs_in_a_process_without_composer_or_laravel(): void
    {
        $output = [];
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__DIR__.'/../Fixtures/domain-isolation.php').' 2>&1', $output, $status);
        self::assertSame(0, $status, implode("\n", $output));
        self::assertSame(['domain-ok'], $output);
    }

    public function test_application_only_references_its_own_namespace_domain_and_native_php(): void
    {
        $allowed = ['App\\Library\\Domain', 'App\\Library\\Application'];
        $directory = new \RecursiveDirectoryIterator(__DIR__.'/../../app/Library/Application');
        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                self::assertSame([], DependencyInspector::core(file_get_contents($file->getPathname()), $allowed), $file->getPathname());
            }
        }
        self::assertNotEmpty(DependencyInspector::core('<?php use App\\Library\\Adapters\\Outbound\\Security\\Hasher; use Illuminate\\Support\\Facades\\Hash;', $allowed));
    }

    public function test_production_adapter_construction_and_bindings_are_confined_to_the_composition_point(): void
    {
        $root = realpath(__DIR__.'/../..');
        $composition = realpath($root.'/app/Providers/LibraryServiceProvider.php');
        foreach (['app', 'bootstrap', 'routes'] as $directory) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory)) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    self::assertSame([], DependencyInspector::composition(file_get_contents($file->getPathname()),
                        $file->getRealPath() === $composition), $file->getPathname());
                }
            }
        }
    }
}
