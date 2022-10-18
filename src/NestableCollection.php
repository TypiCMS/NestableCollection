<?php

/*
 * (c) Samuel De Backer <sdebacker@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TypiCMS;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;

class NestableCollection extends Collection
{
    protected int $total;

    protected string $parentColumn;

    protected bool $removeItemsWithMissingAncestor = true;

    protected string $indentChars = '    ';

    protected string $childrenName = 'items';

    protected string $parentRelation = 'parent';

    public function __construct(array $items = [])
    {
        parent::__construct($items);
        $this->parentColumn = 'parent_id';
        $this->total = count($items);
    }

    public function childrenName(string $name): self
    {
        $this->childrenName = $name;

        return $this;
    }

    /**
     * Nest items.
     */
    public function nest(): self
    {
        $parentColumn = $this->parentColumn;
        if (!$parentColumn) {
            return $this;
        }

        // Set id as keys.
        $this->items = $this->getDictionary();

        $keysToDelete = [];

        // Add empty collection to each items.
        $collection = $this->each(function ($item) {
            if (!$item->{$this->childrenName}) {
                $item->{$this->childrenName} = app()->make('Illuminate\Support\Collection');
            }
        });

        // Remove items with missing ancestor.
        if ($this->removeItemsWithMissingAncestor) {
            $collection = $this->reject(function ($item) use ($parentColumn) {
                if ($item->{$parentColumn}) {
                    $missingAncestor = $this->anAncestorIsMissing($item);

                    return $missingAncestor;
                }
            });
        }

        // Add items to children collection.
        foreach ($collection->items as $item) {
            if ($item->{$parentColumn} && isset($collection[$item->{$parentColumn}])) {
                $collection[$item->{$parentColumn}]->{$this->childrenName}->push($item);
                // @phpstan-ignore-next-line
                $keysToDelete[] = $item->id;
            }
        }

        // Delete moved items.
        $this->items = array_values(Arr::except($collection->items, $keysToDelete));

        return $this;
    }

    /**
     * Recursive function that flatten a nested Collection
     * with characters (default is four spaces).
     */
    public function listsFlattened(string $column = 'title', BaseCollection $collection = null, int $level = 0, array &$flattened = [], ?string $indentChars = null, mixed $parentString = null): array
    {
        $collection = $collection ?: $this;
        $indentChars = $indentChars ?: $this->indentChars;
        foreach ($collection as $item) {
            if ($parentString) {
                $item_string = ($parentString === true) ? $item->{$column} : $parentString.$indentChars.$item->{$column};
            } else {
                $item_string = str_repeat($indentChars, $level).$item->{$column};
            }

            $flattened[$item->id] = $item_string;
            if ($item->{$this->childrenName}) {
                $this->listsFlattened($column, $item->{$this->childrenName}, $level + 1, $flattened, $indentChars, ($parentString) ? $item_string : null);
            }
        }

        return $flattened;
    }

    /**
     * Returns a fully qualified version of listsFlattened.
     */
    public function listsFlattenedQualified(string $column = 'title', BaseCollection $collection = null, int $level = 0, array &$flattened = [], ?string $indentChars = null): array
    {
        return $this->listsFlattened($column, $collection, $level, $flattened, $indentChars, true);
    }

    /**
     * Change the default indent characters when flattening lists.
     */
    public function setIndent(string $indentChars): self
    {
        $this->indentChars = $indentChars;

        return $this;
    }

    /**
     * Force keeping items that have a missing ancestor.
     */
    public function noCleaning(): self
    {
        $this->removeItemsWithMissingAncestor = false;

        return $this;
    }

    /**
     * Check if an ancestor is missing.
     */
    public function anAncestorIsMissing(mixed $item): bool
    {
        $parentColumn = $this->parentColumn;
        if (!$item->{$parentColumn}) {
            return false;
        }
        if (!$this->has($item->{$parentColumn})) {
            return true;
        }
        $parent = $this[$item->{$parentColumn}];

        return $this->anAncestorIsMissing($parent);
    }

    /**
     * Get total items in nested collection.
     */
    public function total(): int
    {
        return $this->total;
    }

    /**
     * Get total items for laravel 4 compatibility.
     */
    public function getTotal(): int
    {
        return $this->total();
    }

    /**
     * Sets the $item->parent relation for each item in the
     * NestableCollection to be the parent it has in the collection
     * so it can be used without querying the database.
     */
    public function setParents(): self
    {
        $this->setParentsRecursive($this);

        return $this;
    }

    protected function setParentsRecursive(BaseCollection &$items, &$parent = null): void
    {
        foreach ($items as $item) {
            if ($parent) {
                $item->setRelation($this->parentRelation, $parent);
            }
            $this->setParentsRecursive($item->{$this->childrenName}, $item);
        }
    }
}
