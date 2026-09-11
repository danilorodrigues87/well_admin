<?php

namespace App\Model\Db;

class Pagination
{
    private int $limit;

    private int $results;

    private int $pages;

    private int $currentPage;

    public function __construct(int $results, int $currentPage = 1, int $limit = 10)
    {
        $this->results = $results;
        $this->limit = $limit;
        $this->currentPage = ($currentPage > 0) ? $currentPage : 1;
        $this->calculate();
    }

    private function calculate(): void
    {
        $this->pages = $this->results > 0 ? (int)ceil($this->results / $this->limit) : 1;
        $this->currentPage = min($this->currentPage, max(1, $this->pages));
    }

    public function getLimit(): string
    {
        $offset = $this->limit * ($this->currentPage - 1);

        return $offset.','.$this->limit;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->pages;
    }

    /** @return list<array{page:int,current:bool}> */
    public function getPages(): array
    {
        if ($this->pages <= 1) {
            return [];
        }
        $paginas = [];
        for ($i = 1; $i <= $this->pages; $i++) {
            $paginas[] = ['page' => $i, 'current' => $i === $this->currentPage];
        }

        return $paginas;
    }

    /** Paginação compacta (janela + reticências), estilo CTI. */
    public static function renderNav(self $pagination, string $jsFunction = 'loadPage'): string
    {
        $total = $pagination->getTotalPages();
        if ($total <= 1) {
            return '';
        }

        $current = $pagination->getCurrentPage();
        $html = '<nav class="crud-pagination-nav"><ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center">';

        if ($current > 1) {
            $html .= self::pageLink($current - 1, '«', $jsFunction, false);
        }

        for ($i = 1; $i <= $total; $i++) {
            if ($total > 9 && abs($i - $current) > 3 && $i !== 1 && $i !== $total) {
                if ($i === 2 || $i === $total - 1) {
                    $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
                }
                continue;
            }
            $html .= self::pageLink($i, (string)$i, $jsFunction, $i === $current);
        }

        if ($current < $total) {
            $html .= self::pageLink($current + 1, '»', $jsFunction, false);
        }

        $html .= '</ul></nav>';

        return $html;
    }

    private static function pageLink(int $page, string $label, string $jsFunction, bool $active): string
    {
        $cls = 'page-item'.($active ? ' active' : '');

        return '<li class="'.$cls.'"><a class="page-link" href="#" onclick="'.$jsFunction.'('.$page.');return false;">'
            .htmlspecialchars($label, ENT_QUOTES, 'UTF-8').'</a></li>';
    }

    /** @deprecated Use renderNav() */
    public static function render(array $pages, string $jsFunction = 'loadPage'): string
    {
        if (empty($pages)) {
            return '';
        }
        $current = 1;
        foreach ($pages as $p) {
            if ($p['current'] ?? false) {
                $current = (int)$p['page'];
                break;
            }
        }
        $total = (int)max(array_column($pages, 'page'));
        $stub = new self($total, $current, 1);
        $stub->pages = $total;
        $stub->currentPage = $current;

        return self::renderNav($stub, $jsFunction);
    }
}
