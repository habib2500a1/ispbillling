<?php

namespace App\Models\Concerns;

/**
 * Persist attributes from billing/network services (bypasses narrowed $fillable).
 */
trait CreatesFromTrustedSource
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function createTrusted(array $attributes): static
    {
        $model = new static;
        $model->forceFill($attributes);
        $model->save();

        return $model->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTrusted(array $attributes): static
    {
        $this->forceFill($attributes);
        $this->save();

        return $this->refresh();
    }
}
