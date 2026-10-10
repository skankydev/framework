# 18 Mails

## The mail object

A mail from your app is a class that extends `MasterMail`: a subject, some data, and a view that uses them (in dot notation like any view, but **without the site's layout**):

```php
class WelcomeMail extends MasterMail {
    protected string $view = 'mail.welcome';

    public function __construct(string $name, string $verifyUrl) {
        $this->data = ['name' => $name, 'verifyUrl' => $verifyUrl];
        $this->addAttachment('/path/to/terms.pdf', 'Terms.pdf');
    }

    public function subject(): string {
        return 'Welcome!';
    }
}
```

The rendering (`content()`) is called by the sender, never by you: it renders the `mail.welcome` view with `$data`, without a layout. An HTML mail doesn't need the site's header, nav or footer, just its own content.

A rule to remember: only put simple values in `$data` (strings, numbers), never a full Document. A mail can go out through a job, and everything that goes into a job sits in the `jobs` collection until it's processed ([17 Queue and Jobs](017-Queue-and-Jobs.md)).

## The sender

`MailSender` sends a `MasterMail` over SMTP (PHPMailer underneath), configured with the `smtp` key:

```php
'smtp' => [
    'host'           => getenv('MAIL_HOST'),
    'port'           => getenv('MAIL_PORT'),
    'secure'         => getenv('MAIL_SECURE'),   // 'tls', 'ssl', or empty
    'username'       => getenv('MAIL_USERNAME'),
    'password'       => getenv('MAIL_PASSWORD'),
    'default_sender' => getenv('MAIL_SENDER'),
],
```

It's a singleton, like the Collections, so same static call convention with `_`:

```php
MailSender::_send($email, new WelcomeMail($name, $verifyUrl));
MailSender::_send([$email1, $email2], $mail); // several recipients
```

If sending fails (badly configured SMTP, connection refused...), you get a `MailException`, not a silent `false`.

## The job for the queue

Sending a mail is slow (an SMTP connection has to be opened). You don't want to make the request that just triggered it wait (a sign-up, a user action). `SendMailJob` wraps the call to `MailSender` so it goes through the queue ([17](017-Queue-and-Jobs.md)):

```php
Queue::push(new SendMailJob($email, new WelcomeMail($name, $verifyUrl)));
```

The job only makes one call to `MailSender::_send()` in `run()`. The worker runs it in the background, and the original HTTP request doesn't wait for it.

## Under the hood

`MasterMail` extends `EmbeddedDocument` ([09.1](009.1-Persistable.md)). That's what lets a concrete mail (`WelcomeMail` or another subclass) be stored as is as a job's content, then rebuilt with the right class when reading. Hence the rule about `$data`.
