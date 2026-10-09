---
type: Runbook
title: Testing
description: Pest suite with FIFO streams and a fake probe; workbench of path repositories; Mac and Pi.
resource: tests/
tags: [evdev, pest]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-09T12:00:00Z }
sources:
  - id: pest
    resource: tests/Pest.php
    title: tests/Pest.php
---
# Steps
* No hardware: `fifo()` gives a FIFO opened as a node is; `feed()` queues packed `input_event`s; `FakeProbe` answers capability and state reads; `inputWorld()` makes a /dev/input and sysfs of the test's own. `ProbeTest` reads one real node through ioctl and skips where none is readable.
* Workbench, never the repo root: copy without `vendor/`, `.git`, `composer.lock`; symlinked path repositories to `<surface>/src/Surface/*`, `<framework>/src/Voyager/*`; `composer update`.
* Mac: `php84 -d memory_limit=128M vendor/bin/pest`, then `zhp`.
* Pi: the same copy under `~/…`, path repositories relative, `php -d memory_limit=128M vendor/bin/pest`.
* After: delete the workbench.
