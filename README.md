# NestableCollection

[![Latest Version on Packagist](https://img.shields.io/packagist/v/typicms/nestablecollection.svg?style=flat-square)](https://packagist.org/packages/typicms/nestablecollection)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/8dc349b4-951d-4098-af3a-c2911937a901/mini.png)](https://insight.sensiolabs.com/projects/8dc349b4-951d-4098-af3a-c2911937a901)
[![StyleCI](https://styleci.io/repos/30971812/shield)](https://styleci.io/repos/30971812)

A Laravel/Lumen Package that extends collections to handle nested items following adjacency list model.

## Installation
Run ```composer require typicms/nestablecollection```

## Usage
The model must have a **parent_id** attributes :

```php
protected $fillable = [
    'parent_id',
    // …
];
```

and must use the following trait:

```php
use TypiCMS\NestableTrait;
```

Now each time you get a collection of that model, it will be an instance of **TypiCMS\NestableCollection** in place of **Illuminate\Database\Eloquent\Collection**.

If you want a tree of models, simply call the nest method on a collection ordered by parent_id asc :

```php
Model::orderBy('parent_id')->get()->nest();
```

Of course you will probably want a position column as well. So you will have to order first by parent_id asc and then by position asc.

## Change the name of subcollections

By default, the name of the subcollections is **items**, but you can change it by calling the ```childrenName($name)``` method :
For example if you want your subcollections being named **children**:

```php
$collection->childrenName('children')->nest();
```

## Indented and flattened list

```listsFlattened()``` method generate the tree as a flattened list with id as keys and title as values, perfect for select/option, for example :

```php
[
    '22' => 'Item 1 Title',
    '10' => '    Child 1 Title',
    '17' => '    Child 2 Title',
    '14' => 'Item 2 Title',
]
```

To use it, first call the `nest()` method, followed by the `listsFlattened()` method:

```php
Model::orderBy('parent_id')->get()->nest()->listsFlattened();
```

By default it will look for a `title` column. You can send a custom column name as first parameter:

```php
Model::orderBy('parent_id')->get()->nest()->listsFlattened('name');
```

Four spaces are used to indent by default, to use your own use the `setIndent()` method, followed by the `listsFlattened()` method:

```php
Model::orderBy('parent_id')->get()->nest()->setIndent('> ')->listsFlattened();
```

Results:

```php
[
    '22' => 'Item 1 Title',
    '10' => '> Child 1 Title',
    '17' => '> Child 2 Title',
    '14' => 'Item 2 Title',
]
```

## Nesting a subtree

This package remove items that have missing ancestor, this doesn’t allow you to nest a branch of a tree.
To avoid this, you can use the ```noCleaning()``` method:

```php
Model::orderBy('parent_id')->get()->noCleaning()->nest();
```

