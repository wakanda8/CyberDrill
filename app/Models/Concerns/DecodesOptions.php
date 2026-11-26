<?php

namespace App\Models\Concerns;

/**
 * For models with an `options` column cast to array.
 *
 * @property mixed $options
 */
trait DecodesOptions
{
    /**
     * The answer options. Seeders and the admin form store them JSON-encoded on top of
     * the array cast, so the cast can hand back a JSON string instead of an array.
     *
     * @return list<array<string, mixed>>
     */
    public function decodedOptions(): array
    {
        $options = is_string($this->options) ? json_decode($this->options, true) : $this->options;

        return is_array($options) ? array_values($options) : [];
    }
}
