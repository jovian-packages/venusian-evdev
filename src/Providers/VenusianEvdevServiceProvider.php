<?php

namespace Jovian\Input\Evdev\Providers;

use Jovian\Input\Evdev\EvdevPadSource;
use Surface\HumanInput\HumanInputManager;
use Surface\HumanInput\InputFrame;
use Voyager\NutsAndBolts\ServiceProvider;

/** Registers the 'evdev' pad source with Surface's HumanInput, on Linux with ext-posi loaded. */
class VenusianEvdevServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        if (PHP_OS_FAMILY === 'Linux' && extension_loaded('posi') && $this->app->has('human-input')) {
            self::pads($this->app->get('human-input'));
        }
    }

    /** The 'evdev' pad source, on any HumanInput manager: Surface names no pad source. */
    public static function pads(HumanInputManager $input): void
    {
        $input->extendPads('evdev', fn (InputFrame $frame): EvdevPadSource => new EvdevPadSource($frame));
    }
}
