# TwigStan: static code analysis for twig templates
This repo offers an extension to the [Twig templating](https://github.com/twigphp/Twig) engine which adds static code analysis.

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
While implementing the first inspections, the authors ran into some limitations in the Twig extension design.

One seemingly simple is that there's no way to distinguish whether a `macro` argument has an explicit `null` default or
an implicit one. `ExpressionParser->parseArguments()` (line 628) will create identical ASTs for both.

Another is that comments are not added to the AST at all (see `Lexer->lexComment()`), making it impossible to process
type hints in `@var` comments. Support for this has been added in
[a Twig PR](https://github.com/twigphp/Twig/pull/4009). Let's 🤞 this gets merged!

# Inspections
Here's a list of inspections considered relevant.

Those marked with ❌ are (considered) impossible to implement with the current design given the limitations.

Most, if not all, of these inspections depend on the ability to inspect comments, and can't be implemented *yet*.

## Variable types
* ❌ Invalid type (e.g., `{# @var i nit #}`)
* ❌ Invalid object property or method (e.g., `{{ user.nmae }}`)
* ❌ Undocumented context variable (i.e., missing `{# @var foo bool #}`)
* ❌ Use of short-hand form (e.g., `{{ user.admin }}` instead of `isAdmin`) [Notice]

  Rationale: makes it hard to find usage of properties/methods
* Non-stringable variable in string interpolation

## Macros
* ✅ Use of undeclared variables (arguments, `{% set %}`, etc)
* ✅ Calls with *too many* arguments (except is `varargs` is used),
* ❌ Calls with *too few* arguments
* ❌ Type mismatch in macro call
