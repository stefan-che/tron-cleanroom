<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Reports;

use Orchid\Screen\Layouts\Metric;
use Orchid\Screen\Repository;

/**
 * Metric tiles with a hint for each metric.
 *
 * The stock Layout::metrics shows only a label and a value, while the report metrics
 * (spread, variance, agreement with AI) are ambiguous without an explanation.
 */
class HintedMetricsLayout extends Metric
{
    protected $template = 'orchid.reports.metrics';

    /**
     * Metric hints: ['Tile label' => 'what it means'].
     */
    private array $hints = [];

    /**
     * @param array $labels ['Tile label' => 'query.key']
     * @param array $hints ['Tile label' => 'hint']
     */
    public static function make(array $labels, array $hints = []): self
    {
        $layout = new self($labels);
        $layout->hints = $hints;

        return $layout;
    }

    public function build(Repository $repository)
    {
        $this->query = $repository;

        if (!$this->isSee() || empty($this->labels)) {
            return;
        }

        return view($this->template, [
            'title' => $this->title,
            'metrics' => collect($this->labels)
                ->map(fn(string $value) => $repository->getContent($value, '')),
            'hints' => $this->hints,
        ]);
    }
}
