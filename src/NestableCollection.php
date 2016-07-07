<?php

/*
 * (c) Samuel De Backer <sdebacker@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TypiCMS;

use App;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class NestableCollection extends Collection
{
    private $total;
    private $parentColumn;

    public function __construct($items = [])
    {
        parent::__construct($items);
        $this->parentColumn = 'parent_id';
        $this->total = count($items);
    }

    /**
     * Nest items.
     *
     * @return mixed NestableCollection
     */
    public function nest()
    {
        $parentColumn = $this->parentColumn;
        if (!$parentColumn) {
            return $this;
        }

        // Set id as keys.
        $this->items = $this->getDictionary();

        $keysToDelete = [];

        $collection = $this->each(function ($item) {
            // Add empty collection to each items.
            if (!$item->items) {
                $item->items = App::make('Illuminate\Support\Collection');
            }
        })->reject(function ($item) use ($parentColumn) {
            // Remove items with a missing ancestor.
            if ($item->$parentColumn) {
                $missingAncestor = $this->anAncestorIsMissing($item);
                return $missingAncestor;
            }
        });

        // Add items to children collection.
        foreach ($collection->items as $key => $item) {
            if ($item->$parentColumn && isset($collection[$item->$parentColumn])) {
                $collection[$item->$parentColumn]->items->push($item);
                $keysToDelete[] = $item->id;
            }
        }

        // Delete moved items.
        $this->items = array_values(array_except($collection->items, $keysToDelete));

        return $this;
    }

    /**
     * Recursive function that flatten a nested Collection
     * with characters (default is four spaces).
     *
     * @param BaseCollection|null $collection
     * @param string              $column
     * @param int                 $level
     * @param array               &$flattened
     * @param string              $indentChars
     *
     * @return array
     */
    public function listsFlattened($column = 'title', BaseCollection $collection = null, $level = 0, array &$flattened = [], $indentChars = '&nbsp;&nbsp;&nbsp;&nbsp;')
    {
        $collection = $collection ?: $this;
        foreach ($collection as $item) {
            $flattened[$item->id] = str_repeat($indentChars, $level).$item->$column;
            if ($item->items) {
                $this->listsFlattened($column, $item->items, $level + 1, $flattened, $indentChars);
            }
        }

        return $flattened;
    }

    /**
     * Check if an ancestor is missing.
     *
     * @param $item
     *
     * @return boolean
     */
    public function anAncestorIsMissing($item)
    {
        $parentColumn = $this->parentColumn;
        if (!$item->$parentColumn) {
            return false;
        }
        if (!$this->has($item->$parentColumn)) {
            return true;
        }
        $parent = $this[$item->$parentColumn];

        return $this->anAncestorIsMissing($parent);
    }

    /**
     * Get total items in nested collection.
     *
     * @return int
     */
    public function total()
    {
        return $this->total;
    }

    /**
     * Get total items for laravel 4 compatibility.
     *
     * @return int
     */
    public function getTotal()
    {
        return $this->total();
    }
}
