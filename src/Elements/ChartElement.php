<?php

namespace Donmanueldev\NativephpCharts\Elements;

use Donmanueldev\NativephpCharts\Support\AxisNormalizer;
use Donmanueldev\NativephpCharts\Support\CallbackExpression;
use Donmanueldev\NativephpCharts\Support\ChartStyleNormalizer;
use Donmanueldev\NativephpCharts\Support\LegendNormalizer;
use Donmanueldev\NativephpCharts\Support\WireEncoder;
use InvalidArgumentException;
use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\Element;

abstract class ChartElement extends Element
{
    /** @var array<string, bool|int|string> */
    protected array $chartProps = [
        'animated' => true,
        'empty_label' => 'No data',
        'a11y_label' => 'Chart',
        'locale' => '',
        'value_format' => 'number',
        'currency_code' => '',
        'minimum_fraction_digits' => -1,
        'maximum_fraction_digits' => -1,
    ];

    /** @var array<string, mixed> */
    protected array $legend = [];

    /** @var array<string, array<string, mixed>> */
    protected array $chartStyle = [];

    protected ?string $selectMethod = null;

    /** @var array<string, bool|int|string>|null */
    private ?array $commonWireSnapshot = null;

    private ?int $commonWireLegendItemCount = null;

    abstract protected function chartType(): string;

    public static function make(): static
    {
        return new static;
    }

    /** @param array<string, mixed> $style */
    public function style(array $style): static
    {
        $this->chartStyle = ChartStyleNormalizer::normalize($style, $this->chartType(), $this->chartName());
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    /** @param array<string, mixed> $legend */
    public function legend(array $legend): static
    {
        LegendNormalizer::normalize($legend, $this->legendItemCount(), $this->chartName());
        $this->legend = $legend;
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function onSelect(string $method): static
    {
        $this->selectMethod = CallbackExpression::normalize($method, $this->chartName());

        return $this;
    }

    public function animated(bool $animated): static
    {
        $this->chartProps['animated'] = $animated;
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function emptyLabel(string $emptyLabel): static
    {
        $this->chartProps['empty_label'] = $this->requiredText($emptyLabel, 'empty label');
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function a11yLabel(string $a11yLabel): static
    {
        $this->chartProps['a11y_label'] = $this->requiredText($a11yLabel, 'accessibility label');
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function locale(string $locale): static
    {
        $locale = str_replace('_', '-', trim($locale));
        if (
            $locale !== ''
            && preg_match(
                '/^(?:[a-zA-Z]{2,8}(?:-[a-zA-Z0-9]{2,8})*(?:-[0-9a-wy-zA-WY-Z](?:-[a-zA-Z0-9]{2,8})+)*(?:-x(?:-[a-zA-Z0-9]{1,8})+)?|x(?:-[a-zA-Z0-9]{1,8})+)$/',
                $locale,
            ) !== 1
        ) {
            throw new InvalidArgumentException("The {$this->chartName()} locale must be a valid BCP-47 locale tag.");
        }

        $this->chartProps['locale'] = $locale;
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function valueFormat(string $valueFormat): static
    {
        if (! in_array($valueFormat, ['number', 'currency', 'percent'], true)) {
            throw new InvalidArgumentException("The {$this->chartName()} value format must be number, currency, or percent.");
        }

        $this->chartProps['value_format'] = $valueFormat;
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function currencyCode(string $currencyCode): static
    {
        $currencyCode = strtoupper(trim($currencyCode));
        if ($currencyCode !== '' && preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            throw new InvalidArgumentException("The {$this->chartName()} currency code must be a three-letter code.");
        }

        $this->chartProps['currency_code'] = $currencyCode;
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function minimumFractionDigits(int $digits): static
    {
        $this->chartProps['minimum_fraction_digits'] = $this->fractionDigits($digits, 'minimum fraction digits');
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    public function maximumFractionDigits(int $digits): static
    {
        $this->chartProps['maximum_fraction_digits'] = $this->fractionDigits($digits, 'maximum fraction digits');
        $this->invalidateCommonWireSnapshot();

        return $this;
    }

    /** @param array<string, mixed> $attrs */
    protected function applyCommonAttributes(array $attrs): void
    {
        $this->applyBooleanAttributes($attrs, ['animated'], 'animated');
        $this->applyStringAttributes($attrs, ['empty-label', 'emptyLabel'], 'emptyLabel');
        $this->applyStringAttributes($attrs, ['a11y-label', 'a11yLabel'], 'a11yLabel');
        $this->applyStringAttributes($attrs, ['locale'], 'locale');
        $this->applyStringAttributes($attrs, ['value-format', 'valueFormat'], 'valueFormat');
        $this->applyStringAttributes($attrs, ['currency-code', 'currencyCode'], 'currencyCode');
        $this->applyIntegerAttributes($attrs, ['minimum-fraction-digits', 'minimumFractionDigits'], 'minimumFractionDigits');
        $this->applyIntegerAttributes($attrs, ['maximum-fraction-digits', 'maximumFractionDigits'], 'maximumFractionDigits');
        $this->applyArrayAttributes($attrs, ['style'], 'style');
        $this->applyArrayAttributes($attrs, ['legend'], 'legend');
        $this->applyStringAttributes($attrs, ['_select', 'on-select', 'onSelect'], 'onSelect');
    }

    /** @return array<string, bool|int|string> */
    protected function resolveCommonProps(CallbackRegistry $registry): array
    {
        return [
            ...$this->commonWireSnapshot(),
            'on_select' => $this->selectMethod === null ? 0 : $registry->register($this->selectMethod),
        ];
    }

    protected function invalidateCommonWireSnapshot(): void
    {
        $this->commonWireSnapshot = null;
        $this->commonWireLegendItemCount = null;
    }

    /** @param array<string, mixed> $format */
    protected function syncFormattingProps(array $format): void
    {
        foreach (['value_format', 'currency_code', 'minimum_fraction_digits', 'maximum_fraction_digits'] as $key) {
            if (array_key_exists($key, $format)) {
                $this->chartProps[$key] = $format[$key];
            }
        }
    }

    protected function chartName(): string
    {
        return $this->chartType().' chart';
    }

    protected function legendItemCount(): int
    {
        return 0;
    }

    /** @param array<string, mixed> $attrs */
    protected function applyBooleanAttributes(array $attrs, array $attributes, string $method): void
    {
        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $attrs)) {
                $this->{$method}($this->boolean($attrs[$attribute], $attribute));
            }
        }
    }

    /** @param array<string, mixed> $attrs */
    protected function applyStringAttributes(array $attrs, array $attributes, string $method): void
    {
        foreach ($attributes as $attribute) {
            if (! array_key_exists($attribute, $attrs)) {
                continue;
            }

            if (! is_string($attrs[$attribute])) {
                throw new InvalidArgumentException("The {$this->chartName()} {$attribute} attribute must be a string.");
            }

            $this->{$method}($attrs[$attribute]);
        }
    }

    /** @param array<string, mixed> $attrs */
    protected function applyIntegerAttributes(array $attrs, array $attributes, string $method): void
    {
        foreach ($attributes as $attribute) {
            if (! array_key_exists($attribute, $attrs)) {
                continue;
            }

            if (! is_int($attrs[$attribute])) {
                throw new InvalidArgumentException("The {$this->chartName()} {$attribute} attribute must be an integer.");
            }

            $this->{$method}($attrs[$attribute]);
        }
    }

    /** @param array<string, mixed> $attrs */
    protected function applyArrayAttributes(array $attrs, array $attributes, string $method): void
    {
        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $attrs)) {
                $this->arrayAttribute($attrs[$attribute], $attribute, fn (array $value) => $this->{$method}($value));
            }
        }
    }

    protected function arrayAttribute(mixed $value, string $attribute, callable $apply): void
    {
        if (! is_array($value)) {
            throw new InvalidArgumentException("The {$this->chartName()} {$attribute} attribute must be an array.");
        }

        $apply($value);
    }

    private function boolean(mixed $value, string $attribute): bool
    {
        return match ($value) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => throw new InvalidArgumentException("The {$this->chartName()} {$attribute} attribute must be a boolean."),
        };
    }

    private function requiredText(mixed $value, string $context): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("The {$this->chartName()} {$context} must be a non-empty string.");
        }

        return trim($value);
    }

    private function fractionDigits(int $digits, string $property): int
    {
        if ($digits < 0 || $digits > 8) {
            throw new InvalidArgumentException("The {$this->chartName()} {$property} must be between 0 and 8.");
        }

        return $digits;
    }

    /** @return array<string, int|string> */
    private function formattingProps(): array
    {
        return [
            'value_format' => $this->chartProps['value_format'],
            'currency_code' => $this->chartProps['currency_code'],
            'minimum_fraction_digits' => $this->chartProps['minimum_fraction_digits'],
            'maximum_fraction_digits' => $this->chartProps['maximum_fraction_digits'],
        ];
    }

    /** @return array<string, bool|int|string> */
    private function commonWireSnapshot(): array
    {
        $legendItemCount = $this->legendItemCount();
        if ($this->commonWireSnapshot !== null && $this->commonWireLegendItemCount === $legendItemCount) {
            return $this->commonWireSnapshot;
        }

        $format = AxisNormalizer::y($this->formattingProps(), $this->chartName());
        $this->syncFormattingProps($format);
        $legend = LegendNormalizer::normalize($this->legend, $legendItemCount, $this->chartName());
        $this->commonWireLegendItemCount = $legendItemCount;

        return $this->commonWireSnapshot = [
            ...$this->chartProps,
            'contract_version' => 1,
            'style_json' => WireEncoder::encode($this->chartStyle, $this->chartName(), emptyAsObject: true),
            'legend_json' => WireEncoder::encode($legend, $this->chartName()),
        ];
    }
}
