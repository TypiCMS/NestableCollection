# NestableCollection

[![Tests](https://github.com/TypiCMS/NestableCollection/actions/workflows/tests.yml/badge.svg)](https://github.com/TypiCMS/NestableCollection/actions/workflows/tests.yml)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](https://github.com/TypiCMS/NestableCollection/blob/master/LICENCE)

A Laravel package that extends Eloquent collections to handle nested items following the adjacency list model.

## Requirements

- PHP ^8.3
- Laravel 12 or 13

## Installation

```bash
composer require typicms/nestablecollection
```

## Usage

The model must have a `parent_id` attribute:

```php
protected $fillable = [
    'parent_id',
    // …
];
```

and must use the `NestableTrait`:

```php
use TypiCMS\NestableTrait;

class Category extends Model
{
    use NestableTrait;
}
```

Now each time you retrieve a collection of that model, it will be an instance of `TypiCMS\NestableCollection` instead of `Illuminate\Database\Eloquent\Collection`.

To get a tree of models, call the `nest()` method on a collection ordered by `parent_id`:

```php
Category::orderBy('parent_id')->get()->nest();
```

You will probably want a `position` column as well, so order first by `parent_id` then by `position`:

```php
Category::orderBy('parent_id')->orderBy('position')->get()->nest();
```

## Custom parent column

By default, the parent column is `parent_id`. You can change it with the `parentColumn()` method:

```php
$collection->parentColumn('category_id')->nest();
```

## Custom children name

By default, subcollections are named `items`. You can change it with the `childrenName()` method:

```php
$collection->childrenName('children')->nest();
```

## Indented and flattened list

The `listsFlattened()` method generates the tree as a flattened list with id as keys and title as values, perfect for select/option elements:

```php
[
    22 => 'Item 1 Title',
    10 => '    Child 1 Title',
    17 => '    Child 2 Title',
    14 => 'Item 2 Title',
]
```

First call `nest()`, then `listsFlattened()`:

```php
Category::orderBy('parent_id')->get()->nest()->listsFlattened();
```

By default it looks for a `title` column. Pass a custom column name as the first parameter:

```php
$collection->nest()->listsFlattened('name');
```

Four spaces are used to indent by default. Use `setIndent()` to customize:

```php
$collection->nest()->setIndent('> ')->listsFlattened();
```

Result:

```php
[
    22 => 'Item 1 Title',
    10 => '> Child 1 Title',
    17 => '> Child 2 Title',
    14 => 'Item 2 Title',
]
```

## Fully qualified flattened list

The `listsFlattenedQualified()` method builds full paths instead of indentation:

```php
$collection->nest()->setIndent(' / ')->listsFlattenedQualified();
```

Result:

```php
[
    22 => 'Item 1 Title',
    10 => 'Item 1 Title / Child 1 Title',
    17 => 'Item 1 Title / Child 2 Title',
    14 => 'Item 2 Title',
]
```

## Setting parent relations

The `setParents()` method sets the `parent` relation on each nested item, so you can traverse up the tree without querying the database:

```php
$nested = Category::orderBy('parent_id')->get()->nest()->setParents();

$nested[0]->items[0]->parent; // Returns the parent model
```

## Nesting a subtree

By default, items with a missing ancestor are removed. To nest a branch of a tree, use the `noCleaning()` method:

```php
Category::orderBy('parent_id')->get()->noCleaning()->nest();
```

## Testing

```bash
composer require pestphp/pest --dev
vendor/bin/pest
```

## License

The MIT License (MIT). Please see [License File](LICENCE) for more information.
