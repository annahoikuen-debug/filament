# Security Policy

Please review the [Version Support Policy](https://filamentphp.com/docs/introduction/version-support-policy) on our website.

## Reporting a Vulnerability

If you discover a security vulnerability within Filament, please [report it through GitHub](https://github.com/filamentphp/filament/security/advisories). All security vulnerabilities will be promptly addressed.

## PHP Requirements

`composer.json` requires PHP patch releases that include the latest upstream security fixes:

```
>=8.2.35 <8.3 || >=8.3.36 <8.4 || >=8.4.27 <8.5 || >=8.5.12 <8.6
```

Verify the version in use with `php -v`.

## Deployment Recommendations

When deploying an application built with Filament to production, consider the following:

- Set `SESSION_SECURE_COOKIE=true` and `SESSION_SAME_SITE=lax` in your `.env`.
- Send security headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy`) from your web server or CDN.
- Do not grant execute permissions to file upload directories, and validate uploaded MIME types server-side.
- Commit `composer.lock` and deploy with `composer install --no-dev --optimize-autoloader`.
