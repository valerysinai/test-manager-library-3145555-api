<?php

declare(strict_types=1);

namespace Tests\Architecture;

/** Verificación estática del código del repositorio; no evalúa fuentes ni carga dependencias. */
final class DependencyInspector
{
    public static function core(string $source, array $allowed): array
    {
        [$tokens, $namespace, $imports, $skip, $errors] = self::context($source);
        foreach ($imports as $kind => $entries) {
            foreach ($entries as $name) {
                if (!self::allowed($name, $allowed, $kind)) {
                    $errors[] = 'import: '.$name;
                }
            }
        }
        foreach ($tokens as $index => $token) {
            if (isset($skip[$index])) {
                continue;
            }
            $previous = $tokens[$index - 1] ?? [null, ''];
            $next = $tokens[$index + 1] ?? [null, ''];
            if ($token[0] === T_VARIABLE && $next[1] === '('
                && !in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true)) {
                $errors[] = 'dynamic function call';
            }
            if ($token[0] === T_NEW && !self::nameToken($next) && $next[0] !== T_CLASS) {
                $errors[] = 'dynamic construction';
            }
            if (!self::nameToken($token)) {
                continue;
            }
            if (in_array($previous[0], [T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_CASE,
                T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)
                || ($previous[1] === '&' && ($tokens[$index - 2][0] ?? null) === T_FUNCTION)
                || $next[1] === ':') {
                continue;
            }
            if (in_array(strtolower($token[1]), ['self', 'parent', 'static', 'int', 'float', 'string', 'bool',
                'mixed', 'void', 'never', 'object', 'true', 'false', 'null', 'iterable'], true)) {
                continue;
            }
            $kind = $next[1] === '(' && $previous[0] !== T_NEW ? 'function' : 'class';
            $name = self::resolve($token[1], $namespace, $imports[$kind], $kind);
            if ($kind === 'class' && self::nativeConstant($token[1])) {
                continue;
            }
            if (!self::allowed($name, $allowed, $kind)) {
                $errors[] = $kind.': '.$name;
            }
        }

        return array_values(array_unique($errors));
    }

    public static function composition(string $source, bool $compositionPoint): array
    {
        if ($compositionPoint) {
            return [];
        }
        [$tokens, $namespace, $imports, $skip, $errors] = self::context($source);
        foreach ($tokens as $index => $token) {
            if (isset($skip[$index])) {
                continue;
            }
            $next = $tokens[$index + 1] ?? [null, ''];
            if ($token[0] === T_NEW) {
                if (!self::nameToken($next) && $next[0] !== T_CLASS) {
                    $errors[] = 'dynamic construction outside composition';
                } elseif (self::nameToken($next)) {
                    $name = self::resolve($next[1], $namespace, $imports['class'], 'class');
                    if (str_starts_with(strtolower($name), 'app\\library\\adapters\\')) {
                        $errors[] = 'adapter construction outside composition: '.$name;
                    }
                }
            }
            $previous = $tokens[$index - 1] ?? [null, ''];
            if ($token[0] === T_STRING && $next[1] === '('
                && in_array(strtolower($token[1]), ['bind', 'bindif', 'singleton', 'singletonif',
                    'scoped', 'scopedif', 'instance', 'make', 'makewith', 'resolve'], true)
                && in_array($previous[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON], true)) {
                $errors[] = 'binding or resolution outside composition';
            }
        }

        return array_values(array_unique($errors));
    }

    private static function allowed(string $name, array $prefixes, string $kind): bool
    {
        $lower = strtolower($name);
        if (in_array($lower, ['pdo', 'pdostatement', 'pdoexception'], true)) {
            return false;
        }
        if ($kind === 'function') {
            return function_exists($name) && (new \ReflectionFunction($name))->isInternal();
        }
        if ($kind === 'const') {
            return self::nativeConstant($name);
        }
        foreach ($prefixes as $prefix) {
            if ($lower === strtolower($prefix) || str_starts_with($lower, strtolower($prefix).'\\')) {
                return true;
            }
        }

        return (class_exists($name, false) || interface_exists($name, false) || trait_exists($name, false))
            && (new \ReflectionClass($name))->isInternal();
    }

    private static function nativeConstant(string $name): bool
    {
        foreach (get_defined_constants(true) as $group => $constants) {
            if ($group !== 'user' && array_key_exists(ltrim($name, '\\'), $constants)) {
                return true;
            }
        }

        return false;
    }

    private static function resolve(string $name, string $namespace, array $imports, string $kind): string
    {
        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }
        if (str_starts_with(strtolower($name), 'namespace\\')) {
            return $namespace.'\\'.substr($name, 10);
        }
        $parts = explode('\\', $name, 2);
        if (isset($imports[strtolower($parts[0])])) {
            return $imports[strtolower($parts[0])].(isset($parts[1]) ? '\\'.$parts[1] : '');
        }
        if ($kind === 'function' && !isset($parts[1]) && function_exists($name)
            && (new \ReflectionFunction($name))->isInternal()) {
            return $name;
        }

        return $namespace === '' ? $name : $namespace.'\\'.$name;
    }

    private static function nameToken(array $token): bool
    {
        return in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE], true);
    }

    private static function context(string $source): array
    {
        $tokens = [];
        foreach (token_get_all($source, TOKEN_PARSE) as $token) {
            if (is_array($token)) {
                if (!in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $tokens[] = [$token[0], $token[1]];
                }
            } else {
                $tokens[] = [null, $token];
            }
        }
        $namespace = '';
        $imports = ['class' => [], 'function' => [], 'const' => []];
        $skip = [];
        $errors = [];
        foreach ($tokens as $index => $token) {
            if ($token[0] === T_NAMESPACE) {
                for ($cursor = $index; isset($tokens[$cursor]); $cursor++) {
                    $skip[$cursor] = true;
                    if (self::nameToken($tokens[$cursor])) {
                        $namespace = $tokens[$cursor][1];
                    }
                    if (in_array($tokens[$cursor][1], [';', '{'], true)) {
                        break;
                    }
                }
            }
            if ($token[0] !== T_USE || ($tokens[$index + 1][1] ?? '') === '(') {
                continue;
            }
            $raw = '';
            for ($cursor = $index; isset($tokens[$cursor]); $cursor++) {
                $skip[$cursor] = true;
                if ($tokens[$cursor][1] === ';') {
                    break;
                }
                if ($cursor !== $index) {
                    $raw .= in_array($tokens[$cursor][0], [T_AS, T_FUNCTION, T_CONST], true)
                        ? ' '.$tokens[$cursor][1].' ' : $tokens[$cursor][1];
                }
            }
            $kind = 'class';
            if (preg_match('/\A\s*(function|const)\s+/', $raw, $type) === 1) {
                $kind = $type[1];
                $raw = substr($raw, strlen($type[0]));
            }
            $prefix = '';
            if (($opening = strpos($raw, '{')) !== false) {
                $prefix = rtrim(substr($raw, 0, $opening), '\\ ').'\\';
                $raw = substr($raw, $opening + 1, strrpos($raw, '}') - $opening - 1);
            }
            foreach (explode(',', $raw) as $entry) {
                if (preg_match('/\A\s*(?:(function|const)\s+)?([a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*)(?:\s+as\s+([a-zA-Z_][a-zA-Z0-9_]*))?\s*\z/', $entry, $parts) !== 1) {
                    $errors[] = 'unresolved import';
                    continue;
                }
                $entryKind = ($parts[1] ?? '') !== '' ? $parts[1] : $kind;
                $name = ltrim($prefix.$parts[2], '\\');
                $segments = explode('\\', $name);
                $alias = ($parts[3] ?? '') !== '' ? $parts[3] : end($segments);
                $imports[$entryKind][strtolower($alias)] = $name;
            }
        }

        return [$tokens, $namespace, $imports, $skip, $errors];
    }
}
