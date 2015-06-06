# Parser



#### Namespace

`Dotink\Jin`

#### Imports

<table>

	<tr>
		<th>Alias</th>
		<th>Namespace / Target</th>
	</tr>
	
	<tr>
		<td>Flourish</td>
		<td>Dotink\Flourish</td>
	</tr>
	
</table>


## Methods

### Instance Methods
<hr />

#### <span style="color:#3e6a6e;">__construct()</span>

Create a new Jin

###### Returns

<dl>
	
		<dt>
			void
		</dt>
		<dd>
			Provides no return value.
		</dd>
	
</dl>


<hr />

#### <span style="color:#3e6a6e;">parse()</span>

Parse a Jin string

###### Parameters

<table>
	<thead>
		<th>Name</th>
		<th>Type(s)</th>
		<th>Description</th>
	</thead>
	<tbody>
			
		<tr>
			<td>
				$jin_string
			</td>
			<td>
									<a href="http://php.net/language.types.string">string</a>
				
			</td>
			<td>
				The Jin string to parse
			</td>
		</tr>
					
		<tr>
			<td>
				$assoc
			</td>
			<td>
									<a href="http://php.net/language.types.boolean">boolean</a>
				
			</td>
			<td>
				Whether JSON objects should be associative arrays
			</td>
		</tr>
			
	</tbody>
</table>

###### Returns

<dl>
	
		<dt>
			array
		</dt>
		<dd>
			The parsed Jin string as an associative array
		</dd>
	
</dl>


<hr />

#### <span style="color:#3e6a6e;">removeNewLines()</span>

Removes newlines in the proper places

###### Parameters

<table>
	<thead>
		<th>Name</th>
		<th>Type(s)</th>
		<th>Description</th>
	</thead>
	<tbody>
			
		<tr>
			<td>
				$string
			</td>
			<td>
									<a href="http://php.net/language.types.string">string</a>
				
			</td>
			<td>
				The string from which to remove new lines
			</td>
		</tr>
			
	</tbody>
</table>

###### Returns

<dl>
	
		<dt>
			string
		</dt>
		<dd>
			The string, stripped of new lines
		</dd>
	
</dl>


<hr />

#### <span style="color:#3e6a6e;">removeWhitespace()</span>

Removes leading whitespace from the proper places

###### Parameters

<table>
	<thead>
		<th>Name</th>
		<th>Type(s)</th>
		<th>Description</th>
	</thead>
	<tbody>
			
		<tr>
			<td>
				$string
			</td>
			<td>
									<a href="http://php.net/language.types.string">string</a>
				
			</td>
			<td>
				The string from which to remove leading whitespace
			</td>
		</tr>
			
	</tbody>
</table>

###### Returns

<dl>
	
		<dt>
			string
		</dt>
		<dd>
			The string, stripped of leading whitespace
		</dd>
	
</dl>




