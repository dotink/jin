JIN - Jsonified Ini Notation
=====

JIN is data format which uses the structure of an INI file with JSON-like values.

It is a simple and informal language which is most suitable for confgiruation data and settings.  It works best as a metaconfiguration or schema configuration of sorts, but can also just give rich type and object support to something as simple as a `.env` file.

In some ways it is similar to TOML (https://github.com/toml-lang/toml), but does not have a formal specification.  Parsing rules can best be described as follows:

- File structure is that of an INI file
	- key = value
	- [section]
	- ; comment
- Values are JSON-like with the following differences
	- Escaped character, e.g. \n, \b, \t are not supported
	- A single \ does not need to be escaped

## Basic Usage

```php
$jin_parser  = new Dotink\Jin\Parser()
$jin_string  = file_get_contents('config.jin');
$config_data = $jin_parser->parse($jin_string)->get();
```

Calling `get()` on the collection will return the full parsed data as an associative array.

If you'd rather work directly with the collection you can leave off the `get()`. You can see more documentation about the collection at (https://github.com/adbario/php-dot-notation):

```php
$config = $jin_parser->parse($jin_string);
```

Using the collection directly will allow you to use the "dot notation" to reference and retrieve specific values, as well as to supply defaults if they don't exist:

```php
$config->get('database.connections.default', [
	'name' => 'website',
	'host' => 'localhost',
	'user' => 'web',
	'pass' => '3ch0th3w4lRUS'
]);
```

You can, additionally, preserve `stdClass` objects in parsed JSON by passing FALSE to the second parameter:

```php
$config_data = $jin_parser->parse($jin_string, FALSE)->get();
```

## The Language

### A Simple Field

```js
field = value ; INI style string
```

Strings don't have to be quoted, but can be:

```js
field = "value" ; JSON style string
```

Integers are converted to the proper type automatically:

```js
integerValue = 1
```

Floats too...

```js
floatValue = 1.03
```

Booleans and NULL values are case insensitive:

```js
boolValue = false
```

```js
boolValue = TRUE
```

```js
nullValue = NULL
```

### Multi-Line Text

```js
multi = JIN supports multi-line values until the new line resembles
an INI database structure.  So, for example, this line would be parsed
with new lines preserved until `foo=bar` or `[section]` or `\n\n`.
```

### Comments

Comments are allowed anywhere in a value, so it is important to keep in mind that anything after an `;` character is going to be cut off.

```js
field = "This probably does not do what you expect; this is stripped"
```

### JSON-like Values

Arrays are defined literally:

```js
favoriteFoods = ["Tacos", "Sushi", "Curry"]
```

Objects are also defined literally too:

```js
favorites = {"food":  "Indian", "music": "Classic Rock"}
```

Both can span multiple lines and contain comments:

```js
multiFoods = [
	"Tacos",
	"Sushi", ; Most keto friendly
	"Curry"
]

multiFavorites = {
	;
	; The basics
	;

	"food": "Tacos",
	"music": "Classic Rock" ; Not actually my favorite
}
```

#### Differences

Although values can be JSON-like, they are not, strictly speaking, JSON.  The major difference is that they do not support JSON's built in escaped characters, so you cannot use `\n` or `\t`.  On the bright side, you do not need to escape a backslash:

```js
middlewares = [
	"App\Middleware\ResponseHandler"
]
```

### Sections

Sections provide an alternative to JSON object structures.  Note, sections are never parsed as `stdClass` objects, but will always return associative arrays.

```js
[category]

	fieldOne   = valueOne
	fieldTwo   = valueTwo
	fieldThree = 1
	fieldFour  = [0, 7, 9, 13]
	fieldFive  = {
		"foo": "bar"
	}
```

### Sub-Sections

You can add sub-sections by separating the previous category name by a dot.  This is extremely
useful for keyed configuration values with repeating data, for example, imagine a database config
with multiple aliases connections:

```js
[database]

	[database.connections.default]
		driver = pgsql
		dbname = website
		host   = localhost

	[database.connections.forums]
		driver = mysql
		dbname = forums
		host   = localhost
		user   = web
		pass   = 3ch0th3w4lRUS
```

### Section References

You can reference a parent section for shorter section names.

```js
[database]

	[&.connections.default]
		driver = pgsql
		dbname = website
		host   = localhost
```

References can be stacked to refer to sub-sub-sections.  Reference stacking always begins from the last section defined without a reference:

```js
[database]

	[&.connections]

		;
		; This section contains all of our database connections
		;

		[&&.default]
			driver = pgsql
			dbname = website
			host   = localhost
```

## Environment Variables

You can get values from the environment.

```js
envField = env(DEBUGGING)
```

And provide defaults when they are not set:

```js
envField = env(DEBUGGING, TRUE)
```

## Native Language Values

You can use native language functions (in this implementation, PHP):

```js
runField = run(md5('hash this thing'))
```

You can add context to the parser for access to variables as well:

```php
$jin_parser  = new Dotink\Jin\Parser([
	'app' => $app
]);
```

Then access/use them as you'd expect:

```js
cacheDirectory = run($app->getDirectory('storage/cache', TRUE))
```

## Custom Functions

You can add custom functions by passing an array of callables keyed by the function name as the second parameter to the parser:

```php
$jin_parser  = new Dotink\Jin\Parser([], [
	'hello' => function($name) {
		return 'Hello ' . $name;
	}
]);
```

Then use them as you'd expect.

```js
hello = hello(Matt)
```

Note, you can overload the `env()` and even the `run()` function, however, overloading structures such as `map()`, `def()`, `inc()` will not work.

### Templates

Templates provide a powerful way to duplciate complex data structures with different values:

```js
[database]

	settings = def(type, name, host, user, pass) {
		{
			"type": $type,
			"name": $name,
			"host": $host,
			"auth": {
				"user": $user,
				"pass": $pass
			}
		}
	}

	[&.connections]

		default = inc(database.settings) {
			pgsql
			my_database
			localhost

			;
			; Do not be afraid to use any valid value where values are
			; specified
			;

			env(DB_USER, web)
			env(DB_PASS, NULL)
		}
```

Templates can also be used to create arrays of non-keyed objects:

```js
[routing]

	route = def(methods, pattern, target) {
		{
			"methods": $methods,
			"pattern": $pattern,
			"target": $target
		}
	}

	;
	; The map function takes a tab separated list of values.  Multiple tabs
	; are reduced to one before parsing.
	;

	routes = map(routing.route) {
		["GET"]		/		ViewHome
		["GET"]		/articles	ListArticles
		["POST"]	/articles	CreateArticle
		["GET"]		/articles/{id}	ViewArticle
		["POST"]	/articles/{id}	EditArticle
	}

```

## Testing

```
php vendor/bin/phpunit --bootstrap vendor/autoload.php test/routines/
```

## Addendum

JIN was originally written as a way to configure a data mapper ORM.  It is a very flexible and intuitive language, but it may not make sense in all cases.  It is strongly recommended that if you are using it for frequently accessed configurations (like during runtime) that you serialize and cache the resulting collection rather than parsing it on every load.

### Editor Support

There is a hobbled together grammar file for Atom which can be found here:

https://github.com/dotink/atom-language-jin

Because of its similarity to TOML, TOML syntax highlighting also tends to look well.  You can alternatively try JS/JSON syntax highlighting, but your mileage may vary depending on syntax highlighting implementations.
