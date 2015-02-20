# NestableCollection
A Laravel Package that extends Collection to handle unlimited nested items following adjacency list model.

## Installation
Run ```composer require typicms/nestablecollection```

## Usage
The model must have a **parent_id** attributes :

```php
protected $fillable = array(
    'parent_id',
    // …
}
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
