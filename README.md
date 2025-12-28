# Dakiya

[![Latest Version](https://img.shields.io/packagist/v/vaibhavpandeyvpz/dakiya.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/dakiya)
[![Downloads](https://img.shields.io/packagist/dt/vaibhavpandeyvpz/dakiya.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/dakiya)
[![PHP Version](https://img.shields.io/packagist/php-v/vaibhavpandeyvpz/dakiya.svg?style=flat-square)](https://packagist.org/packages/vaibhavpandeyvpz/dakiya)
[![License](https://img.shields.io/packagist/l/vaibhavpandeyvpz/dakiya.svg?style=flat-square)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/vaibhavpandeyvpz/dakiya/tests.yml?branch=master&style=flat-square)](https://github.com/vaibhavpandeyvpz/dakiya/actions)

> **Dakiya** (`डाकिया`) means "Postman" in Hindi

A lightweight, PSR-18 compliant HTTP client for PHP 8.2+ that uses cURL as its transport mechanism. Dakiya provides a simple and efficient way to send HTTP requests using PSR-7 message interfaces.

## Features

- ✅ **PSR-18 Compliant** - Implements the HTTP Client Interface standard
- ✅ **PSR-7 Compatible** - Works with any PSR-7 message implementation
- ✅ **Modern PHP 8.2+** - Leverages latest PHP features and type safety
- ✅ **Lightweight** - Minimal dependencies, only requires cURL extension
- ✅ **Streaming Support** - Automatic streaming for large request bodies (>1MB)
- ✅ **HTTP/2 Ready** - Supports HTTP/1.0, HTTP/1.1, and HTTP/2.0
- ✅ **All HTTP Methods** - GET, POST, PUT, PATCH, DELETE, HEAD
- ✅ **Authentication** - Built-in support for URI-based authentication
- ✅ **Customizable** - Configurable via cURL options

## Requirements

- PHP 8.2 or higher
- cURL extension
- A PSR-17 HTTP Factory implementation (for creating requests/responses)
- A PSR-7 HTTP Message implementation

## Installation

Install via Composer:

```bash
composer require vaibhavpandeyvpz/dakiya
```

## Quick Start

```php
<?php

use Dakiya\Client;
use Sandesh\RequestFactory;
use Sandesh\ResponseFactory;

// Create the client with a response factory
$client = new Client(new ResponseFactory());

// Create a request
$requestFactory = new RequestFactory();
$request = $requestFactory->createRequest('GET', 'https://api.example.com/users');

// Send the request
$response = $client->sendRequest($request);

// Check the response
if ($response->getStatusCode() === 200) {
    $body = (string) $response->getBody();
    $data = json_decode($body, true);
    // Process the data...
}
```

## Usage Examples

### Basic GET Request

```php
use Dakiya\Client;
use Sandesh\RequestFactory;
use Sandesh\ResponseFactory;

$client = new Client(new ResponseFactory());
$requestFactory = new RequestFactory();

$request = $requestFactory->createRequest('GET', 'https://api.example.com/data');
$response = $client->sendRequest($request);

echo $response->getStatusCode(); // 200
echo (string) $response->getBody();
```

### POST Request with JSON Body

```php
use Dakiya\Client;
use Sandesh\RequestFactory;
use Sandesh\ResponseFactory;
use Sandesh\StreamFactory;

$client = new Client(new ResponseFactory());
$requestFactory = new RequestFactory();
$streamFactory = new StreamFactory();

$body = json_encode(['name' => 'John Doe', 'email' => 'john@example.com']);
$request = $requestFactory->createRequest('POST', 'https://api.example.com/users')
    ->withBody($streamFactory->createStream($body))
    ->withHeader('Content-Type', 'application/json');

$response = $client->sendRequest($request);
```

### POST Request with Form Data

```php
$data = http_build_query(['username' => 'johndoe', 'password' => 'secret']);
$request = $requestFactory->createRequest('POST', 'https://api.example.com/login')
    ->withBody($streamFactory->createStream($data))
    ->withHeader('Content-Type', 'application/x-www-form-urlencoded');

$response = $client->sendRequest($request);
```

### Request with Authentication

```php
$uri = $requestFactory->createUri('https://api.example.com/protected')
    ->withUserInfo('username', 'password');

$request = $requestFactory->createRequest('GET', $uri);
$response = $client->sendRequest($request);
```

### Custom cURL Options

```php
$client = new Client(new ResponseFactory(), [
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_USERAGENT => 'MyApp/1.0',
    CURLOPT_SSL_VERIFYPEER => true,
]);

$request = $requestFactory->createRequest('GET', 'https://api.example.com/data');
$response = $client->sendRequest($request);
```

### Different HTTP Protocol Versions

```php
// HTTP/1.0
$request = $requestFactory->createRequest('GET', 'https://api.example.com/data')
    ->withProtocolVersion('1.0');

// HTTP/1.1 (default)
$request = $requestFactory->createRequest('GET', 'https://api.example.com/data')
    ->withProtocolVersion('1.1');

// HTTP/2.0
$request = $requestFactory->createRequest('GET', 'https://api.example.com/data')
    ->withProtocolVersion('2.0');

$response = $client->sendRequest($request);
```

### Error Handling

```php
use Dakiya\NetworkException;

try {
    $response = $client->sendRequest($request);
} catch (NetworkException $e) {
    // Network-level errors (connection failures, timeouts, etc.)
    echo "Network error: " . $e->getMessage();
    $failedRequest = $e->getRequest();
} catch (\Psr\Http\Client\ClientExceptionInterface $e) {
    // Other client exceptions
    echo "Client error: " . $e->getMessage();
}
```

### Large File Upload (Streaming)

For request bodies larger than 1MB, Dakiya automatically uses streaming upload:

```php
$largeFile = file_get_contents('large-file.bin');
$request = $requestFactory->createRequest('POST', 'https://api.example.com/upload')
    ->withBody($streamFactory->createStream($largeFile))
    ->withHeader('Content-Type', 'application/octet-stream');

// Automatically uses streaming for bodies > 1MB
$response = $client->sendRequest($request);
```

## API Reference

### `Client`

The main HTTP client class implementing `Psr\Http\Client\ClientInterface`.

#### Constructor

```php
public function __construct(
    ResponseFactoryInterface $factory,
    array $options = []
)
```

- `$factory` - A PSR-17 ResponseFactoryInterface implementation
- `$options` - Optional array of cURL options to override defaults

#### Methods

##### `sendRequest(RequestInterface $request): ResponseInterface`

Sends a PSR-7 request and returns a PSR-7 response.

**Parameters:**

- `$request` - The PSR-7 request to send

**Returns:**

- `ResponseInterface` - The PSR-7 response

**Throws:**

- `NetworkException` - If a network error occurs (connection failure, timeout, etc.)

### Exceptions

#### `ClientException`

Base exception class for all HTTP client exceptions. Implements `Psr\Http\Client\ClientExceptionInterface`.

#### `NetworkException`

Thrown when a network-level error occurs (connection failures, timeouts, DNS errors, SSL errors). Implements `Psr\Http\Client\NetworkExceptionInterface`.

**Methods:**

- `getRequest(): RequestInterface` - Returns the request that caused the error
- `setRequest(RequestInterface $request): void` - Sets the request that caused the error

#### `RequestException`

Thrown when a request-level error occurs (invalid request format, missing headers, etc.). Implements `Psr\Http\Client\RequestExceptionInterface`.

**Methods:**

- `getRequest(): RequestInterface` - Returns the request that caused the error
- `setRequest(RequestInterface $request): void` - Sets the request that caused the error

## PSR-17 Factory Recommendations

Dakiya requires a PSR-17 factory implementation. We recommend:

- **[vaibhavpandeyvpz/sandesh](https://github.com/vaibhavpandeyvpz/sandesh)** - A lightweight PSR-17/PSR-7 implementation
- **[guzzlehttp/psr7](https://github.com/guzzle/psr7)** - Popular PSR-7 implementation with factories
- **[nyholm/psr7](https://github.com/Nyholm/psr7)** - Fast PSR-7 implementation

## Testing

Run the test suite:

```bash
composer test
```

Or with coverage:

```bash
composer test -- --coverage-clover=coverage.xml
```

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/vaibhavpandeyvpz/dakiya).
