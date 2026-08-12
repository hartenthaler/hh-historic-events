<?php

declare(strict_types=1);

namespace Hartenthaler\WebtreesModules\History\HhHistoricEvents;

use Fisharebest\Webtrees\I18N;
use RuntimeException;

use function array_fill;
use function array_search;
use function array_slice;
use function array_unique;
use function basename;
use function checkdate;
use function date;
use function explode;
use function fclose;
use function fgetcsv;
use function file_get_contents;
use function file_put_contents;
use function fopen;
use function fputcsv;
use function fwrite;
use function glob;
use function implode;
use function is_dir;
use function is_file;
use function ksort;
use function mkdir;
use function pathinfo;
use function preg_match;
use function str_starts_with;
use function strcasecmp;
use function strtotime;
use function strtoupper;
use function stream_get_contents;
use function trim;
use function unlink;

final class CustomCsvFileManager
{
    private const MAX_FILE_BYTES = 1048576;
    private const MAX_ROWS = 10000;
    private const MAX_FIELD_BYTES = 16384;
    public const METADATA_FIELDS = [
        'TOPIC',
        'LANGUAGE',
        'REGION',
        'VERSION',
        'AUTHOR',
        'CONTACT',
        'LICENSE',
        'SOURCE',
        'DESCRIPTION',
    ];

    public function __construct(private readonly string $folder)
    {
    }

    /**
     * @return list<array{filename:string,topic:string,language:string,region:string}>
     */
    public function files(): array
    {
        $files = [];
        foreach (glob($this->folder . '*.csv') ?: [] as $file) {
            if (basename($file) === 'GermanChancellorsPresidents.csv') {
                continue;
            }
            try {
                $metadata = $this->read(basename($file))['metadata'];
            } catch (RuntimeException) {
                $metadata = [];
            }
            $files[] = [
                'filename' => basename($file),
                'topic' => $metadata['TOPIC'] ?? '',
                'language' => $metadata['LANGUAGE'] ?? '',
                'region' => $metadata['REGION'] ?? '',
            ];
        }

        return $files;
    }

    /**
     * @return array{filename:string,metadata:array<string,string>,rows:list<array{from_date:string,to_date:string,event:string,link:string,category:string,event_id:string}>}
     */
    public function read(string $filename): array
    {
        $filename = $this->filename($filename);
        $file = $this->path($filename);
        if (!is_file($file)) {
            throw new RuntimeException('The selected CSV file does not exist.');
        }
        if (filesize($file) === false || filesize($file) > self::MAX_FILE_BYTES) {
            throw new RuntimeException(I18N::translate('The selected CSV file is too large. The maximum size is 1 MiB.'));
        }

        $handle = fopen($file, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The selected CSV file cannot be read.');
        }

        $metadata = [];
        $rows = [];
        $lineCount = 0;
        while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
            if (++$lineCount > self::MAX_ROWS || !$this->rowIsWithinLimits($row)) {
                fclose($handle);
                throw new RuntimeException(I18N::translate('The selected CSV file exceeds the supported size limits.'));
            }
            $first = trim((string) ($row[0] ?? ''));
            if (preg_match('/^##\s+([A-Z][A-Z0-9_-]*):\s*(.*)$/', $first, $matches) === 1) {
                $metadata[$matches[1]] = trim($matches[2]);
                continue;
            }
            if ($first === '' || str_starts_with($first, '#') || $first === 'From date' || $first === 'Start date') {
                continue;
            }

            $values = array_slice($row + array_fill(0, 6, ''), 0, 6);
            $rows[] = [
                'from_date' => $this->dateForEditor((string) $values[0]),
                'to_date' => $this->dateForEditor((string) $values[1]),
                'event' => (string) $values[2],
                'link' => (string) $values[3],
                'category' => (string) $values[4],
                'event_id' => $this->readEventId((string) $values[5]),
            ];
        }
        fclose($handle);
        ksort($metadata);

        return ['filename' => $filename, 'metadata' => $metadata, 'rows' => $rows];
    }

    /**
     * @param array<string,string> $metadata
     * @param list<array{from_date:string,to_date:string,event:string,link:string,category:string,event_id:string}> $rows
     * @return array{invalid_dates:int,invalid_periods:int}
     */
    public function save(string $filename, array $metadata, array $rows): array
    {
        $filename = $this->filename($filename);
        if (count($rows) > self::MAX_ROWS) {
            throw new RuntimeException(I18N::translate('The CSV file contains too many rows.'));
        }
        $existing = is_file($this->path($filename)) ? $this->read($filename)['metadata'] : [];

        foreach (self::METADATA_FIELDS as $field) {
            $existing[$field] = $this->singleLine($metadata[$field] ?? '');
        }
        $existing['FORMAT'] = 'webtrees gramps-historical-facts';

        if ($existing['TOPIC'] === '' || $existing['LANGUAGE'] === '') {
            throw new RuntimeException('TOPIC and LANGUAGE are required.');
        }

        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('The CSV file cannot be prepared.');
        }

        fwrite($stream, "########################################################\n");
        foreach (['FORMAT', ...self::METADATA_FIELDS] as $field) {
            fwrite($stream, '## ' . $field . ': ' . ($existing[$field] ?? '') . "\n");
        }
        fwrite($stream, "########################################################\n");
        fputcsv($stream, ['Start date', 'End date', 'Event', 'Source link', 'Category', 'Event ID'], ';', '"', '\\');

        $invalidDates = 0;
        $invalidPeriods = 0;
        foreach ($rows as $row) {
            $fromDate = $this->validatedDate($row['from_date'] ?? '', $invalidDates);
            $toDate = $this->validatedDate($row['to_date'] ?? '', $invalidDates);
            if ($fromDate !== '' && $toDate !== '' && !$this->datesInSequence($fromDate, $toDate)) {
                $toDate = '';
                ++$invalidPeriods;
            }
            $values = [
                $fromDate,
                $toDate,
                $this->singleLine($row['event'] ?? ''),
                $this->singleLine($row['link'] ?? ''),
                $this->singleLine($row['category'] ?? ''),
            ];
            if (!$this->rowIsWithinLimits($values)) {
                fclose($stream);
                throw new RuntimeException(I18N::translate('A CSV field exceeds the supported size limit.'));
            }
            if (implode('', $values) === '') {
                continue;
            }
            $values[] = $this->canonicalEventId($row['event_id'] ?? '');
            fputcsv($stream, $values, ';', '"', '\\');
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false || file_put_contents($this->path($filename), $contents, LOCK_EX) === false) {
            throw new RuntimeException('The CSV file cannot be saved.');
        }

        return ['invalid_dates' => $invalidDates, 'invalid_periods' => $invalidPeriods];
    }

    /** @param array<int,string|null> $row */
    private function rowIsWithinLimits(array $row): bool
    {
        foreach ($row as $value) {
            if (strlen((string) $value) > self::MAX_FIELD_BYTES) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,string> $metadata */
    public function create(string $filename, array $metadata): void
    {
        $this->saveAs($filename, $metadata, []);
    }

    /**
     * @param array<string,string> $metadata
     * @param list<array{from_date:string,to_date:string,event:string,link:string,category:string,event_id:string}> $rows
     * @return array{invalid_dates:int,invalid_periods:int}
     */
    public function saveAs(string $filename, array $metadata, array $rows): array
    {
        $filename = $this->filename($filename);
        if (is_file($this->path($filename))) {
            throw new RuntimeException('A CSV file with this name already exists.');
        }

        $this->ensureFolder();
        return $this->save($filename, $metadata, $rows);
    }

    public function delete(string $filename): void
    {
        $file = $this->path($this->filename($filename));
        if (!is_file($file)) {
            throw new RuntimeException('The selected CSV file does not exist.');
        }
        if (!unlink($file)) {
            throw new RuntimeException('The selected CSV file cannot be deleted.');
        }
    }

    private function ensureFolder(): void
    {
        if (!is_dir($this->folder) && !mkdir($this->folder, 0775, true) && !is_dir($this->folder)) {
            throw new RuntimeException('The custom CSV data folder cannot be created.');
        }
    }

    private function filename(string $filename): string
    {
        $filename = trim($filename);
        if (pathinfo($filename, PATHINFO_EXTENSION) === '') {
            $filename .= '.csv';
        }
        if ($filename === 'GermanChancellorsPresidents.csv') {
            throw new RuntimeException('This CSV file belongs to another data provider and cannot be edited here.');
        }
        if (preg_match('/\A[A-Za-z0-9][A-Za-z0-9._-]*\.csv\z/', $filename) !== 1 || pathinfo($filename, PATHINFO_BASENAME) !== $filename) {
            throw new RuntimeException('Use a simple filename ending in .csv.');
        }

        return $filename;
    }

    private function path(string $filename): string
    {
        $this->ensureFolder();

        return $this->folder . $filename;
    }

    private function singleLine(string $value): string
    {
        return trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value));
    }

    private function canonicalEventId(string $value): string
    {
        $eventIds = $this->eventIds($value);
        if ($eventIds === []) {
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
            $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

            return substr(bin2hex($bytes), 0, 8) . '-' . substr(bin2hex($bytes), 8, 4) . '-' .
                substr(bin2hex($bytes), 12, 4) . '-' . substr(bin2hex($bytes), 16, 4) . '-' . substr(bin2hex($bytes), 20);
        }

        return implode(',', $eventIds);
    }

    private function readEventId(string $value): string
    {
        return implode(',', $this->eventIds($value));
    }

    /**
     * @return list<string>
     */
    private function eventIds(string $value): array
    {
        $value = strtolower($this->singleLine($value));
        if ($value === '') {
            return [];
        }

        $eventIds = [];
        foreach (explode(',', $value) as $eventId) {
            $eventId = trim($eventId);
            if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $eventId) !== 1) {
                throw new RuntimeException('Use canonical UUID v4 values for event IDs.');
            }

            $eventIds[] = $eventId;
        }

        return array_values(array_unique($eventIds));
    }

    private function dateForEditor(string $value): string
    {
        $value = $this->singleLine($value);
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $matches) === 1) {
            if (!checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])) {
                return $value;
            }

            return (int) $matches[3] . ' ' . $this->monthName((int) $matches[2]) . ' ' . (int) $matches[1];
        }
        if (preg_match('/\A(\d{4})-(\d{2})\z/', $value, $matches) === 1) {
            if ((int) $matches[1] === 0 || (int) $matches[2] < 1 || (int) $matches[2] > 12) {
                return $value;
            }

            return $this->monthName((int) $matches[2]) . ' ' . (int) $matches[1];
        }

        return $value;
    }

    private function canonicalDate(string $value): string
    {
        $value = $this->singleLine($value);
        if ($value === '') {
            return '';
        }
        if (strcasecmp($value, 'Today') === 0) {
            return 'Today';
        }
        if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            if (!checkdate($month, $day, $year)) {
                throw new RuntimeException('Use a valid Gregorian date without a range or qualifier.');
            }

            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
        if (preg_match('/\A(\d{4})-(\d{2})\z/', $value, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            if ($year === 0 || $month < 1 || $month > 12) {
                throw new RuntimeException('Use a valid Gregorian date without a range or qualifier.');
            }

            return sprintf('%04d-%02d', $year, $month);
        }

        $value = strtoupper($value);
        if (preg_match('/\A[1-9]\d{0,3}\z/', $value) === 1) {
            return sprintf('%04d', (int) $value);
        }
        if (preg_match('/\A(JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC) ([1-9]\d{0,3})\z/', $value) === 1) {
            preg_match('/\A([A-Z]{3}) ([1-9]\d{0,3})\z/', $value, $matches);

            return sprintf('%04d-%02d', (int) $matches[2], $this->monthNumber($matches[1]));
        }
        if (preg_match('/\A(\d{1,2}) (JAN|FEB|MAR|APR|MAY|JUN|JUL|AUG|SEP|OCT|NOV|DEC) ([1-9]\d{0,3})\z/', $value, $matches) === 1) {
            if (!checkdate($this->monthNumber($matches[2]), (int) $matches[1], (int) $matches[3])) {
                throw new RuntimeException('Use a valid Gregorian date without a range or qualifier.');
            }

            return sprintf('%04d-%02d-%02d', (int) $matches[3], $this->monthNumber($matches[2]), (int) $matches[1]);
        }

        throw new RuntimeException('Use a valid Gregorian date without a range or qualifier.');
    }

    private function validatedDate(string $value, int &$invalidDates): string
    {
        try {
            $date = $this->canonicalDate($value);
            if ($date !== '' && !$this->dateIsInSupportedRange($date)) {
                throw new RuntimeException('Use a date from the beginning of the Gregorian calendar up to today.');
            }

            return $date;
        } catch (RuntimeException) {
            ++$invalidDates;

            return '';
        }
    }

    private function dateIsInSupportedRange(string $date): bool
    {
        [$earliest, $latest] = $this->dateBounds($date);

        return $latest >= '1582-10-15' && $earliest <= date('Y-m-d');
    }

    private function datesInSequence(string $fromDate, string $toDate): bool
    {
        [$fromEarliest] = $this->dateBounds($fromDate);
        [, $toLatest] = $this->dateBounds($toDate);

        return $fromEarliest <= $toLatest;
    }

    /** @return array{string,string} */
    private function dateBounds(string $date): array
    {
        if (strcasecmp($date, 'Today') === 0) {
            $today = date('Y-m-d');

            return [$today, $today];
        }
        if (preg_match('/\A(\d{4})\z/', $date, $matches) === 1) {
            return [$matches[1] . '-01-01', $matches[1] . '-12-31'];
        }
        if (preg_match('/\A(\d{4})-(\d{2})\z/', $date, $matches) === 1) {
            $lastDay = date('t', strtotime($date . '-01'));

            return [$date . '-01', $date . '-' . $lastDay];
        }

        return [$date, $date];
    }

    private function monthName(int $month): string
    {
        return [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'][$month];
    }

    private function monthNumber(string $month): int
    {
        return (int) array_search($month, [1 => 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'], true);
    }
}
