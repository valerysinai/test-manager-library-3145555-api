<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DependencyInspectorTest extends TestCase
{
    #[DataProvider('forbiddenCoreSources')]
    public function test_forbidden_core_dependencies_are_detected(string $source): void
    {
        self::assertNotEmpty(DependencyInspector::core($source, ['App\\Library\\Domain']));
    }

    public static function forbiddenCoreSources(): array
    {
        return [
            ['<?php namespace App\Library\Domain; use PDO;'],
            ['<?php namespace App\Library\Domain; function f(\PDO $connection): void {}'],
            ['<?php namespace App\Library\Domain; use Illuminate\Support\Facades\DB as Database; function f() { return Database::table("x"); }'],
            ['<?php namespace App\Library\Domain; use Illuminate\Support\{Collection as Items, Facades\DB};'],
            ['<?php namespace App\Library\Domain; use App\Library\Application\Ports\Clock;'],
            ['<?php namespace App\Library\Domain; function f() { return \App\Library\Adapters\Outbound\Security\Hasher::class; }'],
            ['<?php namespace App\Library\Domain; function f() { return config("x"); }'],
            ['<?php namespace App\Library\Domain; function f() { return \app(); }'],
            ['<?php namespace App\Library\Domain; use function Illuminate\Support\env as setting; function f() { return setting("x"); }'],
            ['<?php namespace App\Library\Domain; function f($name) { return new $name(); }'],
            ['<?php namespace App\Library\Domain; function f($callback) { return $callback(); }'],
            ['<?php namespace App\Library\Domain; function f(): \Illuminate\Contracts\Support\Arrayable {}'],
            ['<?php namespace App\Library\Domain; function f() { return call_user_func("app"); }'],
            ['<?php namespace App\Library\Domain; function f() { require "external.php"; }'],
        ];
    }

    #[DataProvider('allowedCoreSources')]
    public function test_native_php_and_internal_dependencies_pass(string $source): void
    {
        self::assertSame([], DependencyInspector::core($source, ['App\\Library\\Domain']));
    }

    public static function allowedCoreSources(): array
    {
        return [
            ['<?php namespace App\Library\Domain; use DateTimeImmutable as Instant; function f(Instant $at): Instant { return $at; }'],
            ['<?php namespace App\Library\Domain; use App\Library\Domain\{Book as Volume, Quantity}; function f(Volume $book): Quantity { return $book->totalCopies; }'],
            ['<?php namespace App\Library\Domain; use function strlen as length; function f(string $value): int { return length($value); }'],
            ['<?php namespace App\Library\Domain; function f(string $value): int { return \strlen($value); }'],
            ['<?php namespace App\Library\Domain; function f() { return new \DateTimeImmutable(); }'],
            ['<?php namespace App\Library\Domain; function f() { return namespace\Book::class; }'],
        ];
    }

    public function test_application_can_use_its_own_ports_and_domain_but_cannot_use_framework_helpers(): void
    {
        $allowed = ['App\\Library\\Domain', 'App\\Library\\Application'];
        self::assertSame([], DependencyInspector::core('<?php namespace App\Library\Application;
            use App\Library\Application\Ports\Clock; use App\Library\Domain\Book;
            function f(Clock $clock, Book $book) { return $clock->now(); }', $allowed));
        self::assertNotEmpty(DependencyInspector::core('<?php namespace App\Library\Application; function f() { return app(); }', $allowed));
    }

    #[DataProvider('adapterConstructionSources')]
    public function test_adapter_construction_or_binding_requires_the_composition_point(string $source): void
    {
        self::assertNotEmpty(DependencyInspector::composition($source, false));
        self::assertSame([], DependencyInspector::composition($source, true));
    }

    public static function adapterConstructionSources(): array
    {
        return [
            ['<?php new \App\Library\Adapters\Outbound\Security\Hasher();'],
            ['<?php use App\Library\Adapters\Outbound\Security\Hasher as H; new H();'],
            ['<?php use App\Library\Adapters\Outbound\Security\{Hasher as H}; new H();'],
            ['<?php $container->bind("port", "adapter");'],
            ['<?php $container->singleton("port", fn () => new \App\Library\Adapters\Outbound\Security\Hasher());'],
            ['<?php $container->make("adapter");'],
            ['<?php $name = "adapter"; new $name();'],
            ['<?php app(\App\Library\Adapters\Outbound\Security\Hasher::class);'],
            ['<?php use function app as locate; locate("adapter");'],
        ];
    }

    public function test_using_an_inbound_adapter_without_constructing_it_is_not_a_binding(): void
    {
        self::assertSame([], DependencyInspector::composition('<?php
            use App\Library\Adapters\Inbound\Http\ApiExceptionRenderer;
            function f($error, $request) { return ApiExceptionRenderer::render($error, $request); }', false));
    }
}
