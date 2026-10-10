# 15 Logs

To log things, there's `SkankyDev\Utilities\Log`, a static class. It writes one file per day in `logs/` at the project root, created automatically when needed.

```php
Log::info('message', 'context');      // logs/{date}-context.log (default context: 'skankydev')
Log::warning('message', 'context');   // same file as info, WARNING prefix
Log::debug('message', $context);      // logs/{date}-debug.log, does nothing if debug is off
Log::error($exception, $context);     // logs/{date}-error.log, full exception + trace
Log::job($jobName, $status, $details);// logs/{date}-jobs.log, a job's lifecycle
```

It's not just for exceptions. `Queue` and `QueueWork` use it to trace the life of jobs (`queued`, `completed`, `failed`, see [17 Queue and Jobs](017-Queue-and-Jobs.md)), regardless of any error.

`Log::error()` is what `ExceptionHandler` calls ([14 Error handling](014-Error-Handling.md)), but nothing stops you from calling it yourself elsewhere.

`Log::debug()` is the only conditional one: if the `debug` config is off, it does nothing, quietly. No need to wrap it in an `if` on every call.

## Cleaning up

`Log::cleanup(int $days = 10)` deletes the files in `logs/` older than `$days` days. Nothing calls it automatically in the framework: it's up to you to trigger it (a dedicated CLI command, a cron on the project side) if you want rotation.
