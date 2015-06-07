Jin - Jsonified Ini Notation
=====

Jin is INI files which supports JSON values.

It is a simple and informal configuration language which can be used for all sorts of purposes.
It works best as a metaconfiguration or schema configuration as it is extremely good for repeated
blocks of data that need to be keyed differently.

In some ways it is similar to TOML (https://github.com/toml-lang/toml), but does not have a formal
specification.

## Basic Usage

```php
$collection  = new Dotink\Flourish\Collection();
$jin_parser  = new Dotink\Jin\Parser($collection);

$config_data = $jin_parser->parse($jin_string)->get();
```

You can make turn `stdClass` objects in parsed JSON into additional associative arrays by
passing `TRUE` as the second parameter:

```php
$config_data = $jin_parser->parse($jin_string, TRUE)->get();
```

If you'd rather work directly with the collection you can leave off the `get()`.  You can see
more documentation about the collection at [https://github.com/dotink/flourish-collection]:

```php
$config = $jin_parser->parse($jin_string, TRUE);
```

## The Language

### A Simple Field

```yaml
field = value
```

Strings don't have to be quoted, but can be:

```yaml
field = "value"
```

Integers are converted to the proper type automatically:

```yaml
integerValue = 1
```

Floats too...

```yaml
floatValue = 1.03
```

Booleans and NULL values are case insensitive:

```yaml
boolValue = false
```

```yaml
boolValue = TRUE
```

```yaml
nullValue = NULL
```

### Multi-Line Text

```yaml
multi = Jin supports multi lines as well so long as they do not look like an
INI field.  It should be noted however that multiple lines will not retain their
line breaks.  New lines will, instead be separated by a space.
```

### JSON Structures

Arrays are defined literally:

```yaml
favoriteFoods = ["Tacos", "Sushi", "Curry"]
```

Objects are also defined literally and can span multiple lines:

```yaml
favorites = {
	"food":  "Indian",
	"music": "Classic Rock"
}
```

### Categories

Categories are an alternative to object structures.  While objects (by default) in JSON notation
will result in `stdClass` objects, categories will be associative arrays.  It is recommended that
categories be used when the fields represent a well defined schema, and JSON objects be used for
what amounts to user supplied data.

```yaml
[category]
fieldOne   = valueOne
fieldTwo   = valueTwo
fieldThree = 1
fieldFour  = [0, 7, 9, 13]
fieldFive  = {
	"foo": "bar"
}
```

### Sub Categories

You can add subcategories by separating the previous category name by a dot.  This is extremely
useful for keyed configuration values with repeating data, for example, imagine a database config
with multiple aliases connections:

```yaml
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

**Note: The leading whitespace does not matter and can be tabs or spaces**

## Addendum

Jin was written primarily as a way to configure a data mapper ORM.  While it should serve a lot
of configuration needs well, since there is no formalized spec, strange edge cases may be
possible.

Keep in Mind:

- Category identifiers SHOULD only contain `a-z`, `A-Z`, `.`, `\`, `-`, and `_`
- Field identifiers SHOULD only contain `a-z`, `A-Z`, `-` and `_`
- JSON can span multiple lines, line breaks in content or comments are a no-No!

The basic transformation algorithm is rather simple, so feel free to look at the source if you're
running into a particular bug.  Although we will entertain new features or additional
formalization, please keep in mind that this is designed with informality in mind.

### Kitchen Sink Example

```clojure
[person]
firstName = "Matthew"
lastName  = "Sahagian"
location  = "Silicon Valley, CA"
email     = "msahagian@dotink.org"
age       = 31
pi        = 3.14159
single    = FALSE
employed  = TRUE
vehicles  = ["2005 Mazda 6"]
favorites = {
	"food":  "Indian",
	"hobby": "Homebrew",
	"music": "Classic Rock"
}

	[person.education]
	level = "Associates"

		[person.education.highschool]
		name     = "Bellingham Memorial High School"
		location = "Bellingham, MA"
		gradYear = 2002

		[person.education.college]
		name     = "New England Institute of Technology"
		location = "Warwick, RI"
		gradYear = 2004
		degree   = "Associates"
```

#### Outputted Array

```
Array
(
	[person] => Array
		(
			[firstName] => Matthew
			[lastName] => Sahagian
			[location] => Silicon Valley, CA
			[email] => msahagian@dotink.org
			[age] => 31
			[pi] => 3.14159
			[single] =>
			[employed] => 1
			[vehicles] => Array
				(
					[0] => 2005 Mazda 6
				)

			[favorites] => stdClass Object
				(
					[food] => Indian
					[hobby] => Homebrew
					[music] => Classic Rock
				)

			[education] => Array
				(
					[level] => Associates
					[highschool] => Array
						(
							[name] => Bellingham Memorial High School
							[location] => Bellingham, MA
							[gradYear] => 2002
						)

					[college] => Array
						(
							[name] => New England Institute of Technology
							[location] => Warwick, RI
							[gradYear] => 2004
							[degree] => Associates
						)
				)
		)
)
```


## Editor Support

I've noticed that a lot of existing syntax highlighting can make Jin look good, but my favorite,
thus far, is `Javascript (Rails)` in atom.  I may try to replicate that highlighting and release
a package.  I welcome any other suggestions people have for languages that match similarly and
provide nice highlighting.
