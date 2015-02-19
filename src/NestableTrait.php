<?php
namespace TypiCMS;

trait NestableTrait
{
    /**
     * Return a custom nested collection
     *
     * @param  array              $models
     * @return NestableCollection
     */
    public function newCollection(array $models = array())
    {
        return new NestableCollection($models);
    }
}
