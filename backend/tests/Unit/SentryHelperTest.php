<?php

declare(strict_types=1);

use App\Support\Sentry\SentryHelper;
use Sentry\Breadcrumb;
use Sentry\State\HubInterface;
use Sentry\State\Scope;

/**
 * Wave 5 — Sentry hot-path observability test.
 *
 * 驗證點：
 *   1. captureException 帶 module tag + scrubbed context
 *   2. captureMessage 一樣
 *   3. addBreadcrumb 走 SDK
 *   4. PII / health-route 被 [Filtered] / [health-route] 取代
 *   5. SDK 不在時 noop（不會 throw）
 */
beforeEach(function () {
    // 用 fake hub 攔截 capture call
    $this->hub = new class implements HubInterface
    {
        public array $exceptions = [];
        public array $messages = [];
        public array $breadcrumbs = [];
        public ?Scope $currentScope = null;

        public function getClient(): ?\Sentry\ClientInterface
        {
            return null;
        }

        public function getLastEventId(): ?\Sentry\EventId
        {
            return null;
        }

        public function pushScope(): Scope
        {
            return new Scope();
        }

        public function popScope(): bool
        {
            return true;
        }

        public function withScope(callable $callback): mixed
        {
            $scope = new Scope();
            $this->currentScope = $scope;
            try {
                return $callback($scope);
            } finally {
                $this->currentScope = null;
            }
        }

        private function snapshotScope(): array
        {
            if ($this->currentScope === null) {
                return ['tags' => [], 'contexts' => []];
            }
            $applied = $this->currentScope->applyToEvent(\Sentry\Event::createEvent(), null);
            return [
                'tags' => $applied?->getTags() ?? [],
                'contexts' => $applied?->getContexts() ?? [],
            ];
        }

        public function configureScope(callable $callback): void
        {
            $callback(new Scope());
        }

        public function bindClient(\Sentry\ClientInterface $client): void {}

        public function captureMessage(string $message, ?\Sentry\Severity $level = null, ?\Sentry\EventHint $hint = null): ?\Sentry\EventId
        {
            $snap = $this->snapshotScope();
            $this->messages[] = [
                'message' => $message,
                'level' => $level?->__toString(),
                'tags' => $snap['tags'],
                'contexts' => $snap['contexts'],
            ];

            return null;
        }

        public function captureException(\Throwable $exception, ?\Sentry\EventHint $hint = null): ?\Sentry\EventId
        {
            $snap = $this->snapshotScope();
            $this->exceptions[] = [
                'message' => $exception->getMessage(),
                'class' => $exception::class,
                'tags' => $snap['tags'],
                'contexts' => $snap['contexts'],
            ];

            return null;
        }

        public function captureEvent(\Sentry\Event|array $event, ?\Sentry\EventHint $hint = null): ?\Sentry\EventId
        {
            return null;
        }

        public function captureLastError(?\Sentry\EventHint $hint = null): ?\Sentry\EventId
        {
            return null;
        }

        public function captureCheckIn(string $slug, \Sentry\CheckInStatus $status, $duration = null, ?\Sentry\MonitorConfig $monitorConfig = null, ?string $checkInId = null): ?string
        {
            return null;
        }

        public function addBreadcrumb(Breadcrumb $breadcrumb): bool
        {
            $this->breadcrumbs[] = [
                'category' => $breadcrumb->getCategory(),
                'message' => $breadcrumb->getMessage(),
                'metadata' => $breadcrumb->getMetadata(),
            ];

            return true;
        }

        public function getIntegration(string $className): ?\Sentry\Integration\IntegrationInterface
        {
            return null;
        }

        public function startTransaction(\Sentry\Tracing\TransactionContext $context, array $customSamplingContext = []): \Sentry\Tracing\Transaction
        {
            return new \Sentry\Tracing\Transaction($context);
        }

        public function getTransaction(): ?\Sentry\Tracing\Transaction
        {
            return null;
        }

        public function getSpan(): ?\Sentry\Tracing\Span
        {
            return null;
        }

        public function setSpan(?\Sentry\Tracing\Span $span): HubInterface
        {
            return $this;
        }
    };

    \Sentry\SentrySdk::setCurrentHub($this->hub);
});

it('captureException tags module and scrubs PII / health context', function () {
    SentryHelper::captureException(new \RuntimeException('boom'), 'iap', [
        'platform' => 'apple',
        'product_id' => 'calendar.premium.monthly',
        'email' => 'leak@example.com', // must be filtered
        'user_phone' => '+886-912-345-678', // key contains 'phone' → filtered
        'cycle_length' => 28, // health → filtered
        'mood' => 'cramping', // health → filtered
        'callback_url' => 'https://api.example.com/v1/cycles/123', // health-route URL
    ]);

    expect($this->hub->exceptions)->toHaveCount(1);
    $captured = $this->hub->exceptions[0];

    expect($captured['tags']['module'] ?? null)->toBe('iap');

    $ctx = $captured['contexts']['module_data'] ?? [];
    expect($ctx['platform'])->toBe('apple');
    expect($ctx['product_id'])->toBe('calendar.premium.monthly');
    expect($ctx['email'])->toBe('[Filtered]');
    expect($ctx['user_phone'])->toBe('[Filtered]');
    expect($ctx['cycle_length'])->toBe('[Filtered]');
    expect($ctx['mood'])->toBe('[Filtered]');
    expect($ctx['callback_url'])->toBe('[health-route]');
});

it('captureMessage attaches module tag and severity', function () {
    SentryHelper::captureMessage('webhook hmac mismatch', 'warning', 'webhook.identity', [
        'stage' => 'hmac',
        'event_id' => 'evt_123',
    ]);

    expect($this->hub->messages)->toHaveCount(1);
    $m = $this->hub->messages[0];
    expect($m['message'])->toBe('webhook hmac mismatch');
    expect($m['tags']['module'] ?? null)->toBe('webhook.identity');
    expect($m['contexts']['module_data']['stage'] ?? null)->toBe('hmac');
});

it('addBreadcrumb scrubs sensitive metadata', function () {
    SentryHelper::addBreadcrumb('llm.fail', 'openai 503', [
        'provider' => 'openai',
        'symptom_count' => 3, // 'symptom' → filtered
        'access_token' => 'sk-XXX', // filtered
    ]);

    expect($this->hub->breadcrumbs)->toHaveCount(1);
    $b = $this->hub->breadcrumbs[0];
    expect($b['category'])->toBe('llm.fail');
    expect($b['metadata']['provider'])->toBe('openai');
    expect($b['metadata']['symptom_count'])->toBe('[Filtered]');
    expect($b['metadata']['access_token'])->toBe('[Filtered]');
});

it('nested array scrub goes deep', function () {
    SentryHelper::captureException(new \RuntimeException('x'), 'subscription', [
        'meta' => [
            'inner' => [
                'cycle_start' => '2026-04-01', // filtered
                'product_id' => 'monthly', // ok
            ],
        ],
    ]);

    $ctx = $this->hub->exceptions[0]['contexts']['module_data'] ?? [];
    expect($ctx['meta']['inner']['cycle_start'])->toBe('[Filtered]');
    expect($ctx['meta']['inner']['product_id'])->toBe('monthly');
});
