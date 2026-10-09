# AGENTS.md

1. Bindings are ext-posi's: `posix_open`, `posix_read`, `posix_close`, `ioctl`, `posi_errno`. This package computes evdev request numbers and unpacks the kernel's structs; it holds no C.
2. Pads only: a node is a pad when its sysfs key bits have `BTN_SOUTH`. Joysticks without it are recognised and left closed until the joystick slice (HumanInput spec, slice 24).
3. Tests need no hardware: streams go through FIFOs, capabilities through `FakeProbe`. One test reads a real node through ioctl and skips where none is readable.
4. Errors say what to do: a refused node names the node and the `input` group.
5. Publish prep is part of done: README examples run, `.okf` validated.
