# vaibhavpandeyvpz/dakiya
Tiny **HTTP** client for exchanging [PSR-7](https://github.com/php-fig/http-message) messages, based on [PSR-18](https://www.php-fig.org/psr/psr-18/).

> Dakiya: `डाकिया` (Postman)

[![Build status][build-status-image]][build-status-url]
[![Code Coverage][code-coverage-image]][code-coverage-url]
[![Latest Version][latest-version-image]][latest-version-url]
[![Downloads][downloads-image]][downloads-url]
[![PHP Version][php-version-image]][php-version-url]
[![License][license-image]][license-url]

[![SensioLabsInsight][insights-image]][insights-url]

Usage
-----
```php
<?php

$client = new Dakiya\Client(new Sandesh\ResponseFactory());

$request = (new Sandesh\RequestFactory())->createRequest('GET', 'https://example.com/');
$response = $client->sendRequest($request);
if ($response->getStatusCode() === 200) {
    // ...do as required
}
```

Documentation
-------------
To view installation and usage instructions, visit this [Wiki](https://github.com/vaibhavpandeyvpz/dakiya/wiki).

License
-------
See [LICENSE.md][license-url] file.

[build-status-image]: https://img.shields.io/travis/vaibhavpandeyvpz/dakiya.svg?style=flat-square
[build-status-url]: https://travis-ci.org/vaibhavpandeyvpz/dakiya
[code-coverage-image]: https://img.shields.io/codecov/c/github/vaibhavpandeyvpz/dakiya.svg?style=flat-square
[code-coverage-url]: https://codecov.io/gh/vaibhavpandeyvpz/dakiya
[latest-version-image]: https://img.shields.io/github/release/vaibhavpandeyvpz/dakiya.svg?style=flat-square
[latest-version-url]: https://github.com/vaibhavpandeyvpz/dakiya/releases
[downloads-image]: https://img.shields.io/packagist/dt/vaibhavpandeyvpz/dakiya.svg?style=flat-square
[downloads-url]: https://packagist.org/packages/vaibhavpandeyvpz/dakiya
[php-version-image]: http://img.shields.io/badge/php-7.0+-8892be.svg?style=flat-square
[php-version-url]: https://packagist.org/packages/vaibhavpandeyvpz/dakiya
[license-image]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[license-url]: LICENSE.md
[insights-image]: https://insight.sensiolabs.com/projects/7b4447e4-d17a-4699-a0e6-ac53a202995c/small.png
[insights-url]: https://insight.sensiolabs.com/projects/7b4447e4-d17a-4699-a0e6-ac53a202995c
