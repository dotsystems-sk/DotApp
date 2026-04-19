<?php
namespace Dotsystems\App\Parts;

/**
 * CLASS Pagination - DotApp Pagination Component
 *
 * Flexible, framework-independent pagination builder with support for:
 * - page window calculation
 * - first/prev/next/last navigation
 * - ellipsis support
 * - edge page rendering
 * - fully customizable rendering via callback
 *
 * Designed for consistent UI systems (Bootstrap/Tailwind/custom HTML).
 *
 * Example usage:
 *
 * echo Pagination::paginate(10, 100)
 *     ->window(5)
 *     ->arrows(true)
 *     ->ellipsis(true)
 *     ->edge(true)
 *     ->render(function ($type, $page, $label, $state) {
 *
 *         $disabled = $state === 'disabled';
 *         $active   = $state === 'active';
 *
 *         return "<li class='page-item ".($active ? 'active' : '').($disabled ? ' disabled' : '')."'>
 *                     <a class='page-link' href='".($disabled ? "javascript:void(0);" : "?page=$page")."'>
 *                         $label
 *                     </a>
 *                 </li>";
 *     });
 */

class Pagination
{
    private int $current;
    private int $total;

    private int $window = 5;
    private bool $arrows = true;
    private bool $ellipsis = true;
    private bool $edge = true;

    private function __construct(int $current, int $total)
    {
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

    public function render(callable $render, $href=null): string
    {
        if ($this->total <= 0) {
            return '';
        }

        $window = min($this->window, $this->total);
        $half = intdiv($window, 2);

        $from = $this->current - $half;
        $to   = $this->current + $half;

        // normalize window
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
        // FIRST + PREV
        // =========================
        if ($this->arrows && $this->total > $window) {

            $items[] = $this->item(
                'first',
                1,
                '«',
                $this->current === 1 ? 'disabled' : 'normal'
            );

            $items[] = $this->item(
                'prev',
                max(1, $this->current - 1),
                '‹',
                $this->current === 1 ? 'disabled' : 'normal'
            );
        }

        // =========================
        // LEFT EDGE
        // =========================
        if ($this->edge && $from > 1) {

            $items[] = $this->item('page', 1, '1', $this->current === 1 ? 'active' : 'normal');

            if ($this->ellipsis && $from > 2) {
                $items[] = $this->item('ellipsis', null, '...', 'normal');
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
                $i === $this->current ? 'active' : 'normal'
            );
        }

        // =========================
        // RIGHT EDGE
        // =========================
        if ($this->edge && $to < $this->total) {

            if ($this->ellipsis && $to < $this->total - 1) {
                $items[] = $this->item('ellipsis', null, '...', 'normal');
            }

            $items[] = $this->item(
                'page',
                $this->total,
                (string)$this->total,
                $this->current === $this->total ? 'active' : 'normal'
            );
        }

        // =========================
        // NEXT + LAST
        // =========================
        if ($this->arrows && $this->total > $window) {

            $items[] = $this->item(
                'next',
                min($this->total, $this->current + 1),
                '›',
                $this->current === $this->total ? 'disabled' : 'normal'
            );

            $items[] = $this->item(
                'last',
                $this->total,
                '»',
                $this->current === $this->total ? 'disabled' : 'normal'
            );
        }

        // =========================
        // RENDER
        // =========================
        $html = '';

        foreach ($items as $it) {
            $html .= $render($it['type'], $it['page'], $it['label'], $it['state'],$href);
        }

        return $html;
    }

    private function item(string $type, ?int $page, string $label, string $state): array
    {
        return [
            'type' => $type,
            'page' => $page,
            'label' => $label,
            'state' => $state
        ];
    }
}