<?php

declare(strict_types=1);

namespace TypiCMS;

trait NestableTrait
{
    /**
     * Return a custom nested collection.
     */
    public function newCollection(array $models = []): NestableCollection
    {
        return new NestableCollection($models);
    }
}
