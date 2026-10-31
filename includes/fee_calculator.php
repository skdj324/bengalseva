<?php
declare(strict_types=1);

/**
 * BengalSeva Customer Extra Fee Calculator
 * Calculates platform/convenience fee based on Admin Settings
 */
function getCustomerPlatformFee(float $subtotal, string $serviceType = 'order', ?PDO $pdo = null): array
{
    if ($pdo === null) {
        $pdo = Database::getConnection();
    }

    static $cachedSettings = null;
    if ($cachedSettings === null) {
        $cachedSettings = $pdo->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    $isEnabled = ($cachedSettings['platform_fee_enabled'] ?? '1') === '1';
    if (!$isEnabled) {
        return ['fee' => 0.0, 'label' => '', 'total' => $subtotal];
    }

    // Check service type applicability
    $apply = match ($serviceType) {
        'order'     => ($cachedSettings['fee_apply_orders'] ?? '1') === '1',
        'ride'      => ($cachedSettings['fee_apply_rides'] ?? '1') === '1',
        'booking'   => ($cachedSettings['fee_apply_bookings'] ?? '1') === '1',
        'recharge'  => ($cachedSettings['fee_apply_recharges'] ?? '1') === '1',
        default     => true
    };

    if (!$apply) {
        return ['fee' => 0.0, 'label' => '', 'total' => $subtotal];
    }

    $type = $cachedSettings['platform_fee_type'] ?? 'fixed';
    $rate = (float)($cachedSettings['platform_fee_amount'] ?? 5.0);
    $label = $cachedSettings['platform_fee_label'] ?? 'Platform Fee';

    $fee = ($type === 'percent') ? round($subtotal * ($rate / 100), 2) : $rate;
    $finalTotal = round($subtotal + $fee, 2);

    return [
        'fee'   => $fee,
        'label' => $label,
        'total' => $finalTotal
    ];
}