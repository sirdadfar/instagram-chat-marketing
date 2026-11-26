<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Small presentation helper for the Persian (RTL) panel UI:
 * Persian digits, relative times and human labels for raw API values.
 */
final class PersianText
{
    private const DIGITS = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ];

    private const EVENT_LABELS = [
        'message.received' => 'پیام دریافتی',
        'message.sent' => 'پیام ارسالی',
        'message.read' => 'خواندن پیام',
        'message.updated' => 'به‌روزرسانی پیام',
        'message.deleted' => 'حذف پیام',
        'reaction.received' => 'واکنش دریافتی',
        'comment.received' => 'نظر دریافتی',
        'comment.created' => 'نظر جدید',
        'comment.deleted' => 'حذف نظر',
        'comment.hidden' => 'پنهان‌شدن نظر',
        'story.reply' => 'پاسخ استوری',
        'story.insights' => 'آمار استوری',
        'mention.received' => 'منشن جدید',
        'follow.received' => 'دنبال‌کردن جدید',
        'unfollow.received' => 'لغو دنبال‌کردن',
    ];

    private const STATUS_LABELS = [
        'active' => 'فعال',
        'paused' => 'متوقف',
        'draft' => 'پیش‌نویس',
        'pending' => 'در انتظار',
        'processed' => 'پردازش‌شده',
        'ignored' => 'نادیده‌گرفته‌شده',
        'failed' => 'ناموفق',
        'success' => 'موفق',
        'executed' => 'موفق',
        'gated' => 'منتظر تأیید فالو',
        'not_matched' => 'شرایط برقرار نبود',
        'blocked' => 'محدود شده',
        'matched' => 'منطبق',
    ];

    private const COUNTRIES = [
        'IR' => 'ایران', 'MM' => 'میانمار', 'IT' => 'ایتالیا', 'CN' => 'چین', 'SY' => 'سوریه',
        'TR' => 'ترکیه', 'US' => 'آمریکا', 'GB' => 'بریتانیا', 'DE' => 'آلمان', 'FR' => 'فرانسه',
        'AE' => 'امارات', 'AF' => 'افغانستان', 'IQ' => 'عراق', 'PK' => 'پاکستان', 'IN' => 'هند',
        'RU' => 'روسیه', 'SE' => 'سوئد', 'NL' => 'هلند', 'ES' => 'اسپانیا', 'CA' => 'کانادا',
        'AU' => 'استرالیا', 'AZ' => 'آذربایجان', 'AM' => 'ارمنستان', 'GE' => 'گرجستان',
        'SA' => 'عربستان', 'QA' => 'قطر', 'KW' => 'کویت', 'OM' => 'عمان', 'BH' => 'بحرین',
        'MY' => 'مالزی', 'ID' => 'اندونزی', 'JP' => 'ژاپن', 'KR' => 'کره جنوبی', 'BR' => 'برزیل',
    ];

    private const COUNTRY_NAMES = [
        'iran' => 'ایران',
        'turkey' => 'ترکیه',
        'italy' => 'ایتالیا',
        'china' => 'چین',
        'syria' => 'سوریه',
        'myanmar' => 'میانمار',
        'united states' => 'آمریکا',
        'united states of america' => 'آمریکا',
        'united kingdom' => 'بریتانیا',
        'germany' => 'آلمان',
        'france' => 'فرانسه',
        'iraq' => 'عراق',
        'afghanistan' => 'افغانستان',
        'pakistan' => 'پاکستان',
        'india' => 'هند',
        'canada' => 'کانادا',
        'australia' => 'استرالیا',
    ];

    private const PROVINCES = [
        'alborz' => 'البرز',
        'ardabil' => 'اردبیل',
        'bushehr' => 'بوشهر',
        'chahar mahall and bakhtiari' => 'چهارمحال و بختیاری',
        'chaharmahal and bakhtiari' => 'چهارمحال و بختیاری',
        'east azerbaijan' => 'آذربایجان شرقی',
        'azerbaijan east' => 'آذربایجان شرقی',
        'west azerbaijan' => 'آذربایجان غربی',
        'azerbaijan west' => 'آذربایجان غربی',
        'fars' => 'فارس',
        'gilan' => 'گیلان',
        'golestan' => 'گلستان',
        'hamadan' => 'همدان',
        'hormozgan' => 'هرمزگان',
        'ilam' => 'ایلام',
        'isfahan' => 'اصفهان',
        'kerman' => 'کرمان',
        'kermanshah' => 'کرمانشاه',
        'khuzestan' => 'خوزستان',
        'kohgiluyeh and boyer-ahmad' => 'کهگیلویه و بویراحمد',
        'kohgiluyeh and boyerahmad' => 'کهگیلویه و بویراحمد',
        'kurdistan' => 'کردستان',
        'lorestan' => 'لرستان',
        'markazi' => 'مرکزی',
        'mazandaran' => 'مازندران',
        'north khorasan' => 'خراسان شمالی',
        'khorasan north' => 'خراسان شمالی',
        'razavi khorasan' => 'خراسان رضوی',
        'khorasan razavi' => 'خراسان رضوی',
        'south khorasan' => 'خراسان جنوبی',
        'khorasan south' => 'خراسان جنوبی',
        'qazvin' => 'قزوین',
        'qom' => 'قم',
        'semnan' => 'سمنان',
        'sistan and baluchestan' => 'سیستان و بلوچستان',
        'sistan and baluchistan' => 'سیستان و بلوچستان',
        'tehran' => 'تهران',
        'yazd' => 'یزد',
        'zanjan' => 'زنجان',
    ];

    public static function digits(string|int|float|null $value): string
    {
        return strtr((string) $value, self::DIGITS);
    }

    public static function number(float|int|string|null $value, int $decimals = 0): string
    {
        return self::digits(number_format((float) $value, $decimals, '.', '٬'));
    }

    public static function date(?CarbonInterface $date, string $format = 'Y/m/d'): string
    {
        if (! $date) {
            return '—';
        }

        return self::digits($date->format($format));
    }

    public static function dateTime(?CarbonInterface $date): string
    {
        return self::date($date, 'Y/m/d H:i');
    }

    /**
     * Human readable Persian distance, e.g. «۵ دقیقه پیش».
     */
    public static function relative(?CarbonInterface $date): string
    {
        if (! $date) {
            return '—';
        }

        $seconds = now()->getTimestamp() - $date->getTimestamp();

        if ($seconds < 0) {
            return self::date($date);
        }

        if ($seconds < 45) {
            return 'لحظاتی پیش';
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return self::digits(max(1, $minutes)).' دقیقه پیش';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return self::digits($hours).' ساعت پیش';
        }

        $days = intdiv($hours, 24);

        if ($days < 30) {
            return self::digits($days).' روز پیش';
        }

        return self::date($date);
    }

    public static function eventLabel(?string $type): string
    {
        if (! $type) {
            return 'رویداد';
        }

        return self::EVENT_LABELS[strtolower($type)] ?? $type;
    }

    public static function statusLabel(?string $status): string
    {
        if (! $status) {
            return '—';
        }

        return self::STATUS_LABELS[strtolower($status)] ?? $status;
    }

    /**
     * Translate a demographic dimension value (country / city / age / gender).
     */
    public static function dimension(string $key, mixed $value): string
    {
        $raw = is_scalar($value) ? trim((string) $value) : '—';

        if ($raw === '') {
            return '—';
        }

        return match ($key) {
            'country' => self::COUNTRIES[strtoupper($raw)] ?? self::COUNTRY_NAMES[mb_strtolower($raw)] ?? $raw,
            'city' => self::city($raw),
            'gender' => match (strtoupper($raw)) {
                'F', 'FEMALE' => 'زن',
                'M', 'MALE' => 'مرد',
                'U', 'UNKNOWN' => 'نامشخص',
                default => $raw,
            },
            'age' => str_contains($raw, '-')
                ? implode(' تا ', array_map(fn ($part) => self::digits(trim($part)), explode('-', $raw, 2)))
                : self::digits($raw),
            default => $raw,
        };
    }

    /**
     * Demographics "city" rows arrive as «City, Province»; translate the
     * province part when we know it and keep the city readable.
     */
    private static function city(string $raw): string
    {
        if (! preg_match('/[,،]/u', $raw)) {
            return self::PROVINCES[mb_strtolower($raw)] ?? $raw;
        }

        $parts = preg_split('/[,،]/u', $raw, 2) ?: [$raw, ''];
        [$city, $province] = array_map('trim', $parts);
        $province = preg_replace('/\s+province$/iu', '', $province) ?? $province;
        $provinceFa = self::PROVINCES[mb_strtolower($province)] ?? $province;

        if ($provinceFa === $province) {
            return $raw;
        }

        return $city.'، '.$provinceFa;
    }
}
