# Log

## 2026-10-28

* **Version**: 0.11.0 with the Venusian 0.11 ecosystem: `dev-main` → `0.11.x-dev`, its extensions, `venusian-surface/*`, `venusian-voyager/*` and the jovian siblings at `^0.11.0`, PHP `~8.4.27||^8.5.12`, homepage `0.11.x`. No API change. 25 passed, 1 skipped on Homebrew PHP 8.4 NTS and ZTS; 26 passed on the Pi (8.4 ZTS).

## 2026-10-09

* Created the bundle for 0.10.0: the `evdev` pad source for HumanInput, replacing 0.8's microscrap/evdev and microscrap/scrapyard-evdev. [pads](architecture/pads.md)
* xpad X/Y read by position; a refusal says permission denied and names the group and the node's mode; a dropped EvdevDevice closes its node. [pads](architecture/pads.md)
* A replugged pad is found at the next listing on a ZTS PHP: listings clear the realpath cache, which held the node's old sysfs target for up to 120 s. [pads](architecture/pads.md)
