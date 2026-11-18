<?php

namespace App\Enums;

enum BookingType: string
{
    case Local = 'local';
    case OutstationOneway = 'outstation_oneway';
    case OutstationRound = 'outstation_round';
    case Airport = 'airport';
    case Railway = 'railway';
    case Hourly = 'hourly';
    case FullDay = 'full_day';
    case Corporate = 'corporate';

    public function label(): string
    {
        return match ($this) {
            self::Local => __('Local taxi'),
            self::OutstationOneway => __('Outstation one-way'),
            self::OutstationRound => __('Outstation round trip'),
            self::Airport => __('Airport pickup / drop'),
            self::Railway => __('Railway station pickup / drop'),
            self::Hourly => __('Hourly rental'),
            self::FullDay => __('Full-day rental'),
            self::Corporate => __('Corporate booking'),
        };
    }

    public function isRental(): bool
    {
        return in_array($this, [self::Hourly, self::FullDay], true);
    }

    public function isOutstation(): bool
    {
        return in_array($this, [self::OutstationOneway, self::OutstationRound], true);
    }

    public function needsDrop(): bool
    {
        return ! $this->isRental();
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()])->all();
    }
}
