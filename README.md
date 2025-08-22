Postal Mailer
=============

Provides [Postal Email](https://docs.postalserver.io/) integration for Symfony Mailer.

## Symfony Postal Mailer Bridge

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](LICENSE)

A Symfony Mailer Bridge for [Postal](https://postalserver.io/) using the official [postalserver/postal-php](https://github.com/postalserver/postal-php) client. This package is inspired by [symfony/postal-mailer](https://github.com/symfony/postal-mailer), but leverages the official Postal PHP client instead of implementing the API manually.

## Features
- Seamless integration with Symfony Mailer
- Uses the official Postal PHP client for reliability and future compatibility
- Simple configuration and usage

## Installation

```bash
composer require thienvu18/symfony-postal-mailer
```

## Usage

Configure your Symfony Mailer to use the Postal transport. Example configuration:

```yaml
# config/packages/mailer.yaml
framework:
  mailer:
    dsn: 'postal+api://<api-key>@<host>:<port>'
```

Replace `<api-key>`, `<host>`, and `<port>` with your Postal server details.

## Usage with Laravel

You can use this package as a custom Symfony transport in Laravel by registering the Postal transport in the `boot` method of one of your service providers (e.g., `App\Providers\AppServiceProvider`):

```php
use Illuminate\Support\Facades\Mail;
use Kyle\PostalMailer\Transport\PostalTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

public function boot(): void
{
  Mail::extend('postal', function () {
      return (new PostalTransportFactory)->create(
          new Dsn(
              'postal+api',
              config('services.postal.base_url'),
              null,
              config('services.postal.key')
          )
      );
  });
}
```

Next, add a new mailer to your `config/mail.php`:

```php
'mailers' => [
  'postal' => [
    'transport' => 'postal',
  ],
  // ...existing mailers...
],
```
Then, add an entry for your Postal API credentials to your application's `config/services.php` configuration file:

```php
'services' => [
  'postal' => [
      'base_url' => env('POSTAL_BASE_URL'),
      'key' => env('POSTAL_API_KEY'),
  ],
  // ...existing services
]
```

Also, set your API key in your `.env` file:

```env
POSTAL_BASE_URL=your-base-url
POSTAL_API_KEY=your-postal-api-key
```

Finally, set your default mailer to `postal` in your `.env` file:

```dotenv
MAIL_MAILER=postal
```


## Credits

- [symfony/postal-mailer](https://github.com/symfony/postal-mailer) for the original inspiration and implementation ideas.
- [postalserver/postal-php](https://github.com/postalserver/postal-php) for the official Postal PHP client.

Thank you to both projects for their excellent work and open source contributions!

## License

This project is licensed under the [GPL-3.0-or-later](LICENSE).

