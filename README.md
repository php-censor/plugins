PHP Censor Plugins
==================

[WIP] PHP Censor commom plugins (for PHP Censor v2.0+).

Code style
----------

```bash
vendor/bin/php-cs-fixer fix
```

Unit tests
----------

Phpunit tests:

```bash
vendor/bin/phpunit --configuration=phpunit.xml.dist --coverage-text --coverage-html=tests/var/coverage
```

Infection mutation tests:

```bash
vendor/bin/infection --threads=4 --show-mutations -vvv
```
