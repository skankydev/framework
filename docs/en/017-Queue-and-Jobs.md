# 17 Queue and Jobs

## Jobs and how to run them

A job is a task you set aside to do later, in the background, without making the current request wait. It's a class that extends `MasterJob`:

```php
class SendMailJob extends MasterJob {
    public string|array $to;
    public MasterMail $mail;

    public function __construct(string|array $to, MasterMail $mail) {
        $this->to   = $to;
        $this->mail = $mail;
    }

    public function run(): void {
        MailSender::_send($this->to, $this->mail);
    }
}
```

To put it on hold, you call `Queue::push()`:

```php
Queue::push(new SendMailJob($email, new WelcomeMail($name)));
```

`push()` stores the job in a `JobDoc` (status `pending`, `attempts` at 0, `max_attempts` at 3 by default) and saves it in the `jobs` collection. That's it: the current request carries on without waiting for the job to run.

## The command for the workers

The worker is the one that runs the jobs. You start it with:

```bash
php craft queue-worker
```

It's an infinite loop: it takes the oldest `pending` job, switches it to `processing`, runs `run()`, then:

- **success**: the `JobDoc` is deleted. No need to keep it in the database, and it avoids potentially sensitive content lying around there forever.
- **failure** (a caught exception): `attempts` goes up and the status goes back to `pending` for another try, unless `max_attempts` is reached: the status then becomes `failed`. The error is logged (`Log::error()`, see [15 Logs](015-Logs.md)) and its message is kept on the `JobDoc`.

When no job is pending, the loop waits a second before looping again (`sleep(1)`).

## Keeping the worker running

The worker isn't supposed to stop on its own. In production, you need a process supervisor that restarts it if it crashes or if the server reboots (Supervisor, systemd, or the equivalent for your deployment). An example with Supervisor:

```ini
[program:queue-worker]
command=php /path/to/the/project/craft queue-worker
autostart=true
autorestart=true
```

There's nothing framework-specific in there: `queue-worker` is just a normal, long-running PHP process, kept alive like any other worker.

## Under the hood

`MasterJob` is itself an `EmbeddedDocument` ([09.1](009.1-Persistable.md)). That's what lets it be stored as is in Mongo and rebuilt with the right concrete subclass when reading (thanks to `__pclass`), without going through a constructor that would have to be guessed.

See also: [18 Mails](018-Mails.md) (`SendMailJob`), [09.3 Relations and propagation](009.3-Relations.md) (`SnapshotSyncJob`)
