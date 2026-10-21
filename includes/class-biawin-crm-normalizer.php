<?php

if (!defined('ABSPATH')) {
    exit;
}

class Biawin_CRM_Normalizer
{
    public static function phone(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
        $latin = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
        $latin_value = str_replace($persian, $latin, $value);
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?[eE][+\-]?[0-9]+$/', $latin_value) && is_numeric($latin_value)) {
            $latin_value = sprintf('%.0f', (float)$latin_value);
        }
        $digits = preg_replace('/\D+/', '', $latin_value);
        if (strpos($digits, '0098') === 0) {
            $digits = '0' . substr($digits, 4);
        } elseif (strpos($digits, '98') === 0 && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (strlen($digits) === 10 && strpos($digits, '9') === 0) {
            $digits = '0' . $digits;
        }
        return (preg_match('/^09\d{9}$/', $digits) === 1) ? $digits : '';
    }

    public static function name(?string $value): string
    {
        $value = trim((string) $value);
        $value = str_replace(['ي', 'ك', 'ۀ'], ['ی', 'ک', 'ه'], $value);
        return preg_replace('/\s+/u', ' ', mb_strtolower($value));
    }

    public static function money($value): float
    {
        $value = str_replace([',', '٬', '،', ' '], '', (string) $value);
        $value = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $value);
        return is_numeric($value) ? (float) $value : 0.0;
    }

    /**
     * v0.8.62.21: تاریخ ورودی فرم‌های Workflow را به میلادی «Y-m-d» برمی‌گرداند.
     * ورودی شمسی (1404/07/05) → میلادی؛ ورودی میلادی (2026-09-27) → همان؛ نامعتبر → ''. 
     * (پرتال از v0.7.5 تقویم شمسی دارد ولی فرم‌های Workflow سه ورودی type=date میلادی داشتند؛
     * حالا هر دو فرمت پذیرفته می‌شود تا هم فلش‌بک و هم APIها نشکنند.)
     */
    public static function date($value): string
    {
        $value = trim((string) $value);
        if ($value === '') return '';
        $value = str_replace(['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'], ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'], $value);
        if (preg_match('/^(1[34]\d{2})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $m)) {
            [$jy, $jm, $jd] = [(int)$m[1], (int)$m[2], (int)$m[3]];
            if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > 31) return '';
            [$gy, $gm, $gd] = self::jalali_to_gregorian($jy, $jm, $jd);
            return checkdate($gm, $gd, $gy) ? sprintf('%04d-%02d-%02d', $gy, $gm, $gd) : '';
        }
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $value, $m)) {
            [$gy, $gm, $gd] = [(int)$m[1], (int)$m[2], (int)$m[3]];
            return checkdate($gm, $gd, $gy) ? sprintf('%04d-%02d-%02d', $gy, $gm, $gd) : '';
        }
        $ts = strtotime($value);
        return $ts ? wp_date('Y-m-d', $ts) : '';
    }

    /** v0.8.62.21: تبدیل تاریخ شمسی به میلادی (همان الگوریتم shared با REST/فرانت). */
    public static function jalali_to_gregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097); $days %= 146097;
        if ($days > 36524) { $gy += 100 * intdiv(--$days, 36524); $days %= 36524; if ($days >= 365) $days++; }
        $gy += 4 * intdiv($days, 1461); $days %= 1461;
        if ($days > 365) { $gy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
        $gd = $days + 1;
        $sal = [0,31,(($gy%4===0 && $gy%100!==0)||$gy%400===0)?29:28,31,30,31,30,31,31,30,31,30,31];
        $gm = 1;
        while ($gm <= 12 && $gd > $sal[$gm]) { $gd -= $sal[$gm]; $gm++; }
        return [$gy, $gm, $gd];
    }
}
