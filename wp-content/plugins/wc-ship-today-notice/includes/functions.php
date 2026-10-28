<?php
/**
 * Public API for other plugins and the theme.
 *
 * Dates as Y-m-d in the shop's time zone.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Next slots whose cutoff time has not passed yet.
 *
 * @param int                    $count Number of slots.
 * @param DateTimeInterface|null $now   A moment other than "now" (tests).
 * @return array<int,array{cutoff:DateTimeImmutable,ship:DateTimeImmutable,delivery:?DateTimeImmutable}>
 */
function wc_stn_get_slots($count = 1, $now = null)
{
    if ($now instanceof DateTime) {
        $now = DateTimeImmutable::createFromMutable($now); // PHP 7.4 has no createFromInterface().
    }

    return WC_Ship_Today_Notice::instance()->schedule()->slots((int) $count, $now);
}

/**
 * Delivery date for a shipping date (e.g. one picked in the checkout calendar).
 *
 * @param string $ship_ymd Shipping date Y-m-d (Ymd is accepted too).
 * @return string|null Y-m-d, or null when delivery is disabled in the settings.
 */
function wc_stn_delivery_for_ship_date($ship_ymd)
{
    $ship_ymd = (string) $ship_ymd;

    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $ship_ymd, $m)) {
        $ship_ymd = $m[1] . '-' . $m[2] . '-' . $m[3];
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ship_ymd)) {
        return null;
    }

    $schedule = WC_Ship_Today_Notice::instance()->schedule();
    $delivery = $schedule->delivery_for($schedule->date($ship_ymd));

    return $delivery ? $delivery->format('Y-m-d') : null;
}

/**
 * Whether there is no shipping on the given day because of a holiday or an excluded date (regardless of weekday).
 */
function wc_stn_is_blocked_date($ymd)
{
    return WC_Ship_Today_Notice::instance()->schedule()->is_blocked((string) $ymd);
}

/**
 * Whether the given day is a shipping day (weekday from the settings, not a holiday, not an excluded date).
 */
function wc_stn_is_shipping_day($ymd)
{
    $schedule = WC_Ship_Today_Notice::instance()->schedule();

    return $schedule->is_shipping_day($schedule->date((string) $ymd));
}
