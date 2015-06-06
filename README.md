Jin - Jsonified Ini Notion
=====

Jin is a simple configuration language which can be used for all sorts of purposes.  It's similar
to TOML [https://github.com/toml-lang/toml], but a lot simpler and less formal.  It's basically
INI files which contain JSON values.

I wrote this primarily as a way to configure the new ORM I'm working on, so it's application and
usefulness may vary.

Here are the rules:

- Category identifiers can only contain `a-z`, `A-Z`, `.`, and `_`
- Field identifiers can only contain `a-z`, `A-Z`, and `_`
- JSON can span multiple lines, line breaks in content or comments are a no-No!

## Input Example

In the example below we define a little bit of information about me.  Note... things that look
like integers will become integers.  Things that look like floats will become floats.  Things that
look like objects will become objects.  Things that... you get the idea.

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

## Output Example

Below is the `print_r` output from the above configuration.

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

## Usage

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

## Editor Support

I've noticed that a lot of existing syntax highlighting can make Jin look good, but my favorite,
thus far, is `Javascript (Rails)` in atom.  I may try to replicate that highlighting and release
a package.  I welcome any other suggestions people have for languages that match similarly and
provide nice highlighting.
