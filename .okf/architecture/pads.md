---
type: Module
title: Pads
description: EvdevPadSource and EvdevPad, HumanInput's evdev pad source.
resource: src/EvdevPadSource.php
tags: [evdev, human-input, linux]
status: draft
generated: { by: claude-opus/5.5, at: 2026-10-09T12:00:00Z }
sources:
  - id: source
    resource: src/EvdevPadSource.php
    title: EvdevPadSource
  - id: pad
    resource: src/EvdevPad.php
    title: EvdevPad
---

# Source

Listing at `connect()` and every 2 s: `/dev/input/event*` in natural order; a node is a pad when `/sys/class/input/<node>/device/capabilities/key` has `BTN_SOUTH` (hex words, most significant first, one per C long). Pad nodes opened `O_RDONLY | O_NONBLOCK`; `EACCES` → `HumanInputException` naming node and `input` group (from `connect()`, or from pad reads until a listing opens it); any other open failure = gone, skipped. Ids `evdev-<node>`. Unplug (read null): release, drop; the manager mails it.

# Pad

Buttons by position; `SELECT` → back, `MODE` → guide. Driver xpad (`device/device/driver`) sends X/Y by label: `BTN_NORTH` read as west, `BTN_WEST` as north. Axes: X/Y left, RX/RY right, Z/RZ triggers with RX present (else right stick), TL2/TR2 digital triggers only without analog ones. Hat → dpad. Sticks: (v − middle) / half-span, 0 inside `flat`; triggers (v − min) / span. `SYN_DROPPED`: drop to the next `SYN_REPORT`, then `resync()` from `EVIOCGKEY` and `EVIOCGABS`. Player LEDs: `device/device/leds/*:player-N/brightness`, SDL's patterns 0x04, 0x0A, 0x15, 0x1B, 0x1F, written only where writable.

# Measured

Pi 5 DualSense (2026-10-09): key bits `7fdb000000000000 0 0 0 0`, abs `3003f`; sticks and triggers 0..255, hat −1..1; touchpad and motion nodes lack `BTN_SOUTH`. `input_event` 24 bytes on aarch64. Empty non-blocking read: `false`, `posi_errno()` 11 (`EAGAIN`). LED files root-owned 0644: without write access the lights stay as the kernel set them. Xbox Series pad on xpad (USB): key bits `7cdb000000000000 0 8000000000 0 0`, no uniq; X pressed reads `BTN_NORTH`, Y `BTN_WEST`, A south, B east.
