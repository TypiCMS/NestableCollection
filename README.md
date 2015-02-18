# NestableCollection
A Laravel Package that extends Collection to handle unlimited nested items following adjacency list model.

## Installation
Run ```composer require typicms/nestablecollection```

## Usage
The model must have a **position** and **parent_id** attributes :

```php
protected $fillable = array(
    'position',
    'parent_id',
    // …
}
```

and must have this method :

```php
public function newCollection(array $models = array())
{
    return new \TypiCMS\NestableCollection($models, 'parent_id');
}
```

Now each time you get a collection of that model, it will be an instance of **TypiCMS\NestableCollection** in place of **Illuminate\Database\Eloquent\Collection**.  

If you want a tree of models, simply call the nest method :

```php
Model::get()->nest();
```
