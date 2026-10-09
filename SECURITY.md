# Security Policy

## Supported versions

Venusian is pre-1.0. No 0.x release receives security fixes or advisories; fixes land in the
next release line. Security support starts with 1.0.

| Version | Security fixes |
|---------|----------------|
| < 1.0   | No             |

## Reporting a vulnerability

Please don't open a public issue for a security problem.

Report it privately through GitHub: the **Report a vulnerability** button on this repository's
**Security** tab. If that isn't available, email **info@projectsaturnstudios.com**.

Include what you found, the affected version, and steps to reproduce. Reports are read and
weighed for the release line in development; before 1.0 there is no response-time commitment.

## Security model

- Input nodes are opened read-only and non-blocking; nothing is written to `/dev/input`. Access is the kernel's: the `input` group, or whatever the system grants.
- The only files written are hid-playstation's player LED `brightness` files, and only where the user may write them.
- Every ioctl buffer is sized by the request number it is passed with; struct sizes follow the PHP word size, as the kernel's do; a torn read tail is dropped, never unpacked.
- Paths come from listing `/dev/input/event*` and `/sys/class/input/<node>`, never from input.
