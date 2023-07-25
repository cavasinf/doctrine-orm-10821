<?php

namespace App\Utils;

use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\QueryBuilder as DoctrineQueryBuilder;
use Doctrine\ORM\Tools\Pagination\CountWalker;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;

class Paginator
{
    final public const PAGE_SIZE = 10;

    private int          $pageSize = self::PAGE_SIZE;
    private int          $currentPage;
    private \Traversable $results;
    private int          $numResults;
    private array        $errors = [];

    public function __construct(
        private readonly PaginatorProcessor $paginatorProcessor,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @throws QueryException
     */
    public function paginate(
        DoctrineQueryBuilder $queryBuilder,
        int $page = 1,
        ?int $pageSize = null,
    ): self {
        $this->paginatorProcessor->initRequestStack($this->requestStack);

        $pageSize = $this->paginatorProcessor->getPageSize() ?: $pageSize;
        $this->setPageSize($pageSize);

        $this->currentPage = max(1, $page);
        $firstResult       = ($this->currentPage - 1) * $this->getPageSize();

        $defaultOrderBy = $queryBuilder->getDQLPart('orderBy');

        if ($this->paginatorProcessor->isSorted()) {
            if ($this->paginatorProcessor->hasDirection()) {
                $orderBy = new OrderBy();
                foreach (explode('+', $this->paginatorProcessor->getSort()) as $sort) {
                    $orderBy->add($sort, $this->paginatorProcessor->getDirection());
                }
                $queryBuilder->orderBy($orderBy);
            } else {
                $this->errors[] = 'Sens de trie incorrect !';
            }
        }

        $paginator = $this->_definePaginator($queryBuilder, $firstResult);

        try {
            $this->results = $paginator->getIterator();
        } catch (QueryException $e) {
            if (str_contains($e->getMessage(), 'is not defined') || str_contains($e->getMessage(), 'has no field')) {
                if (\count($defaultOrderBy) > 0) {
                    $queryBuilder->orderBy($defaultOrderBy);
                } else {
                    $queryBuilder->resetDQLPart('orderBy');
                }

                $this->errors[] = "Impossible de trier sur '{$this->paginatorProcessor->getSort()}' !";
                $paginator      = $this->_definePaginator($queryBuilder, $firstResult);
                $this->results  = $paginator->getIterator();
            } else {
                throw $e;
            }
        }

        $this->numResults = $paginator->count();

        return $this;
    }

    private function _definePaginator(QueryBuilder $queryBuilder, int $firstResult): DoctrinePaginator
    {
        $query = $queryBuilder
            ->setFirstResult($firstResult)
            ->setMaxResults($this->getPageSize())
            ->getQuery()
        ;

        if (0 === (is_countable($queryBuilder->getDQLPart('join')) ? \count($queryBuilder->getDQLPart('join')) : 0)) {
            $query->setHint(CountWalker::HINT_DISTINCT, false);
        }

        $paginator = new DoctrinePaginator($query, true);

        $useOutputWalkers = (is_countable($queryBuilder->getDQLPart('having') ?: []) ? \count($queryBuilder->getDQLPart('having') ?: []) : 0) > 0;
        $paginator->setUseOutputWalkers($useOutputWalkers);

        return $paginator;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getLastPage(): int
    {
        return (int) ceil($this->numResults / $this->getPageSize());
    }

    public function setPageSize(?int $pageSize): self
    {
        if ($pageSize) {
            $this->pageSize = $pageSize;
        }

        return $this;
    }

    public function getPageSize(): int
    {
        return $this->pageSize ?? self::PAGE_SIZE;
    }

    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    public function getPreviousPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getLastPage();
    }

    public function getNextPage(): int
    {
        return min($this->getLastPage(), $this->currentPage + 1);
    }

    public function hasToPaginate(): bool
    {
        return $this->numResults > $this->getPageSize();
    }

    public function getNumResults(): int
    {
        return $this->numResults;
    }

    public function getResults(): \Traversable
    {
        return $this->results;
    }

    public function hasError(): bool
    {
        return \count($this->errors) > 0;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
