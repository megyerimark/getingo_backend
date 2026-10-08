<?php

$trustedProxies = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('TRUSTED_PROXIES', ''))
)));

$trustedHosts = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env(
        'TRUSTED_HOSTS',
        '^localhost$,^127\\.0\\.0\\.1$,^(.+\\.)?getingo\\.hu$'
    ))
)));

return [
    'audit_log_retention_days' => (int) env('AUDIT_LOG_RETENTION_DAYS', 90),
    'max_request_bytes' => (int) env('MAX_REQUEST_BYTES', 1048576),
    'trusted_proxies' => $trustedProxies,
    'trusted_hosts' => $trustedHosts,
];
