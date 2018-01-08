# About
This is a fairly simple extra layer that works on top of Laravel's amazing Eloquent. Its objective is to help you make data selection/retrieval from the database more clean and standard across your codebase.

## Why and how it works
Well, with Laravel we usually use the models we created to select data (like in `Client::all()`), Eloquent of course makes customizing this simple query very straight formward by offering methods like `where(...)`, `limit(...)` .. etc. This of course is great, but the problems happens when you find yourself repeating much of this _Where, OrderBy, Limit, ...etc_ everywhere. These methods simply represents much of the logic behind the whole application, because you use them to control the final output that will be shown to the user. And because we always find ourselves changing these conditions all the time in different places we always wind up with different queries that are supposed to be doing the same thing! 

Of course you can use scopes to solve this problem, and it's sufficent, but I am trying to suggest a different approach, one that you can consider abit cleaner and much more elegant, away of polluting your models. You will simply create an API for each Model, a "Selector" that you can call anywhere and ask for a specific query. Lets take an example; you have a _Client_ model, which represents your "clients" database table:
```php
class Client extends Model{
    public $table = 'clients';
    protected $guarded = [];
    
    public function orders(){
        return $this->hasMany(Order::class);
    }
}
```
Later you will find yourself adding more and more scopes like these:
```php
public function scopeActiveOnly($query){
    return $query->where('active', true);
}

public function scopeRegisteredThisMonth($query){
    return $query->whereMonth('created_at', \Carbon::now());
}
```
With this library you will get to separate these scopes from the model into a cleaner interface, where you can focus better on them, group them into meaningful api-like calls and serve your application logic better!

# How to use it
## Installation
Simply pull it into your project via composer:
```
composer require tamkeenlms/laravel-data-selector
```

Now, for each model you have you will create a Selector class, one that will extend _DataSelector\Selector_, and then you will get to call this selector and ask it for x or y. I suggest you create a new directory for your selectors under _/app_.
```php
<?php namespace app\selectors;
class Clients extends DataSelector\Selector{
    public function __contructor(){
        parent::__construct(Client::class);
    }
}

$clients = new Clients;
$clients->get(); // All clients (Collection)
```
Here we simply created a Selector class for the Client Model. Inside this class's constructor we will need to call the parent class (DataSelector\Selector) contstructor and pass it the Model. Beside the model you can pass the default columns that sould be selected everytime this selector is called. Also you can recieve these columns from each instance of this selector. More over, with each instance you can ask it to include the trashed (soft-deleted) items along.
```php
<?php namespace app\selectors;
class Clients extends DataSelector\Selector{
    public function __contructor(array $columns = null, $withDeleted = false){
        $defaultColumns = ['id', 'name', 'created_at']; // The default, in case the instance didn't provide a list
        parent::__construct(Client::class, $columns, $defaultColumns, $withDeleted);
    }
}

$clients = new Clients(['id', 'name']); // Will select only the id and the name for each Client (excluding the trashed ones)
$allClients = new Clients(['*'], true); // Will select * from all clients, including the trashed
```
Now. Inside the selector instance itself you can define the methods that will help you represent the part of your logic that is linked with the selection of data:
```php
public function activeAndNew(){
    $this->where('active', true)
        ->where('created_at', '=>', Carbon::parse('2018-01-01'));
}
```
And later you can call it from any instance of this Selector.
```php
$clients = (new Clients())->activeAndNew()->get();
```

# Selector methods
### `select($olumns, $override)`
You can use this to set the columns to select in the final query. By default this will add to the existing list of columns set earlier in the ___construct_ or, but you can override this list by passing `true` next to the columns list.
```php
$clients->select(['id', 'name']);
$clients->select('id, name, LEFT(bio) AS `bio`'); // You can also provide a raw statement
$clients->select(['*'], true); // Overrides the above
```

### `includeTrashed()`
This will include the trashed (soft-deleted) records in the final results. This of course depends on Larvel's `SoftDeletes` trait, and if it's not called in the model using this method will trigger an error. You can read more about soft deletion in Laravel [here](https://laravel.com/docs/5.5/eloquent#soft-deleting).

### `onlyTrashed()`
This will return only the trashed (soft-deleted) records.

### `where(...$args)`
You can use this method the same way you would use it on a model.
```php
$clients->where('id', 1);
$clients->where('id', '=', 1);
$clients->where('id', '!=', 1);
$clients->where('name', 'LIKE', '%John%');
```

### `whereIn($column, $values)`
This creates a WHERE IN statment
```php
$clients->whereIn('id', [1, 2, 3]);
```

### `orderBy($column, $asc = true)`
Adds an ORDER BY statement to the query.
```php
$clients->orderBy('created_at'); // Create first at first
$clients->orderBy('created_at', false); // Created last at first
```
### `latestFirst()`
It orders the results latest first, based on the "created_at" value.
```php
$clients->lastestFirst();
```

### `oldestFirst()`
The oldest rows (based on the value of "created_at") will be at the top.

### `lastModifiedFirst()`
### `lastModifiedLast()`

### `cancel()`
This cancels the whole query and returns an empty collection.
```php
$clients = new Clients;
if($request->ids){
    $clients->whereIn('id', $request->ids);
}else{
    $clients->cancel();
}
return ['clients' => $clients];
```

### `get()`
Returns the results (as a collection)
```php
$clients = (new Clients(['id', 'name']))->get();
```

### `getCount()`
Returns only the count of the results. 

### `isEmpty()`
Returns whether records were found for your query or not.

### `isNotEmpty()`

### `getSQL()`
Returns the SQL code for the query you created.
```php
$clients = new Clients(['id', 'name']);
$clients->activeOnly();

$clients->getSQL(); // select id, name from clients where active = 1
```

# Pagination
Laravel makes pagination very simple and straight forward. And it's the same here, just use `paginate($itemsPerPage, $queryString)`. Where `$itemsPerPage` is the number of items for each page and $queryString is an optional argument for any url query string values that should be appended to the pagination url. 
```php
$clients = new Clients();
$clients->paginate(5, ['format' => 'csv']);
```
Now. Instead of a collection, `get()` will return an instance of `Illuminate\Contracts\Pagination\LengthAwarePaginator`.

# Macros
This library uses [spatie/macroable](https://github.com/spatie/macroable) to allow you to add your own methods to the library and use them globally with any selector.
```php
DataSelector\Selector::macro('whereActive', function(){
    return $this->where('active', true);
});

DataSelector\Selector::macro('whereName', function($name){
    return $this->where('name', 'LIKE', '%' . $name . '%');
});

$clients = new Clients;
$clients->whereActive()->whereName('John');
```

You can also use `DataSelector\Selector::defineWhere()` to create the same code above:
```php
DataSelector\Selector::defineWhere('active', function(){
    return $this->where('active', true);
});

$clients = new Clients;
$clients->whereActive(); // the "where" is added automatically

$users = new Users;
$users->whereActive();
```

# Eager-loading
One of the most useful features Eloquent offers is eager-loading, I myself use it heavily to decrease the number of queries as much as possible. You can read more about it [here](https://laravel.com/docs/5.5/eloquent-relationships#eager-loading). DataSelector offers a short cut for this feature. Example:
```php
$clients = new Clients;
$clients->eagerLoading()
        ->add('orders')
        ->add('favourites');
```
### `eagerLoading()->add($relation, array $columns = null, $where = null, $withTrashed = false)`
This adds a new eager-loading call after the data is fetched. The `$relation` here represents the name of the relation (method) defined in the model's class. And `$columns` will let you specify what columns to return for this other model. `$where` allows you to add a WHERE statement for the query, and finally `$withTrashed` will allow you to include the trashed (soft-deleted) rows in the final results. You can also use `with(...)` instead of `eagerLoading()->add(...)`.
```php
$clients = new Clients;
$clients->with('orders');
$clients->with('orders', ['id', 'date'], 'MONTH(date) = 6 AND YEAR(date) = 2017');
$clients->with('orders', ['id', 'date'], ['date', '>=', Carbon::yesterday()], true);
```
Of course the value of each relation will be added to the final result under its parent object, in the same way Laravel offers this feature.

# Results formatting
DataSelector will also help you format the final output. You can define a formatter for the Selector or define a global one that you can call with any Selector. Example:
```php
$clients = new Clients;
$clients->formatters()->add('name', function($name){ // Where "name" is the name of the column targeted for formatting
    return 'Mr. ' . $name
});
```
In the example above each result in the final collection will have a `name` that holds the original value, and `name_formatted` with the value of "Mr. [name]". You can use `format()` as a shortcut too.
```php
$clients->format('name', ...)
```

You can also set a global formatter like so:
```php
DataSelector\Formatter::setGlobalFormatter('YMD', function($date){
    return $date->format('Y-m-d');
});

$clients = new Clients;
$clients->format('created_at', 'YMD'); // Will return "created_at_formatted" with the time formatted as Y-m-d
```
Formatting also works for the eager-loaded values, Example:
```php
$clients = new Clients;
$clients->with('orders', ['id', 'date']);
$clients->format('orders.date', 'YMD'); // Where "date" is a property of the eager-loaded values of "orders"
```
