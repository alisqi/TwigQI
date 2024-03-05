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

The level of error (error, warning, notice) depends entirely on the authors' opinions on code quality. `E_USER_ERROR` is used for, well, errors, that the author(s) deem actual errors in code. For more opinionated issues
(e.g., relying on macro arguments always being optional), `E_USER_WARNING` is used.
