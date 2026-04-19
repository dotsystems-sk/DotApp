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
  
public static function paginate($actual_page, $number_of_pages, $href = null, $callable=null) {
        $paginationOutput = Pagination::paginate($actual_page, $number_of_pages)
        ->window(5)
        ->arrows(true)
        ->ellipsis(false)
        ->edge(false);
        
        if (is_callable($callable)) return $paginationOutput->render($callable, $href);
        
        return $paginationOutput->render(function ($type, $page, $label, $state, $href=null) {

            $liClass = "page-item";

            if ($state === 'active') {
                $liClass .= " active";
            }

            if ($state === 'disabled') {
                $liClass .= " disabled";
            }

            $href = ($state === 'disabled' || $type === 'ellipsis')
                ? "javascript:void(0);"
                : "?page={$page}";

            switch ($type) {

                case 'first':
                    $content = '<i class="icon-base ri ri-skip-back-mini-line icon-18px"></i>';
                    break;

                case 'prev':
                    $content = '<i class="icon-base ri ri-arrow-left-s-line icon-18px"></i>';
                    break;

                case 'next':
                    $content = '<i class="icon-base ri ri-arrow-right-s-line icon-18px"></i>';
                    break;

                case 'last':
                    $content = '<i class="icon-base ri ri-skip-forward-mini-line icon-18px"></i>';
                    break;

                case 'ellipsis':
                    $content = '<span class="px-2">...</span>';
                    break;

                default:
                    $content = $label;
            }

            return 
                "
                    <li class='{$liClass}'>
                        <a class='page-link' ".$href($page).">
                            {$content}
                        </a>
                    </li>
                ";
        });
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