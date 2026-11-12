# Password-reset SMTP verification

Customer and native CMS reset emails are exercised through the real Laravel/Symfony
SMTP transport against an isolated loopback test server. These tests do not use
Notification/Mail fakes, send to external recipients, save message bodies or alter
existing demo account passwords.

Two guarded integration tests use disposable `erepair_test` records:

- Customer forgot-password sends an actual SMTP message. Its delivered plain-text
  link targets the React reset form; submitting the real token resets that test
  customer's password.
- Native Statamic Core forgot-password sends an actual SMTP message. Its link
  targets the native CP reset form and resets only the independent CMS test login.
  The linked application's password stays unchanged.

Laravel's sent-message event is observed only after transport delivery, and the
SMTP fixture records an acceptance count. It uses an ephemeral loopback port,
discards message bodies and has a bounded lifetime plus a parent-owned shutdown
handshake. Tests assert fixture exit, avoiding Windows WMI/taskkill cleanup that
can stall in restricted sessions. No browser/OS security settings are changed.

The full backend suite includes these tests when `RUN_POSTGRES_TESTS=1` and the
dedicated database is configured. Node is already a project prerequisite and runs
the test-only fixture; no new dependency or production service is installed. A
focused run from `backend` is:

```text
php artisan test --filter=SmtpDeliveryIntegrationTest
```

The local application's default mailer remains `log`. This verifies the mail/reset
code path and local SMTP delivery, not receipt at a public mailbox. Production
still needs real provider credentials, sender-domain configuration, HTTPS reset
origins and an actual inbox-delivery check. The production preflight correctly
continues to reject a log mailer. Tests and their SMTP fixture stay in the source
repository; production packages do not need the test server or Node backend runtime.

## Production mail configuration

The SMTP configuration now uses a 15-second default timeout. The production
template sets `MAIL_TIMEOUT=15` and `MAIL_REQUIRE_TLS=true`. For remote SMTP on
port 587, keep required STARTTLS enabled; port 465 uses implicit TLS. Certificate
verification must remain enabled. A timeout must be greater than zero and no more
than 60 seconds for the production check. Local test relays can use anonymous SMTP
without TLS; this does not establish public delivery acceptance.

`MAIL_URL`, when set, overrides individual SMTP fields using Laravel's own URL
parser. The preflight inspects that effective configuration, including URL options,
and rejects malformed URLs, placeholders, partial credentials, disabled certificate
verification and insecure remote SMTP. It does not print the URL or credentials.
The regression suite constructs the native Symfony transport without connecting
and confirms that the resolved host, port, timeout and required TLS match the check.

Optional Postmark, Resend, Mailgun and SES transports require their corresponding
Laravel/Symfony SDK packages and provider configuration. Selecting a transport
name alone does not install its SDK. SES may deliberately use the SDK's IAM/default
credential chain; the preflight cannot verify that chain's runtime permissions.
Sendmail requires an available executable. No optional provider package was added.

The preflight only checks configuration and installed capabilities. It does not
contact providers or send mail. Verify actual password-reset receipt and links on
the production HTTPS origin before launch. The laptop still uses its existing log
mailer and correctly fails that production requirement.
