<?php

namespace Jovian\Input\Evdev;

use Closure;
use Surface\Contracts\HumanInput\GamepadAxis;
use Surface\Contracts\HumanInput\GamepadButton;
use Surface\HumanInput\Devices\GameController;
use Surface\HumanInput\Devices\GamePad;
use Surface\HumanInput\InputFrame;

/**
 * One pad node as a Surface device. Buttons by position (BTN_SOUTH is south whatever is printed
 * on it); ABS_X/Y the left stick, ABS_RX/RY the right; ABS_Z/RZ the triggers when the node has
 * ABS_RX, else the right stick; BTN_TL2/TR2 triggers at 0 or 1 only without analog ones; the hat
 * the dpad. After SYN_DROPPED (the kernel's buffer for this reader overflowed) events up to the
 * next SYN_REPORT are stale, so they are dropped and the state is read whole instead.
 */
final class EvdevPad
{
    /** @var array<int, GamepadButton> */
    private const array BUTTONS = [
        Codes::BTN_SOUTH => GamepadButton::SOUTH,
        Codes::BTN_EAST => GamepadButton::EAST,
        Codes::BTN_NORTH => GamepadButton::NORTH,
        Codes::BTN_WEST => GamepadButton::WEST,
        Codes::BTN_TL => GamepadButton::LEFT_SHOULDER,
        Codes::BTN_TR => GamepadButton::RIGHT_SHOULDER,
        Codes::BTN_SELECT => GamepadButton::BACK,
        Codes::BTN_START => GamepadButton::START,
        Codes::BTN_MODE => GamepadButton::GUIDE,
        Codes::BTN_THUMBL => GamepadButton::LEFT_STICK,
        Codes::BTN_THUMBR => GamepadButton::RIGHT_STICK,
        Codes::BTN_DPAD_UP => GamepadButton::DPAD_UP,
        Codes::BTN_DPAD_DOWN => GamepadButton::DPAD_DOWN,
        Codes::BTN_DPAD_LEFT => GamepadButton::DPAD_LEFT,
        Codes::BTN_DPAD_RIGHT => GamepadButton::DPAD_RIGHT,
    ];

    private bool $dropping = false;

    /**
     * @param array<int, GamepadButton> $buttons by key code
     * @param array<int, array{GamepadAxis, AbsInfo}> $axes by abs code, with the range read at adoption
     * @param array<int, GamepadAxis> $digital trigger buttons standing in for missing analog triggers
     */
    private function __construct(
        private readonly EvdevDevice $device,
        private readonly GamePad $pad,
        private readonly array $buttons,
        private readonly array $axes,
        private readonly array $digital,
        private readonly bool $hat,
    ) {}

    /**
     * @param (Closure(?int): void)|null $lights
     * @param bool $labelled the driver sends X and Y by label (BTN_X, BTN_Y), not position: xpad's
     *        BTN_X (= BTN_NORTH) is the west button and BTN_Y (= BTN_WEST) the north one
     */
    public static function adopt(EvdevDevice $device, InputFrame $frame, ?Closure $lights = null, bool $labelled = false): self
    {
        $probe = $device->probe;
        $keys = $probe->codes(Codes::EV_KEY);
        $abs = $probe->codes(Codes::EV_ABS);
        $map = $labelled
            ? [Codes::BTN_NORTH => GamepadButton::WEST, Codes::BTN_WEST => GamepadButton::NORTH] + self::BUTTONS
            : self::BUTTONS;
        $buttons = array_filter($map, fn (int $code): bool => in_array($code, $keys, true), ARRAY_FILTER_USE_KEY);
        $hat = in_array(Codes::ABS_HAT0X, $abs, true) || in_array(Codes::ABS_HAT0Y, $abs, true);
        $analog = in_array(Codes::ABS_RX, $abs, true);
        $places = [
            Codes::ABS_X => GamepadAxis::LEFT_X,
            Codes::ABS_Y => GamepadAxis::LEFT_Y,
            Codes::ABS_RX => GamepadAxis::RIGHT_X,
            Codes::ABS_RY => GamepadAxis::RIGHT_Y,
            Codes::ABS_Z => $analog ? GamepadAxis::LEFT_TRIGGER : GamepadAxis::RIGHT_X,
            Codes::ABS_RZ => $analog ? GamepadAxis::RIGHT_TRIGGER : GamepadAxis::RIGHT_Y,
        ];

        $axes = [];
        foreach ($places as $code => $axis) {
            $range = in_array($code, $abs, true) ? $probe->absInfo($code) : null;
            if (! is_null($range) && ! in_array($axis, array_column($axes, 0), true)) {
                $axes[$code] = [$axis, $range];
            }
        }
        $digital = [];
        foreach ([Codes::BTN_TL2 => GamepadAxis::LEFT_TRIGGER, Codes::BTN_TR2 => GamepadAxis::RIGHT_TRIGGER] as $code => $axis) {
            if (in_array($code, $keys, true) && ! in_array($axis, array_column($axes, 0), true)) {
                $digital[$code] = $axis;
            }
        }

        $pressable = [];
        foreach ([...array_values($buttons), ...($hat ? [GamepadButton::DPAD_UP, GamepadButton::DPAD_DOWN, GamepadButton::DPAD_LEFT, GamepadButton::DPAD_RIGHT] : [])] as $button) {
            $pressable[$button->value] = $button;
        }
        $axis_list = [...array_column($axes, 0), ...array_values($digital)];
        $id = "evdev-{$device->node}";
        $name = $probe->name() !== '' ? $probe->name() : $id;
        $pad = in_array(GamepadAxis::LEFT_X, $axis_list, true) || in_array(GamepadAxis::RIGHT_X, $axis_list, true)
            ? new GameController($frame, $id, $name, array_values($pressable), $axis_list, $probe->uniq(), $lights)
            : new GamePad($frame, $id, $name, array_values($pressable), $probe->uniq(), $lights);

        $adopted = new self($device, $pad, $buttons, $axes, $digital, $hat);
        $adopted->resync();

        return $adopted;
    }

    public function pad(): GamePad
    {
        return $this->pad;
    }

    /** Apply what the node queued. False once the node is gone. */
    public function poll(): bool
    {
        $events = $this->device->read();
        if (is_null($events)) {
            return false;
        }
        foreach ($events as $event) {
            $this->apply($event);
        }

        return true;
    }

    public function apply(InputEvent $event): void
    {
        if ($this->dropping) {
            if ($event->type === Codes::EV_SYN && $event->code === Codes::SYN_REPORT) {
                $this->dropping = false;
                $this->resync();
            }

            return;
        }

        match (true) {
            $event->type === Codes::EV_SYN && $event->code === Codes::SYN_DROPPED => $this->dropping = true,
            $event->type === Codes::EV_KEY && isset($this->buttons[$event->code]) => $this->pad->update($this->buttons[$event->code], $event->value !== 0),
            $event->type === Codes::EV_KEY && isset($this->digital[$event->code]) => $this->setAxis($this->digital[$event->code], $event->value !== 0 ? 1.0 : 0.0),
            $event->type === Codes::EV_ABS && $event->code === Codes::ABS_HAT0X => $this->hat(GamepadButton::DPAD_LEFT, GamepadButton::DPAD_RIGHT, $event->value),
            $event->type === Codes::EV_ABS && $event->code === Codes::ABS_HAT0Y => $this->hat(GamepadButton::DPAD_UP, GamepadButton::DPAD_DOWN, $event->value),
            $event->type === Codes::EV_ABS && isset($this->axes[$event->code]) => $this->setAxis($this->axes[$event->code][0], self::normal($this->axes[$event->code][0], $this->axes[$event->code][1], $event->value)),
            default => null,
        };
    }

    /** Read the node's whole state: held keys (EVIOCGKEY) and every axis's value (EVIOCGABS). */
    public function resync(): void
    {
        $probe = $this->device->probe;
        $held = $probe->held();
        foreach ($this->buttons as $code => $button) {
            $this->pad->update($button, in_array($code, $held, true));
        }
        foreach ($this->digital as $code => $axis) {
            $this->setAxis($axis, in_array($code, $held, true) ? 1.0 : 0.0);
        }
        foreach ($this->axes as $code => [$axis, $range]) {
            $now = $probe->absInfo($code);
            if (! is_null($now)) {
                $this->setAxis($axis, self::normal($axis, $range, $now->value));
            }
        }
        if ($this->hat) {
            foreach ([Codes::ABS_HAT0X => [GamepadButton::DPAD_LEFT, GamepadButton::DPAD_RIGHT], Codes::ABS_HAT0Y => [GamepadButton::DPAD_UP, GamepadButton::DPAD_DOWN]] as $code => [$negative, $positive]) {
                $now = $probe->absInfo($code);
                if (! is_null($now)) {
                    $this->hat($negative, $positive, $now->value);
                }
            }
        }
    }

    /** Let go of everything and close the node: a sketch still holding the device reads it at rest. */
    public function release(): void
    {
        $this->pad->releaseAll();
        $this->device->close();
    }

    /** An axis value from its range: sticks −1..1 about the middle, zero inside the flat deadzone; triggers 0..1 from the minimum. */
    public static function normal(GamepadAxis $axis, AbsInfo $range, int $value): float
    {
        $span = $range->maximum - $range->minimum;
        if ($span <= 0) {
            return 0.0;
        }
        if ($axis === GamepadAxis::LEFT_TRIGGER || $axis === GamepadAxis::RIGHT_TRIGGER) {
            return ($value - $range->minimum) / $span;
        }
        $middle = ($range->minimum + $range->maximum) / 2;
        if (abs($value - $middle) <= $range->flat) {
            return 0.0;
        }

        return ($value - $middle) / ($span / 2);
    }

    private function setAxis(GamepadAxis $axis, float $value): void
    {
        if ($this->pad instanceof GameController) {
            $this->pad->setAxis($axis, $value);
        }
    }

    private function hat(GamepadButton $negative, GamepadButton $positive, int $value): void
    {
        $this->pad->update($negative, $value < 0);
        $this->pad->update($positive, $value > 0);
    }
}
