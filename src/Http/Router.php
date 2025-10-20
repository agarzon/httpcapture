<?php

declare(strict_types=1);

namespace HttpCapture\Http;

final class Router
{
    /**
     * @var array<int, array{method: string, pattern: string, regex: string, variables: array<int, string>, handler: callable}>
     */
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        [$regex, $variables] = $this->compilePattern($pattern);

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'regex' => $regex,
            'variables' => $variables,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): ?Response
    {
        $method = $request->getMethod();
        $path = $request->getPath();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($route['variables'] as $index => $name) {
                $params[$name] = $matches[$index + 1] ?? null;
            }

            $handler = $route['handler'];
            $result = $handler($request, $params);

            if ($result instanceof Response) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function compilePattern(string $pattern): array
    {
        $variables = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_-]*)\}/', static function (array $matches) use (&$variables): string {
            $variables[] = $matches[1];

            return '([A-Za-z0-9_-]+)';
        }, $pattern);

        $regex = $regex ?? $pattern;
        $regex = '#^' . $regex . '$#';

        return [$regex, $variables];
    }
}
