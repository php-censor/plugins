PHP Censor Plugins
==================

[WIP] PHP Censor common plugins (for PHP Censor v3.0+).

Code style
----------

```bash
vendor/bin/php-cs-fixer --diff fix

vendor/bin/psalm --config=psalm.xml.dist --threads=4 --show-snippet=true --show-info=true
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

