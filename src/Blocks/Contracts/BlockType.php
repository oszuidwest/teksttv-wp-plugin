<?php

namespace TekstTV\Blocks\Contracts;

/**
 * Contract for a TekstTV loop or ticker block implementation (admin + persistence + runtime build).
 *
 * Implementations register themselves via BlockRegistry::register() in {@see register()}.
 */
interface BlockType
{
    public static function register(): void;

    /**
     * Render admin fields for one block row.
     *
     * @param array<string, mixed> $data Current saved values for this row.
     */
    public static function render_fields(int|string $index, array $data, string $prefix): void;

    /**
     * Sanitize POST input for one block row.
     *
     * @param array<string, mixed> $raw Raw POST fragment for this row.
     * @return array<string, mixed>|null Null means skip storing this row (e.g. empty optional ticker text).
     */
    public static function save(array $raw): ?array;

    /**
     * Produce slides (loop) or ticker messages from stored row data.
     *
     * @param array<string, mixed> $data Stored row including `type`.
     * @return list<array<string, mixed>>
     */
    public static function build(array $data, string $channel): array;
}
