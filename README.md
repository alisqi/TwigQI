# TwigQI: Static code analysis for Twig templates
Twig Quality Inspections is an extension to the [Twig templating](https://github.com/twigphp/Twig) engine
which adds static analysis (i.e., compile-time) and runtime assertions to increase templates' quality.

Unlike other projects like [curlylint](https://www.curlylint.org/) and [djLint](https://www.djlint.com/docs/linter/),
which focus on HTML, this tool exclusively analyzes the Twig code.

While the Twig compiler will throw `SyntaxError`s, there are few other compile-time or runtime checks. For example,
a `RuntimeError` is thrown when trying to use an undeclared variables (if `strict_variables` is enabled).
However, this only happens if the code is actually executed. In the logic is non-trivial, neither developer nor tests
(if any even exist) might detect obvious mistakes such as a misspelled variable name.

The two intended use cases are:
* Add the extension to the `Twig\Environment` during development
* Invoke a CLI command in CI and/or pre-commit hook which compiles all templates with the extension enabled.

This won't solve every single edge case or possibility, plus it's opinionated. You've been warned! ;p

# Justification
Just in case you need convincing, please consider the following example:

```twig
{% macro userCard(user, showBadge = false) %}
  {% types {
    user: '\\User',
    showBadge: 'boolean',
  } %}
  
  {{ user.name }}
  {% if showBadge %}
    {% if usr.admin %} {# Oops #}
      (admin)
    {% else if user.role %}
      ({{ user.getRoleLabel(usr.role) }}) {# Uh oh! #}
    {% endif %}
  {% endif %}
{% endmacro %}
```

Here, `usr.admin` is obviously a typo. Fortunately, this bug is easily detected with `strict_types` enabled,
but only if the macro is called with `showBadge=true`, which might be uncommon enough to go unnoticed during
development. In this example, the `(admin)` badge will simply never be printed in production, where `strict_types`
is likely disabled. A bug for sure, but perhaps not a critical one.

However, `user.getRoleLabel(usr.role)` will cause an uncaught `TypeError` if that method's parameter is not nullable,
since Twig will call that method with `null`. Instead of just having a buggy badge, *the whole page breaks*.

# Usage
Add the extension to your `Twig\Environment`.

Any issues will be reported using PHP's `trigger_error` with `E_USER_*` levels.
Set up your app and/or CI build to report these as you see fit.

# Design
The current design uses `NodeVisitor` classes for every inspection. That allows for easy testing and configurability.

The reason the inspections use `trigger_error` instead of `Exception`s is that the latter would halt compilation,
preventing the extension from reporting multiple issues in one go.

The level of error (error, warning, notice) depends entirely on the authors' opinions on code quality. `E_USER_ERROR` is
used for, well, errors, that the author(s) deem actual errors in code. For more opinionated issues (e.g., relying on
macro arguments always being optional), `E_USER_WARNING` is used.

## Typing system and syntax
Many inspections rely on proper typing. However, the [documentation for the `types` tag](https://twig.symfony.com/doc/3.x/tags/types.html)
explicitly avoids specifying the syntax or contents of types.

So how should developers declare types? While PHP developers are often familiar with PHPStan, Twig template designers
may instead be used to TypeScript.

The [Twig documentation](https://twig.symfony.com/doc/3.x/templates.html#variables) sums up its stance succinctly:

> Twig tries to abstract PHP types as much as possible and works with a few basic types[.]

Therefore, TwigQI uses the basic types described by Twig, while defining syntax for iterables. The goal is to have a
*simple* type system that's easy to learn and use, and which should cover the vast majority of use cases.

Your preferences and/or requirements may very well differ.

Here's the list of types supported by TwigQI:
* Scalar: `string`, `number`, `boolean`, `null`, `object` (although a class is preferred)
* Class names (FQN, with a starting backslash. Note that backslashes must be escaped in Twig strings!)
* Three types of iterables, with increasing specificity
  * `iterable` declares nothing more or less than that the variable is iterable
  * `iterable<ValueType>` declares the values' type
  * `iterable<number, ValueType>` and `iterable<string, ValueType>` does the same for keys
  
  You can create recursive types: `iterable<string, iterable<number, iterable<string>>>`
* Lastly, `mixed` allows you to declare that a variable is defined without specifying a concrete type.

Any type can be prefixed with `?` to make it nullable.

Note that there's no dedicated syntax for iterables with particular, known keys. Nor can you declare that values have
different types. You could use one of the `iterable` variants (e.g., `iterable<string, mixed>`), but I would humbly
recommend using a `readonly class` to act as a view model.

# Inspections
Here's the list of inspections already considered relevant and feasible.

Those marked with ⌛ are planned / considered, while ✅ means the inspection is implemented.

Note that most of these could also be analyzed by PHPStan if it could properly understand (compiled) templates and how
they are rendered. This is the aim of a similar project: [TwigStan](https://github.com/twigstan/twigstan).

## Variable types
* ✅ Invalid type declared (e.g., `{% types {i: 'nit'} %}`)
* ⌛ Runtime type doesn't match declaration
* ⌛ Invalid object property or method (e.g., `{{ user.nmae }}`)
* ⌛ Undocumented context variable (i.e., missing in `{% types %}`)
* ⌛ Use of short-hand form (e.g., `{{ user.admin }}` instead of `isAdmin`) [Notice]

  Rationale: makes it hard to find usage of properties/methods
* Non-stringable variable in string interpolation

## Constants
* ✅ Invalid constant (e.g., `constant('BAD')`)
* ✅ Expressions as first argument (e.g., `constant('UH' ~ 'OH')`)
 
  This is opinionated, as it can work perfectly fine
* ✅ Second argument (object) is not a name (e.g., `constant('CONST', {})`)
  
  This is opinionated, too: `constant('CONST', foo ?: bar)` can work fine

## Macros
While Twig considers all macro arguments optional (and provides `null` as a default), TwigQI considers arguments with
no explicit default value as required.

* ⌛ Arguments not declared using `types`
* ✅ Use of undefined variables (arguments, `{% set %}`, etc)
* ✅ Calls with *too many* arguments (except if `varargs` is used)
* ✅ Calls with *too few* arguments
* ✅ Required argument declared after optional
* ⌛ Type mismatch in macro call

# Acknowledgments
Big thanks to [Ruud Kamphuis](https://github.com/ruudk) for [TwigStan](https://github.com/twigstan/twigstan),
and for helping on this very project.
