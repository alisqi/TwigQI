# TwigQI: Static code analysis for Twig templates

[![License](https://img.shields.io/github/license/alisqi/TwigQI.svg)](https://github.com/alisqi/TwigQI/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.2-8892BF.svg)](https://php.net)
![Stability](https://img.shields.io/badge/stability-stable-brightgreen)
[![Build Status](https://github.com/alisqi/TwigQI/actions/workflows/test.yml/badge.svg)](https://github.com/alisqi/TwigQI/actions)
[![codecov](https://codecov.io/gh/alisqi/TwigQI/graph/badge.svg?token=G3AE3RE4K0)](https://codecov.io/gh/alisqi/TwigQI)

Twig Quality Inspections is an extension to the [Twig templating](https://github.com/twigphp/Twig) engine
which adds static analysis (i.e., compile-time) inspections and runtime assertions to increase templates' quality.
See the [inspections section](#Inspections) below for details.

Unlike other projects like [curlylint](https://www.curlylint.org/) and [djLint](https://www.djlint.com/docs/linter/),
which focus on HTML, this tool exclusively analyzes the Twig code.

The two intended use cases are:
* Add the extension to the `Twig\Environment` during development
* Invoke a CLI command in CI and/or pre-commit hook which compiles all templates with the extension enabled.

> [!NOTE]
> TwigQI is stable and should work in most codebases due to its simplicity. I would love to hear about your experience
> using it. Please create an issue or a pull request if you've found an issue. 🙏

Note that TwigQI doesn't support every single edge case, plus it is a little opinionated. You've been warned! 😉
The good news is that you can easily create a bespoke suite by cherry-picking the inspections.

# Justification
Just in case you need convincing, please consider the following example:

```twig
{% macro userCard(user, showBadge = false) %}
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
First, install using
```bash
composer require --dev alisqi/twigqi
```

Next, add the extension to your `Twig\Environment`:
```php
$logger = new AlisQI\TwigQI\Logger\TriggerErrorLogger();
$twig->addExtension(new AlisQI\TwigQI\Extension($logger));
```

The `TriggerErrorLogger` will report issues using PHP's `trigger_error` with `E_USER_*` levels.
Alternatively, you can use any other logger `\Psr\Log\LoggerInterface` implementation.

Set up your app and/or CI build to report these as you see fit.

And that's it! 😎

## Configuration
You can cherry-pick your inspections (see below):
```php
use AlisQI\TwigQI\Extension;
use \AlisQI\TwigQI\Inspection\InvalidConstant;
use \AlisQI\TwigQI\Inspection\InvalidEnumCase;

new Extension($logger, [
    InvalidConstant::class,
    InvalidEnumCase::class,
]);
```

# Design
The current design uses `NodeVisitor` classes for every inspection. That allows for easy testing and configurability.

The level of error (error, warning, notice) depends entirely on the authors' opinions on code quality. `LogLevel::ERROR`
is used for, well, errors, that the author(s) deem actual errors in code. `LogLevel::WARNING` is used for more
opinionated issues, such as relying on macro arguments always being optional.

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
* Classes, interfaces and traits

  Use FQNs with a starting backslash. Note that backslashes must be escaped in Twig strings [until v4](https://github.com/twigphp/Twig/pull/4199).
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

## Typed variables
* ✅ Declared types is invalid (e.g., `{% types {i: 'nit'} %}`)
* ✅ Runtime: non-optional variable is not defined
* ✅ Runtime: non-nullable variable is null
* ✅ Runtime: variable does not match type
* ✅ Invalid object property or method (e.g., `{{ user.nmae }}`)
  
  Types for keys and values in `for` loops are automatically derived from iterable types.

  ⚠️ This inspection _can_ trigger false positives, depending on your template logic.
* ⌛ Undeclared variable (i.e., missing in `types`, `set`, etc)

## Constants and enum cases
* ✅ Invalid constant (e.g., `constant('BAD')`)
* ✅ Expressions as first argument (e.g., `constant('UH' ~ 'OH')`)
 
  This is opinionated, as it can work perfectly fine
* ✅ Second argument (object) is not a name (e.g., `constant('CONST', {})`)
  
  This is opinionated, too: `constant('CONST', foo ?: bar)` can work fine

* ✅ Invalid enum case (e.g., `enum('\\Some\\Enum').InvalidCase`)

## Macros
While Twig considers all macro arguments optional (and provides `null` as a default), TwigQI considers arguments with
no explicit default value as required.

* ⌛ Arguments not declared using `types`
* ✅ Undefined variable used (arguments, `{% set %}`, etc)
* ✅ Call with *too many* arguments (except if `varargs` is used)
* ✅ Call with *too few* arguments
* ✅ Required argument declared after optional
* ✅ Positional argument after named in call expression
* ⌛ Type mismatch in macro call

# Acknowledgments
Big thanks to [Ruud Kamphuis](https://github.com/ruudk) for [TwigStan](https://github.com/twigstan/twigstan),
and for helping on this very project.
