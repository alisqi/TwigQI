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

# Example
Consider the following example:

```twig
{% macro hello(user) %}
  {# @var user \User #}

  Hello {{ user.isLoggedIn() ? (', ' ~ usr.name) : '' }}!
{% endmacro %}
```

Here, `usr` is obviously a typo. Fortunately, this bug is easily detected when enabling `strict_types` during development or testing.

# Usage
