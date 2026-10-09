<?php

namespace Jovian\Input\Evdev;

/** The evdev codes this package reads (linux/input-event-codes.h). */
final class Codes
{
    public const int EV_SYN = 0x00;

    public const int EV_KEY = 0x01;

    public const int EV_ABS = 0x03;

    public const int SYN_REPORT = 0;

    public const int SYN_DROPPED = 3;

    public const int ABS_X = 0x00;

    public const int ABS_Y = 0x01;

    public const int ABS_Z = 0x02;

    public const int ABS_RX = 0x03;

    public const int ABS_RY = 0x04;

    public const int ABS_RZ = 0x05;

    public const int ABS_HAT0X = 0x10;

    public const int ABS_HAT0Y = 0x11;

    public const int BTN_SOUTH = 0x130;

    public const int BTN_EAST = 0x131;

    public const int BTN_NORTH = 0x133;

    public const int BTN_WEST = 0x134;

    public const int BTN_TL = 0x136;

    public const int BTN_TR = 0x137;

    public const int BTN_TL2 = 0x138;

    public const int BTN_TR2 = 0x139;

    public const int BTN_SELECT = 0x13A;

    public const int BTN_START = 0x13B;

    public const int BTN_MODE = 0x13C;

    public const int BTN_THUMBL = 0x13D;

    public const int BTN_THUMBR = 0x13E;

    public const int BTN_DPAD_UP = 0x220;

    public const int BTN_DPAD_DOWN = 0x221;

    public const int BTN_DPAD_LEFT = 0x222;

    public const int BTN_DPAD_RIGHT = 0x223;
}
