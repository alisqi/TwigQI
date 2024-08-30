# TwigQI: The Twig Quality Inspector extension
This repo offers an extension to the [Twig templating](https://github.com/twigphp/Twig) engine
which adds compile-time and runtime code quality inspections for templates.

Unlike other projects like [curlylint](https://www.curlylint.org/) and [djLint](https://www.djlint.com/docs/linter/),
which focus on HTML, this tool exclusively analyzes the Twig code.

While the Twig compiler will throw `SyntaxError`s, there are few runtime checks.

For example, a `RuntimeError` is thrown when trying to use an undeclared variables (if `strict_variables` is enabled).
However, this only happens if the code is actually executed. In the logic is non-trivial, neither developer nor tests
(if any even exist) might detect obvious mistakes such as a misspelled variable name.

This project provides ways to increase code quality and prevent defects.

The two intended use cases are:
* Add the extension to the `Twig\Environment` during development
* Invoke a CLI command in CI or even pre-commit hook

# Justification
Just in case you need convincing, please consider the following example:

```twig
{% macro userCard(user, showBadge = false) %}
  {# @var user \User #}
  {# @var showBadge bool #}
  
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

Any issues will be reported using PHP's `trigger_error`. Set up your app and/or CI build to report these as you see fit.

# Design
The current design requires separate `NodeVisitor` classes for every inspection. That allows for easy testing and
configurability later.

The reason the inspections use `trigger_error` instead of `Exception`s is that the latter would halt compilation,
preventing the extension from reporting multiple issues in one go.

The level of error (error, warning, notice) depends entirely on the authors' opinions on code quality. `E_USER_ERROR` is
used for, well, errors, that the author(s) deem actual errors in code. For more opinionated issues (e.g., relying on
macro arguments always being optional), `E_USER_WARNING` is used.

## Challenges and limitations
While implementing the first inspections, I ran into some limitations in the Twig extension design.

One seemingly simple is that there was no way to distinguish whether a `macro` argument has an explicit `null`
default or an implicit one. `ExpressionParser->parseArguments()` (line 628) created identical ASTs for both.
[I've created a PR](https://github.com/twigphp/Twig/pull/4010) to add a distinction in the AST, which has been merged.

Another is that there is no way to specify types. While the `@var` comments in the example are supported by the
[Symfony Support plugin](https://plugins.jetbrains.com/plugin/7219-symfony-support) for PHPStorm, Twig's parser does not
add comments to the AST, meaning there's no way for extensions to process them.
(A [PR to add support ](https://github.com/twigphp/Twig/pull/4009) was closed.)

The good news is that Twig recently added the [`types` tag](https://github.com/twigphp/Twig/issues/4165).

# Inspections
Here's a list of inspections considered relevant.

Those marked with ⌛ are (considered) possible to implement once the PRs mentioned above have been merged.

Note that most of these could also be analyzed by PHPStan if it could properly understand (compiled) templates and how
they are rendered. This is the aim of [Ruud Kamphuis](https://github.com/ruudk)'s similar project,
[TwigQI](https://github.com/twigphp/Twig/discussions/4233).

## Variable types
* ⌛ Invalid type declared (e.g., `{# @var i nit #}`)
* ⌛ Runtime type doesn't match declaration
* ⌛ Invalid object property or method (e.g., `{{ user.nmae }}`)
* ⌛ Undocumented context variable (i.e., missing `{# @var foo bool #}`)
* ⌛ Use of short-hand form (e.g., `{{ user.admin }}` instead of `isAdmin`) [Notice]

  Rationale: makes it hard to find usage of properties/methods
* Non-stringable variable in string interpolation

## Macros
* ⌛ Arguments not declared using `types`
* ✅ Use of undefined variables (arguments, `{% set %}`, etc)
* ✅ Calls with *too many* arguments (except if `varargs` is used),
* ✅ Calls with *too few* arguments (arguments with no default are considered required)
* ✅ Required argument (no explicit default) declared after optional
* ⌛ Type mismatch in macro call
