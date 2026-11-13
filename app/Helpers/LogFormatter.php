<?php

namespace App\Helpers;

use Illuminate\Support\Collection;

class LogFormatter
{
    /**
     * Format a payload for display as an HTML table
     * @param Collection|array|null $payload
     * @return string HTML markup
     */
    public static function formatPayload(Collection|array|null $payload): string
    {
        if (!$payload) {
            return '<span class="text-muted">No data</span>';
        }

        if ($payload instanceof Collection) {
            $data = $payload->toArray();
        } else {
            $data = $payload;
        }

        if (empty($data)) {
            return '<span class="text-muted">No data</span>';
        }

        // If this is a change structure (field => ['old' => ..., 'new' => ...])
        $isChangesStructure = !empty($data) && is_array(reset($data)) &&
            isset(reset($data)['old']) && isset(reset($data)['new']);

        if ($isChangesStructure) {
            return self::formatChangesTable($data);
        }

        return self::formatSimpleTable($data);
    }

    /**
     * Format a change table (old/new value)
     * @param array $changes
     * @return string HTML table markup
     */
    public static function formatChangesTable(array $changes): string
    {
        if (empty($changes)) {
            return '<span class="text-muted">No changes</span>';
        }

        $html = '<table class="table table-sm table-bordered" style="margin: 0; font-size: 12px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 30%">Field</th>';
        $html .= '<th style="width: 35%">Old value</th>';
        $html .= '<th style="width: 35%">New value</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($changes as $field => $values) {
            $old = self::formatValue($values['old'] ?? null);
            $new = self::formatValue($values['new'] ?? null);

            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($field) . '</strong></td>';
            $html .= '<td>' . $old . '</td>';
            $html .= '<td>' . $new . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Format a simple key-value table
     * @param array $data
     * @return string HTML table markup
     */
    public static function formatSimpleTable(array $data): string
    {
        if (empty($data)) {
            return '<span class="text-muted">No data</span>';
        }

        $html = '<table class="table table-sm table-bordered" style="margin: 0; font-size: 12px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="width: 40%">Parameter</th>';
        $html .= '<th style="width: 60%">Value</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($data as $key => $value) {
            $html .= '<tr>';
            $html .= '<td><strong>' . htmlspecialchars($key) . '</strong></td>';
            $html .= '<td>' . self::formatValue($value) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        return $html;
    }

    /**
     * Format JSON data in a collapsible block
     * @param array|object $data
     * @return string HTML markup
     */
    public static function formatJson(array|object $data): string
    {
        $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return '<details style="cursor: pointer;">
            <summary style="font-weight: 500; color: #6c757d;">Show JSON ▼</summary>
            <pre style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 11px; max-height: 300px; overflow-y: auto;"><code>' .
            htmlspecialchars($json) .
            '</code></pre>
        </details>';
    }

    /**
     * Format a value for display
     * @param mixed $value
     * @return string HTML markup
     */
    public static function formatValue(mixed $value): string
    {
        if ($value === null) {
            return '<span class="text-muted">-</span>';
        }

        if (is_bool($value)) {
            return $value ? '<span class="badge bg-success">Yes</span>' : '<span class="badge bg-secondary">No</span>';
        }

        if (is_array($value)) {
            return '<code style="font-size: 11px;">' . htmlspecialchars(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . '</code>';
        }

        if (is_string($value) && strlen($value) > 100) {
            return '<div style="max-height: 100px; overflow-y: auto;">' . htmlspecialchars($value) . '</div>';
        }

        return htmlspecialchars((string)$value);
    }
}


