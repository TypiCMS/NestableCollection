<?php

/*
 * (c) Samuel De Backer <sdebacker@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TypiCMS;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @template TKey of array-key
 * @template TModel of Model
 *
 * @extends Collection<TKey, TModel>
 */
class NestableCollection extends Collection
{
    protected int $total;

    protected string $parentColumn = 'parent_id';

    protected bool $removeItemsWithMissingAncestor = true;

    protected string $indentChars = '    ';

    protected string $childrenName = 'items';

    protected string $parentRelation = 'parent';

    /** @param array<TKey, TModel> $items */
    public function __construct(array $items = [])
    {
        parent::__construct($items);

        $this->total = count($items);
    }

    /** @return self<TKey, TModel> */
    public function parentColumn(string $name): self
    {
        $this->parentColumn = $name;

        return $this;
    }

    /** @return self<TKey, TModel> */
    public function childrenName(string $name): self
    {
        $this->childrenName = $name;

        return $this;
    }

    /** @return self<TKey, TModel> */
    public function setIndent(string $indentChars): self
    {
        $this->indentChars = $indentChars;

        return $this;
    }

    /** @return self<TKey, TModel> */
    public function noCleaning(): self
    {
        $this->removeItemsWithMissingAncestor = false;

        return $this;
    }

    /** @return self<TKey, TModel> */
    public function nest(): self
    {
        if (! $this->parentColumn) {
            return $this;
        }

        /** @var array<TKey, TModel> $dictionary */
        $dictionary = $this->getDictionary();
        $this->items = $dictionary;

        $this->initializeChildrenCollections();

        $collection = $this->removeItemsWithMissingAncestor ? $this->rejectOrphans() : $this;

        $keysToDelete = $this->assignChildrenToParents($collection);

        $this->items = array_values($collection->except($keysToDelete)->all());

        return $this;
    }

    public function anAncestorIsMissing(Model $item): bool
    {
        /** @var int|string|null $parentId */
        $parentId = $item->{$this->parentColumn};

        if (! $parentId) {
            return false;
        }

        if (! $this->has($parentId)) {
            return true;
        }

        /** @var TModel $parent */
        $parent = $this[$parentId];

        return $this->anAncestorIsMissing($parent);
    }

    /**
     * @param  BaseCollection<array-key, Model>|null  $collection
     * @param  array<int|string, string>  $flattened
     * @return array<int|string, string>
     */
    public function listsFlattened(
        string $column = 'title',
        ?BaseCollection $collection = null,
        int $level = 0,
        array &$flattened = [],
        ?string $indentChars = null,
        mixed $parentString = null,
    ): array {
        $collection ??= $this;
        $indentChars ??= $this->indentChars;

        /** @var Model $item */
        foreach ($collection as $item) {
            $itemString = $this->buildFlattenedLabel($item, $column, $indentChars, $level, $parentString);
            /** @var int|string $key */
            $key = $item->getKey();
            $flattened[$key] = $itemString;

            /** @var BaseCollection<array-key, Model> $children */
            $children = $item->{$this->childrenName};

            if ($children->isNotEmpty()) {
                $this->listsFlattened(
                    $column,
                    $children,
                    $level + 1,
                    $flattened,
                    $indentChars,
                    $parentString ? $itemString : null,
                );
            }
        }

        return $flattened;
    }

    /**
     * @param  BaseCollection<array-key, Model>|null  $collection
     * @param  array<int|string, string>  $flattened
     * @return array<int|string, string>
     */
    public function listsFlattenedQualified(
        string $column = 'title',
        ?BaseCollection $collection = null,
        int $level = 0,
        array &$flattened = [],
        ?string $indentChars = null,
    ): array {
        return $this->listsFlattened($column, $collection, $level, $flattened, $indentChars, true);
    }

    public function total(): int
    {
        return $this->total;
    }

    public function getTotal(): int
    {
        return $this->total();
    }

    /** @return self<TKey, TModel> */
    public function setParents(): self
    {
        $this->setParentsRecursive($this);

        return $this;
    }

    protected function initializeChildrenCollections(): void
    {
        $this->each(function ($item): void {
            if (! $item->{$this->childrenName}) {
                $item->{$this->childrenName} = new BaseCollection;
            }
        });
    }

    protected function rejectOrphans(): static
    {
        /** @var static */
        return $this->reject(fn($item) => $item->{$this->parentColumn} && $this->anAncestorIsMissing($item));
    }

    /**
     * @param  self<array-key, Model>|BaseCollection<array-key, Model>  $collection
     * @return array<int, mixed>
     */
    protected function assignChildrenToParents(self|BaseCollection $collection): array
    {
        $keysToDelete = [];

        /** @var Model $item */
        foreach ($collection as $item) {
            if (! $item->{$this->parentColumn} || ! isset($collection[$item->{$this->parentColumn}])) {
                continue;
            }

            /** @var BaseCollection<array-key, Model> $parentChildren */
            $parentChildren = $collection[$item->{$this->parentColumn}]->{$this->childrenName};
            $parentChildren->push($item);
            $keysToDelete[] = $item->getKey();
        }

        return $keysToDelete;
    }

    protected function buildFlattenedLabel(
        Model $item,
        string $column,
        string $indentChars,
        int $level,
        mixed $parentString,
    ): string {
        /** @var string $value */
        $value = $item->{$column};

        if (! $parentString) {
            return str_repeat($indentChars, $level).$value;
        }

        if ($parentString === true) {
            return $value;
        }

        /** @var string $parentString */
        return $parentString.$indentChars.$value;
    }

    /** @param BaseCollection<array-key, Model> $items */
    protected function setParentsRecursive(BaseCollection $items, ?Model $parent = null): void
    {
        /** @var Model $item */
        foreach ($items as $item) {
            if ($parent) {
                $item->setRelation($this->parentRelation, $parent);
            }

            /** @var BaseCollection<array-key, Model> $children */
            $children = $item->{$this->childrenName};
            $this->setParentsRecursive($children, $item);
        }
    }
}
