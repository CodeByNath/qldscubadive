<?php

namespace QSD\Platform\Modules\Settings\Connectors;

use QSD\Platform\Modules\Settings\Connections\ConnectorCredentials;
use QSD\Platform\Modules\Settings\Security\BrokeredProviderOperation;
use QSD\Platform\Modules\Settings\Security\ProviderSecrets;

/**
 * RezdyConnectionCheck — the brokered `connection.verify` operation: one
 * read-only request that answers "does the stored API key authenticate?".
 *
 * It asks for a single product (`GET products?limit=1`) and reads only the HTTP
 * status and Rezdy's `requestStatus.success` flag; the body is discarded, so
 * nothing is imported or mapped. The result is safe metadata only — outcome,
 * HTTP status, latency — never the key, the request URL, the body, or an
 * upstream error message.
 *
 * Security Phase 2 bound: the check runs only against the Rezdy staging API.
 * With the environment set to anything else it refuses without a request.
 */
final class RezdyConnectionCheck implements BrokeredProviderOperation
{
    public const ALLOWED_ENVIRONMENT = 'staging';

    public const AUTHENTICATED       = 'authenticated';
    public const UNAUTHORIZED        = 'unauthorized';
    public const RATE_LIMITED        = 'rate_limited';
    public const UPSTREAM_ERROR      = 'upstream_error';
    public const UNEXPECTED_RESPONSE = 'unexpected_response';
    public const NETWORK_ERROR       = 'network_error';
    public const REFUSED_ENVIRONMENT = 'refused_environment';
    public const NOT_CONFIGURED      = 'not_configured';

    private const TIMEOUT = 10;

    /** @var \Closure(string, array<string, mixed>): array{status: ?int, body: ?string} */
    private \Closure $transport;

    /** @var \Closure(): float */
    private \Closure $timer;

    /**
     * @param (\Closure(string, array<string, mixed>): array{status: ?int, body: ?string})|null $transport
     * @param (\Closure(): float)|null $timer
     */
    public function __construct(private ConnectorCredentials $credentials, ?\Closure $transport = null, ?\Closure $timer = null)
    {
        $this->transport = $transport ?? static function (string $url, array $args): array {
            $response = wp_remote_get($url, $args);
            if (is_wp_error($response)) {
                return ['status' => null, 'body' => null];
            }
            return ['status' => (int) wp_remote_retrieve_response_code($response), 'body' => (string) wp_remote_retrieve_body($response)];
        };
        $this->timer = $timer ?? static fn(): float => microtime(true);
    }

    public function perform(string $scope, ProviderSecrets $secrets, array $params): array
    {
        if ($scope !== RezdyConnector::SCOPE_VERIFY) {
            throw new \InvalidArgumentException('Unsupported Rezdy scope.');
        }

        $environment = $this->credentials->config(RezdyConnector::PROVIDER, 'environment');
        $result = ['provider' => RezdyConnector::PROVIDER, 'environment' => $environment, 'outcome' => null, 'http_status' => null, 'latency_ms' => null];

        if ($environment !== self::ALLOWED_ENVIRONMENT) {
            return ['outcome' => self::REFUSED_ENVIRONMENT] + $result;
        }
        $apiKey = $secrets->get('api_key');
        if ($apiKey === null) {
            return ['outcome' => self::NOT_CONFIGURED] + $result;
        }

        $url = RezdyConnector::BASE_URLS[self::ALLOWED_ENVIRONMENT] . 'products?' . http_build_query(['limit' => 1, 'apiKey' => $apiKey]);
        $started = ($this->timer)();
        $response = ($this->transport)($url, [
            'timeout'     => self::TIMEOUT,
            'redirection' => 0,
            'headers'     => ['Accept' => 'application/json'],
        ]);
        $result['latency_ms'] = (int) round((($this->timer)() - $started) * 1000);
        $result['http_status'] = $response['status'];
        $result['outcome'] = self::classify($response['status'], $response['body']);
        return $result;
    }

    private static function classify(?int $status, ?string $body): string
    {
        if ($status === null) {
            return self::NETWORK_ERROR;
        }
        $decoded = is_string($body) ? json_decode($body, true) : null;
        $requestStatus = is_array($decoded) && is_array($decoded['requestStatus'] ?? null) ? $decoded['requestStatus'] : [];
        $errorCode = (string) ($requestStatus['error']['errorCode'] ?? '');

        return match (true) {
            $status === 401, $status === 403, in_array($errorCode, ['401', '403'], true) => self::UNAUTHORIZED,
            $status === 429                                                             => self::RATE_LIMITED,
            $status >= 500                                                              => self::UPSTREAM_ERROR,
            $status === 200 && ($requestStatus['success'] ?? null) === true             => self::AUTHENTICATED,
            default                                                                     => self::UNEXPECTED_RESPONSE,
        };
    }
}
