<?php
declare(strict_types=1);

/**
 * AjaxWrapper
 * ------------
 * A **tiny, framework-agnostic** helper for progressive enhancement:
 *   - Works with or without JavaScript.
 *   - With JS → returns JSON ({ ok, errors, html, payload }).
 *   - Without JS → classic POST-Redirect-GET flow (PRG).
 *
 * Zero dependencies.
 * No interfaces or inheritance needed.
 * Works with closures, instance methods, static methods, or legacy echo-based code.
 *
 * Typical use:
 *   $wrapper = new AjaxWrapper();
 *   $wrapper->handle([$controller,'handlePost'], [$controller,'renderPage']);
 *
 * Return format of your POST handler (flexible):
 *   - Recommended: ['ok'=>bool, 'errors'=>array, 'html'=>string|null, 'payload'=>array]
 *   - Or just return a bool (true = success, false = error)
 *
 * Legacy helpers:
 *   - capture(fn): string          → capture echoed output
 *   - swallow(fn): void            → discard echoed output
 *   - renderGetFromEcho(fn): fn    → wrap a GET renderer that echoes HTML
 *   - processPostFromEcho(fn, ...) → wrap a POST handler that echoes HTML
 *
 * Notes:
 *   - Add CSRF protection in production.
 *   - JSON detection: Accept: application/json or X-Requested-With: fetch|XMLHttpRequest.
 *   - PRG prevents duplicate form submissions on reload.
 */
final class AjaxWrapper
{
    /**
     * Main entry point: decides between POST/GET and JSON/HTML response.
     *
     * @param callable $processPost fn(): mixed
     *   Your POST logic. Can return any structure:
     *   - Recommended: ['ok'=>bool,'errors'=>array,'html'=>string|null,'payload'=>array]
     *   - Or simply return true/false.
     *
     * @param callable $renderGet fn(?mixed $lastResult): string
     *   Renders your GET page as HTML (string). The $lastResult argument is optional.
     */
    public function handle(callable $processPost, callable $renderGet): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $result = $processPost();

            if ($this->wantsJson()) {
                // Map arbitrary result → JSON structure
                $ok      = is_array($result) ? !empty($result['ok']) : (bool)$result;
                $errors  = is_array($result) ? ($result['errors'] ?? []) : [];
                $html    = is_array($result) ? ($result['html'] ?? null) : null;
                $payload = is_array($result) ? ($result['payload'] ?? []) : [];

                $this->json(
                    ['ok'=>$ok, 'errors'=>$errors, 'html'=>$html, 'payload'=>$payload],
                    $ok ? 200 : 422
                );
            }

            // Non-AJAX: perform PRG (POST → Redirect → GET)
            header('Location: ' . $this->selfUrl(), true, 303);
            exit;
        }

        // GET: render normal HTML page
        $this->html($renderGet(null));
    }

    /* ===========================
       Legacy / Echo helpers
       =========================== */

    /** Capture all echoed output of a callable and return it as a string. */
    public static function capture(callable $fn): string
    {
        ob_start();
        try {
            $fn();
            return (string)ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /** Execute a callable and discard any echoed output. */
    public static function swallow(callable $fn): void
    {
        ob_start();
        try {
            $fn();
            ob_end_clean();
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Wrap a legacy GET renderer that echoes HTML.
     * Example:
     *   $renderGet = AjaxWrapper::renderGetFromEcho([$legacy,'render']);
     */
    public static function renderGetFromEcho(callable $echoingRenderer): callable
    {
        return function (?mixed $lastResult = null) use ($echoingRenderer): string {
            return self::capture(static fn() => $echoingRenderer($lastResult));
        };
    }

    /**
     * Wrap a legacy POST handler that echoes HTML.
     * - Captured output can optionally become the success HTML.
     * - You can provide detectors for success/error extraction.
     */
    public static function processPostFromEcho(
        callable $echoingPostHandler,
        bool $useCapturedAsSuccessHtml = true,
        ?callable $successDetector = null, // fn(): bool
        ?callable $errorExtractor  = null  // fn(): array
    ): callable {
        return function () use ($echoingPostHandler, $useCapturedAsSuccessHtml, $successDetector, $errorExtractor): array {
            $out = self::capture($echoingPostHandler);

            $ok = $successDetector ? (bool)$successDetector() : ($out !== '');
            $errors = $ok ? [] : (array)($errorExtractor ? $errorExtractor() : []);

            return [
                'ok'      => $ok,
                'errors'  => $errors,
                'html'    => $ok && $useCapturedAsSuccessHtml ? $out : null,
                'payload' => [],
            ];
        };
    }

    /* ===========================
       Internal utilities
       =========================== */

    /** Detects if client expects JSON */
    private function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return str_contains($accept, 'application/json') || in_array($xhr, ['fetch', 'xmlhttprequest'], true);
    }

    /** Returns current URL (without query) for PRG redirect */
    private function selfUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri    = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
        return $scheme . '://' . $host . $uri;
    }

    /** Sends JSON response and terminates */
    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Sends HTML response and terminates */
    private function html(string $html, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }
}
