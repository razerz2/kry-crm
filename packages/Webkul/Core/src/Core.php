<?php

namespace Webkul\Core;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Webkul\Core\Repositories\CoreConfigRepository;
use Webkul\Core\Repositories\CountryRepository;
use Webkul\Core\Repositories\CountryStateRepository;

class Core
{
    /**
     * The Krayin version.
     *
     * @var string
     */
    const KRAYIN_VERSION = '2.2.0';

    /**
     * Create a new instance.
     *
     * @return void
     */
    public function __construct(
        protected CountryRepository $countryRepository,
        protected CoreConfigRepository $coreConfigRepository,
        protected CountryStateRepository $countryStateRepository
    ) {}

    /**
     * Get the version number of the Krayin.
     *
     * @return string
     */
    public function version()
    {
        return static::KRAYIN_VERSION;
    }

    /**
     * Retrieve all timezones.
     */
    public function timezones(): array
    {
        $timezones = [];

        foreach (timezone_identifiers_list() as $timezone) {
            $timezones[$timezone] = $timezone;
        }

        return $timezones;
    }

    /**
     * Retrieve all locales.
     */
    public function locales(): array
    {
        $options = [];

        foreach (config('app.available_locales') as $key => $title) {
            $options[] = [
                'title' => $title,
                'value' => $key,
            ];
        }

        return $options;
    }

    /**
     * Retrieve all countries.
     *
     * @return Collection
     */
    public function countries()
    {
        return $this->countryRepository->all();
    }

    /**
     * Returns country name by code.
     */
    public function country_name(string $code): string
    {
        $country = $this->countryRepository->findOneByField('code', $code);

        return $country ? $country->name : '';
    }

    /**
     * Returns state name by code.
     */
    public function state_name(string $code): string
    {
        $state = $this->countryStateRepository->findOneByField('code', $code);

        return $state ? $state->name : $code;
    }

    /**
     * Retrieve all country states.
     *
     * @return Collection
     */
    public function states(string $countryCode)
    {
        return $this->countryStateRepository->findByField('country_code', $countryCode);
    }

    /**
     * Retrieve all grouped states by country code.
     *
     * @return Collection
     */
    public function groupedStatesByCountries()
    {
        $collection = [];

        foreach ($this->countryStateRepository->all() as $state) {
            $collection[$state->country_code][] = $state->toArray();
        }

        return $collection;
    }

    /**
     * Retrieve all grouped states by country code.
     *
     * @return Collection
     */
    public function findStateByCountryCode($countryCode = null, $stateCode = null)
    {
        $collection = [];

        $collection = $this->countryStateRepository->findByField([
            'country_code' => $countryCode,
            'code' => $stateCode,
        ]);

        if (count($collection)) {
            return $collection->first();
        } else {
            return false;
        }
    }

    /**
     * Create singleton object through single facade.
     *
     * @param  string  $className
     * @return mixed
     */
    public function getSingletonInstance($className)
    {
        static $instances = [];

        if (array_key_exists($className, $instances)) {
            return $instances[$className];
        }

        return $instances[$className] = app($className);
    }

    /**
     * Format date
     *
     * @return string
     */
    public function formatDate($date, $format = 'd M Y h:iA')
    {
        return Carbon::parse($date)->format($format);
    }

    /**
     * Week range.
     *
     * @param  string  $date
     * @param  int  $day
     * @return string
     */
    public function xWeekRange($date, $day)
    {
        $ts = strtotime($date);

        if (! $day) {
            $start = (date('D', $ts) == 'Sun') ? $ts : strtotime('last sunday', $ts);

            return date('Y-m-d', $start);
        } else {
            $end = (date('D', $ts) == 'Sat') ? $ts : strtotime('next saturday', $ts);

            return date('Y-m-d', $end);
        }
    }

    /**
     * Return currency symbol from currency code.
     *
     * @param  float  $price
     * @return string
     */
    public function currencySymbol($code)
    {
        $code = strtoupper((string) ($code ?: config('app.currency')));

        if (class_exists(\NumberFormatter::class)) {
            try {
                $formatter = new \NumberFormatter(app()->getLocale().'@currency='.$code, \NumberFormatter::CURRENCY);

                $symbol = $formatter->getSymbol(\NumberFormatter::CURRENCY_SYMBOL);

                if (is_string($symbol) && $symbol !== '') {
                    return $symbol;
                }
            } catch (\Throwable $exception) {
                // Fallback handled below when intl data/locale is unavailable.
            }
        }

        return $this->fallbackCurrencySymbol($code);
    }

    /**
     * Format price with base currency symbol. This method also give ability to encode
     * the base currency symbol and its optional.
     *
     * @param  float  $price
     * @return string
     */
    public function formatBasePrice($price)
    {
        if (is_null($price)) {
            $price = 0;
        }

        $currencyCode = strtoupper((string) config('app.currency', 'USD'));

        if (class_exists(\NumberFormatter::class)) {
            try {
                $formatter = new \NumberFormatter(app()->getLocale(), \NumberFormatter::CURRENCY);

                $formatted = $formatter->formatCurrency((float) $price, $currencyCode);

                if ($formatted !== false) {
                    return $formatted;
                }
            } catch (\Throwable $exception) {
                // Fallback handled below when intl data/locale is unavailable.
            }
        }

        return $this->fallbackCurrencySymbol($currencyCode).' '.number_format((float) $price, 2, '.', ',');
    }

    /**
     * Resolve a safe currency symbol when intl extension is unavailable.
     */
    protected function fallbackCurrencySymbol(string $code): string
    {
        $configuredSymbol = $this->configuredCurrencySymbol($code);

        if ($configuredSymbol !== null) {
            return $configuredSymbol;
        }

        return match ($code) {
            'AED' => 'AED',
            'ARS' => '$',
            'AUD' => '$',
            'BDT' => 'BDT',
            'BRL' => 'R$',
            'CAD' => '$',
            'CHF' => 'CHF',
            'CLP' => '$',
            'CNY' => 'CNY',
            'COP' => '$',
            'CZK' => 'CZK',
            'DKK' => 'kr',
            'DZD' => 'DZD',
            'EGP' => 'EGP',
            'EUR' => 'EUR',
            'FJD' => '$',
            'GBP' => 'GBP',
            'HKD' => '$',
            'HUF' => 'Ft',
            'IDR' => 'Rp',
            'ILS' => 'ILS',
            'INR' => 'INR',
            'JOD' => 'JD',
            'JPY' => 'JPY',
            'KRW' => 'KRW',
            'KWD' => 'KD',
            'KZT' => 'KZT',
            'LBP' => 'LBP',
            'LKR' => 'Rs',
            'LYD' => 'LYD',
            'MAD' => 'MAD',
            'MUR' => 'MUR',
            'MXN' => '$',
            'MYR' => 'RM',
            'NGN' => 'NGN',
            'NOK' => 'kr',
            'NPR' => 'NPR',
            'NZD' => '$',
            'OMR' => 'OMR',
            'PAB' => 'B/.',
            'PEN' => 'S/',
            'PHP' => 'PHP',
            'PKR' => 'PKR',
            'PLN' => 'PLN',
            'PYG' => 'PYG',
            'QAR' => 'QAR',
            'RON' => 'lei',
            'RUB' => 'RUB',
            'SAR' => 'SAR',
            'SEK' => 'kr',
            'SGD' => '$',
            'THB' => 'THB',
            'TND' => 'DT',
            'TRY' => 'TRY',
            'TWD' => 'NT$',
            'UAH' => 'UAH',
            'USD' => '$',
            'UZS' => 'UZS',
            'VEF' => 'Bs',
            'VND' => 'VND',
            'XAF' => 'FCFA',
            'XOF' => 'CFA',
            'ZAR' => 'R',
            'ZMW' => 'ZK',
            default => $code,
        };
    }

    /**
     * Return custom configured symbol when available.
     */
    protected function configuredCurrencySymbol(string $code): ?string
    {
        $code = strtoupper($code);

        $configuredSymbols = config('app.currency_symbols');

        if (
            is_array($configuredSymbols)
            && isset($configuredSymbols[$code])
            && is_string($configuredSymbols[$code])
            && trim($configuredSymbols[$code]) !== ''
        ) {
            return trim($configuredSymbols[$code]);
        }

        $appCurrencyCode = strtoupper((string) config('app.currency'));
        $appCurrencySymbol = config('app.currency_symbol');

        if (
            $appCurrencyCode === $code
            && is_string($appCurrencySymbol)
            && trim($appCurrencySymbol) !== ''
        ) {
            return trim($appCurrencySymbol);
        }

        return null;
    }

    /**
     * Get the config field.
     */
    public function getConfigField(string $fieldName): ?array
    {
        return system_config()->getConfigField($fieldName);
    }

    /**
     * Retrieve information for configuration.
     */
    public function getConfigData(string $field): mixed
    {
        return system_config()->getConfigData($field);
    }
}
