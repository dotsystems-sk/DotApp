<?php
namespace Dotsystems\App\Parts;

/**
 * CLASS Pagination - DotApp Pagination Component
 *
 * This class provides a flexible, framework-independent pagination builder
 * designed for rendering paginated navigation elements in web applications.
 *
 * It supports configurable page windows, edge links, ellipsis indicators,
 * and navigation arrows. The rendering layer is fully decoupled via a
 * callback-based renderer, allowing complete control over HTML output.
 *
 * The component is designed for use in consistent UI systems where a shared
 * CSS/markup template is applied across the application.
 *
 * Features:
 * - Fluent builder API
 * - Adjustable page window size
 * - Optional first/last and previous/next navigation arrows
 * - Optional ellipsis for compact large-range pagination
 * - Edge page links (first/last pages)
 * - Fully customizable rendering via callback
 *
 * Example usage:
 *
 * ```php
 * echo Pagination::paginate(10, 100)
 *     ->window(5)
 *     ->arrows(true)
 *     ->ellipsis(true)
 *     ->edge(true)
 *     ->render(function ($type, $page, $label, $active) {
 *
 *         return match ($type) {
 *             'page' => "<a href='?page={$page}' class='".($active ? "active" : "")."'>{$label}</a>",
 *             'ellipsis' => "<span class='dots'>{$label}</span>",
 *             'first' => "<a href='?page={$page}' class='first'>{$label}</a>",
 *             'prev'  => "<a href='?page={$page}' class='prev'>{$label}</a>",
 *             'next'  => "<a href='?page={$page}' class='next'>{$label}</a>",
 *             'last'  => "<a href='?page={$page}' class='last'>{$label}</a>",
 *             default => '',
 *         };
 *     });
 * ```
 *
 * @package   DotApp Framework
 * @author    Štefan Miščík <info@dotsystems.sk>
 * @company   Dotsystems s.r.o.
 * @version   1.8 FREE
 * @license   MIT License
 * @date      2014 - 2026
 */



class Pagination {
    private int $current;
    private int $total;

    private int $window = 5;
    private bool $arrows = true;
    private bool $ellipsis = true;
    private bool $edge = true;

    private function __construct(int $current, int $total) {
        $this->current = max(1, $current);
        $this->total = max(0, $total);
    }

    public static function paginate(int $current, int $total): self
    {
        return new self($current, $total);
    }

    public function window(int $window): self
    {
        $this->window = max(1, $window);
        return $this;
    }

    public function arrows(bool $state): self
    {
        $this->arrows = $state;
        return $this;
    }

    public function ellipsis(bool $state): self
    {
        $this->ellipsis = $state;
        return $this;
    }

    public function edge(bool $state): self
    {
        $this->edge = $state;
        return $this;
    }

    public function render(callable $render): string
    {
        if ($this->total <= 0) {
            return '';
        }

        $window = min($this->window, $this->total);
        $half = intdiv($window, 2);

        $from = $this->current - $half;
        $to   = $this->current + $half;

        if ($from < 1) {
            $to += (1 - $from);
            $from = 1;
        }

        if ($to > $this->total) {
            $from -= ($to - $this->total);
            $to = $this->total;
        }

        if ($from < 1) {
            $from = 1;
        }

        $items = [];

        // =========================
        // LEFT ARROWS
        // =========================
        if ($this->arrows && $this->total > $window) {
            $items[] = $this->item('first', 1, '«', false);
            $items[] = $this->item('prev', max(1, $this->current - $window), '‹', false);
        }

        // =========================
        // LEFT EDGE + ELLIPSIS
        // =========================
        if ($this->edge && $from > 1) {
            $items[] = $this->item('page', 1, '1', false);

            if ($this->ellipsis && $from > 2) {
                $items[] = $this->item('ellipsis', null, '...', false);
            }
        }

        // =========================
        // MAIN WINDOW
        // =========================
        for ($i = $from; $i <= $to; $i++) {
            $items[] = $this->item(
                'page',
                $i,
                (string)$i,
                $i === $this->current
            );
        }

        // =========================
        // RIGHT EDGE + ELLIPSIS
        // =========================
        if ($this->edge && $to < $this->total) {

            if ($this->ellipsis && $to < $this->total - 1) {
                $items[] = $this->item('ellipsis', null, '...', false);
            }

            $items[] = $this->item('page', $this->total, (string)$this->total, false);
        }

        // =========================
        // RIGHT ARROWS
        // =========================
        if ($this->arrows && $this->total > $window) {
            $items[] = $this->item('next', min($this->total, $this->current + $window), '›', false);
            $items[] = $this->item('last', $this->total, '»', false);
        }

        // render
        $html = '';

        foreach ($items as $it) {
            $html .= $render($it['type'], $it['page'], $it['label'], $it['active']);
        }

        return $html;
    }

    private function item(string $type, ?int $page, string $label, bool $active): array
    {
        return [
            'type' => $type,
            'page' => $page,
            'label' => $label,
            'active' => $active
        ];
    }
}