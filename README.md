# jovian/venusian-evdev

Game pads from Linux evdev for Venusian Surface's HumanInput: the `evdev` pad source. It reads pads with no window, display or toolkit session, so a headless Pi reads its DualSense over SSH.

## Requirements

- Linux, PHP 8.4+, ext-posi 0.10.
- The user in the `input` group (`sudo usermod -aG input $USER`, then log in again). A node the user may not open is reported, naming the node and the group.

## Install

```bash
composer require jovian/venusian-evdev
```

The provider registers `evdev` with HumanInput. Surface's `human-input.pads.linux` names it by default.

## Usage

In a sketch's loop:

```php
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\HumanInput\MagicAliases\HumanInput;

foreach (HumanInput::gameControllers() as $id => $pad) {          // 'evdev-event4' => DualSense
    $this->x += 2 * $pad->leftStick()['x'];
    if ($pad->isPressed(GamepadButton::SOUTH)) { $this->jump(); }
}
```

A script with no loop and no window:

```php
$input = app('human-input');
while (true) {
    $input->poll();
    foreach ($input->gameControllers() as $id => $pad) {
        echo $id, ' ', json_encode($pad->leftStick()), PHP_EOL;
    }
    usleep(16_000);
}
```

Plug and unplug mail: `input.gamepad.connected.<id>` and `input.gamepad.disconnected.<id>`.

## Behaviour

- **Listing.** Nodes are listed at connect and every two seconds. Pads are found from sysfs (`BTN_SOUTH` in the key bits), so keyboard, touchpad and motion nodes are never opened.
- **Buttons and axes.** Buttons are read by position. xpad (Xbox pads) sends X and Y by label, as `BTN_NORTH` and `BTN_WEST`, so on xpad they are swapped back: X is west, Y north, as on the sdl3 pad source. The sticks read −1…1 inside the node's `flat` deadzone. `ABS_Z`/`ABS_RZ` are the triggers (0…1) when the node has `ABS_RX`. The hat is the dpad.
- **SYN_DROPPED.** Events up to the next report are dropped and the whole state is read again (`EVIOCGKEY`, `EVIOCGABS`).
- **Unplug.** The pad is released (buttons up, axes centred) and reported disconnected. The next listing finds it again.
- **Identity.** `hardwareId()` is the node's `uniq` (a Bluetooth pad's address).
- **Player lights.** `setPlayerIndex($i)` lights hid-playstation's player LEDs in SDL's patterns where the user may write them, and does nothing otherwise.

## Testing

See [.okf/runbooks/testing.md](.okf/runbooks/testing.md). No hardware needed: streams go through FIFOs.

## License

MIT. See [LICENSE](LICENSE).
