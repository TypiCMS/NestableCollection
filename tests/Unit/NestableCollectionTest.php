<?php

use Illuminate\Database\Eloquent\Collection;
use Tests\Stubs\Item;
use TypiCMS\NestableCollection;

function makeItem(int $id, ?int $parentId = null, string $title = ''): Item
{
    $item = new Item;
    $item->id = $id;
    $item->parent_id = $parentId;
    $item->title = $title ?: "Item {$id}";

    return $item;
}

function makeTree(): NestableCollection
{
    return new NestableCollection([
        makeItem(1, null, 'Root 1'),
        makeItem(2, 1, 'Child 1.1'),
        makeItem(3, 1, 'Child 1.2'),
        makeItem(4, null, 'Root 2'),
        makeItem(5, 2, 'Grandchild 1.1.1'),
    ]);
}

it('is an instance of Eloquent Collection', function () {
    $collection = new NestableCollection;

    expect($collection)->toBeInstanceOf(Collection::class);
});

it('returns total count of items before nesting', function () {
    $collection = makeTree();

    expect($collection->total())->toBe(5)->and($collection->getTotal())->toBe(5);
});

it('nests items into a tree structure', function () {
    $nested = makeTree()->nest();

    expect($nested)
        ->toHaveCount(2)
        ->and($nested[0]->title)
        ->toBe('Root 1')
        ->and($nested[1]->title)
        ->toBe('Root 2')
        ->and($nested[0]->items)
        ->toHaveCount(2)
        ->and($nested[1]->items)
        ->toHaveCount(0)
        ->and($nested[0]->items[0]->title)
        ->toBe('Child 1.1')
        ->and($nested[0]->items[0]->items)
        ->toHaveCount(1)
        ->and($nested[0]->items[0]->items[0]->title)
        ->toBe('Grandchild 1.1.1');
});

it('preserves total count after nesting', function () {
    $nested = makeTree()->nest();

    expect($nested->total())->toBe(5);
});

it('returns itself when parent column is empty', function () {
    $collection = new NestableCollection([makeItem(1)]);
    $collection->parentColumn('');

    $result = $collection->nest();

    expect($result)->toHaveCount(1);
});

it('allows setting a custom parent column', function () {
    $item1 = new Item;
    $item1->id = 1;
    $item1->category_id = null;
    $item1->title = 'Root';

    $item2 = new Item;
    $item2->id = 2;
    $item2->category_id = 1;
    $item2->title = 'Child';

    $collection = new NestableCollection([$item1, $item2]);
    $nested = $collection->parentColumn('category_id')->nest();

    expect($nested)->toHaveCount(1)->and($nested[0]->items)->toHaveCount(1);
});

it('allows setting a custom children name', function () {
    $collection = new NestableCollection([
        makeItem(1, null, 'Root'),
        makeItem(2, 1, 'Child'),
    ]);

    $nested = $collection->childrenName('children')->nest();

    expect($nested[0]->children)->toHaveCount(1)->and($nested[0]->children[0]->title)->toBe('Child');
});

it('removes items with a missing ancestor by default', function () {
    $collection = new NestableCollection([
        makeItem(1, null, 'Root'),
        makeItem(2, 1, 'Child'),
        makeItem(3, 99, 'Orphan'),
    ]);

    $nested = $collection->nest();

    expect($nested)->toHaveCount(1)->and($nested[0]->title)->toBe('Root');
});

it('keeps items with missing ancestor when noCleaning is set', function () {
    $collection = new NestableCollection([
        makeItem(1, null, 'Root'),
        makeItem(3, 99, 'Orphan'),
    ]);

    $nested = $collection->noCleaning()->nest();

    expect($nested)->toHaveCount(2);
});

it('removes items whose ancestor chain is broken', function () {
    $collection = new NestableCollection([
        makeItem(1, null, 'Root'),
        makeItem(2, 1, 'Child'),
        makeItem(3, 50, 'Missing parent'),
        makeItem(4, 3, 'Child of missing parent'),
    ]);

    $nested = $collection->nest();

    expect($nested)->toHaveCount(1)->and($nested[0]->items)->toHaveCount(1);
});

it('detects missing ancestor via nest removing orphans', function () {
    $collection = new NestableCollection([
        makeItem(1, null, 'Root'),
        makeItem(2, 1, 'Child'),
        makeItem(3, 99, 'Orphan'),
    ]);

    $nested = $collection->nest();

    expect($nested)
        ->toHaveCount(1)
        ->and($nested[0]->items)
        ->toHaveCount(1)
        ->and($nested[0]->items[0]->title)
        ->toBe('Child');
});

it('keeps root items that have no parent', function () {
    $collection = new NestableCollection([
        makeItem(1, null, 'Root'),
    ]);

    $nested = $collection->nest();

    expect($nested)->toHaveCount(1)->and($nested[0]->title)->toBe('Root');
});

it('flattens a nested collection with indentation', function () {
    $nested = makeTree()->nest();
    $flattened = $nested->listsFlattened('title');

    $nbsp4 = str_repeat("\xC2\xA0", 4);
    $nbsp8 = str_repeat("\xC2\xA0", 8);

    expect($flattened)->toEqual([
        1 => 'Root 1',
        2 => $nbsp4 . 'Child 1.1',
        5 => $nbsp8 . 'Grandchild 1.1.1',
        3 => $nbsp4 . 'Child 1.2',
        4 => 'Root 2',
    ]);
});

it('flattens with custom indent characters', function () {
    $nested = makeTree()->nest();
    $flattened = $nested->setIndent('--')->listsFlattened('title');

    expect($flattened[2])->toBe('--Child 1.1')->and($flattened[5])->toBe('----Grandchild 1.1.1');
});

it('flattens with fully qualified paths', function () {
    $nested = makeTree()->setIndent(' / ')->nest();
    $flattened = $nested->listsFlattenedQualified('title');

    expect($flattened[1])
        ->toBe('Root 1')
        ->and($flattened[2])
        ->toBe('Root 1 / Child 1.1')
        ->and($flattened[5])
        ->toBe('Root 1 / Child 1.1 / Grandchild 1.1.1');
});

it('flattens with custom indent in qualified mode', function () {
    $nested = makeTree()->nest();
    $flattened = $nested->setIndent(' / ')->listsFlattenedQualified('title');

    expect($flattened[2])
        ->toBe('Root 1 / Child 1.1')
        ->and($flattened[5])
        ->toBe('Root 1 / Child 1.1 / Grandchild 1.1.1');
});

it('sets parent relations recursively', function () {
    $nested = makeTree()->nest();
    $nested->setParents();

    $child = $nested[0]->items[0];
    $grandchild = $nested[0]->items[0]->items[0];

    expect($child->getRelation('parent')->id)->toBe(1)->and($grandchild->getRelation('parent')->id)->toBe(2);
});

it('does not set parent relation on root items', function () {
    $nested = makeTree()->nest();
    $nested->setParents();

    expect($nested[0]->relationLoaded('parent'))->toBeFalse();
});

it('handles an empty collection', function () {
    $collection = new NestableCollection;
    $nested = $collection->nest();

    expect($nested)->toHaveCount(0)->and($nested->total())->toBe(0);
});

it('handles a flat collection with no parents', function () {
    $collection = new NestableCollection([
        makeItem(1, null, 'A'),
        makeItem(2, null, 'B'),
        makeItem(3, null, 'C'),
    ]);

    $nested = $collection->nest();

    expect($nested)->toHaveCount(3);
});

it('uses NestableCollection via NestableTrait', function () {
    $collection = (new Item)->newCollection([makeItem(1)]);

    expect($collection)->toBeInstanceOf(NestableCollection::class);
});
