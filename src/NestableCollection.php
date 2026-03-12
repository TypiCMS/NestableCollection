<?php

/*
 * (c) Samuel De Backer <sdebacker@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TypiCMS;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class NestableCollection extends Collection
{
    protected int $total;

    protected string $parentColumn = 'parent_id';

    protected bool $removeItemsWithMissingAncestor = true;

    protected string $indentChars = '    ';

    protected string $childrenName = 'items';

    protected string $parentRelation = 'parent';

    public function __construct(array $items = [])
    {
        parent::__construct($items);

        $this->total = count($items);
    }

    public function parentColumn(string $name): self
    {
        $this->parentColumn = $name;

        return $this;
    }

    public function childrenName(string $name): self
    {
        $this->childrenName = $name;

        return $this;
    }

    public function setIndent(string $indentChars): self
    {
        $this->indentChars = $indentChars;

        return $this;
    }

    public function noCleaning(): self
    {
        $this->removeItemsWithMissingAncestor = false;

        return $this;
    }

    public function nest(): self
    {
        if (!$this->parentColumn) {
            return $this;
        }

        $this->items = $this->getDictionary();

        $this->initializeChildrenCollections();

        $collection = $this->removeItemsWithMissingAncestor ? $this->rejectOrphans() : $this;

        $keysToDelete = $this->assignChildrenToParents($collection);

        $this->items = array_values($collection->except($keysToDelete)->all());

        return $this;
    }

    public function anAncestorIsMissing(mixed $item): bool
    {
        if (!$item->{$this->parentColumn}) {
            return false;
        }

        if (!$this->has($item->{$this->parentColumn})) {
            return true;
        }

        return $this->anAncestorIsMissing($this[$item->{$this->parentColumn}]);
    }

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

        foreach ($collection as $item) {
            $itemString = $this->buildFlattenedLabel($item, $column, $indentChars, $level, $parentString);
            $flattened[$item->id] = $itemString;

            if ($item->{$this->childrenName}->isNotEmpty()) {
                $this->listsFlattened(
                    $column,
                    $item->{$this->childrenName},
                    $level + 1,
                    $flattened,
                    $indentChars,
                    $parentString ? $itemString : null,
                );
            }
        }

        return $flattened;
    }

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

    public function setParents(): self
    {
        $this->setParentsRecursive($this);

        return $this;
    }

    protected function initializeChildrenCollections(): void
    {
        $this->each(function ($item): void {
            if (!$item->{$this->childrenName}) {
                $item->{$this->childrenName} = new BaseCollection();
            }
        });
    }

    protected function rejectOrphans(): static
    {
        return $this->reject(function ($item) {
            return $item->{$this->parentColumn} && $this->anAncestorIsMissing($item);
        });
    }

    /** @return array<int, mixed> */
    protected function assignChildrenToParents(self|BaseCollection $collection): array
    {
        $keysToDelete = [];

        foreach ($collection as $item) {
            if (!$item->{$this->parentColumn} || !isset($collection[$item->{$this->parentColumn}])) {
                continue;
            }

            $collection[$item->{$this->parentColumn}]->{$this->childrenName}->push($item);
            $keysToDelete[] = $item->id;
        }

        return $keysToDelete;
    }

    protected function buildFlattenedLabel(
        mixed $item,
        string $column,
        string $indentChars,
        int $level,
        mixed $parentString,
    ): string {
        if (!$parentString) {
            return str_repeat($indentChars, $level) . $item->{$column};
        }

        if ($parentString === true) {
            return $item->{$column};
        }

        return $parentString . $indentChars . $item->{$column};
    }

    protected function setParentsRecursive(BaseCollection $items, mixed $parent = null): void
    {
        foreach ($items as $item) {
            if ($parent) {
                $item->setRelation($this->parentRelation, $parent);
            }

            $this->setParentsRecursive($item->{$this->childrenName}, $item);
        }
    }
}
