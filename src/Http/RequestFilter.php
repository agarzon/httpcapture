<?php

declare(strict_types=1);

namespace HttpCapture\Http;

final class RequestFilter
{
    /** @var array<int, callable(Request): bool> */
    private array $rules = [];

    public static function create(): self
    {
        return new self();
    }

    public static function default(): self
    {
        return (new self())
            ->ignorePath('/favicon.ico')
            ->ignorePath('/favicon.png')
            ->ignorePath('/favicon.svg')
            ->ignorePath('/apple-touch-icon.png')
            ->ignorePath('/apple-touch-icon-precomposed.png')
            ->ignorePath('/robots.txt');
    }

    public function ignorePath(string $path): self
    {
        $this->rules[] = static fn (Request $request): bool => $request->getPath() === $path;

        return $this;
    }

    public function ignorePathPrefix(string $prefix): self
    {
        $this->rules[] = static fn (Request $request): bool => str_starts_with($request->getPath(), $prefix);

        return $this;
    }

    public function ignoreExtensions(string ...$extensions): self
    {
        $normalized = array_filter(array_map(
            static fn (string $extension): string => ltrim(strtolower($extension), '.')
        , $extensions));

        if ($normalized === []) {
            return $this;
        }

        $this->rules[] = static function (Request $request) use ($normalized): bool {
            $path = strtolower($request->getPath());
            $dotPosition = strrpos($path, '.');

            if ($dotPosition === false) {
                return false;
            }

            $extension = substr($path, $dotPosition + 1);

            return in_array($extension, $normalized, true);
        };

        return $this;
    }

    public function add(callable $rule): self
    {
        $this->rules[] = $rule;

        return $this;
    }

    public function shouldCapture(Request $request): bool
    {
        foreach ($this->rules as $rule) {
            if ($rule($request) === true) {
                return false;
            }
        }

        return true;
    }
}
