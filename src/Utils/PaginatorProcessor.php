<?php

namespace App\Utils;

use function array_merge;
use function array_replace;
use function implode;
use function str_starts_with;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PaginatorProcessor
{
    final public const CST_UrlParam_Sort      = 'sortBy';
    final public const CST_UrlParam_Direction = 'sortOrder';
    final public const CST_UrlParam_Page      = 'page';
    final public const CST_UrlParam_PageSize  = 'pageSize';
    final public const CST_UrlParam_Search    = 'search';

    final public const CST_SortDirection_Asc  = 'asc';
    final public const CST_SortDirection_Desc = 'desc';

    private string $route  = '';
    private array  $params = [];

    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function isSorted(): bool
    {
        return $this->getSort() !== null;
    }

    public function getSort(): ?string
    {
        return $this->params[self::CST_UrlParam_Sort] ?? null;
    }

    public function isSortedFor(string|array $key): bool
    {
        if (\is_array($key)) {
            $key = implode('+', $key);
        }

        return \array_key_exists(self::CST_UrlParam_Sort, $this->params)
            && $this->getSort() === $key;
    }

    public function hasDirection(): bool
    {
        return $this->getDirection() !== null;
    }

    public function getDirection(): ?string
    {
        $direction = $this->params[self::CST_UrlParam_Direction] ?? 'asc';
        if ($direction) {
            $direction = mb_strtolower((string) $direction);
        }

        return $this->hasValidDirection($direction) ? $direction : null;
    }

    public function hasValidDirection(?string $direction): bool
    {
        return \in_array($direction, [self::CST_SortDirection_Asc, self::CST_SortDirection_Desc], true);
    }

    public function getPageSize(): ?int
    {
        return $this->params[self::CST_UrlParam_PageSize] ?? null;
    }

    public function getSearch(): ?string
    {
        return $this->params[self::CST_UrlParam_Search] ?? null;
    }

    public function sortable(
        string $title,
        string|array $key,
        array $options = [],
        array $params = [],
    ): array {
        $this->initRequestStack($this->requestStack);

        $options = array_merge(
            [
                'absolute' => UrlGeneratorInterface::ABSOLUTE_PATH,
            ],
            $options
        );

        $direction = self::CST_SortDirection_Asc;

        $class = 'sortable';
        if ($this->isSortedFor($key)) {
            if ($this->hasDirection()) {
                // Inversement de la direction existante
                switch ($this->getDirection()) {
                    case self::CST_SortDirection_Asc:
                        $direction = self::CST_SortDirection_Desc;
                        $class     = $this->getDirection();
                        break;
                    case self::CST_SortDirection_Desc:
                        $direction = null;
                        $class     = $this->getDirection();
                        break;
                }
            }
        }

        // ** Gestion des class de l'item <a> **
        if (isset($options['class'])) {
            // Ajout de la classe aux existantes
            $options['class'] .= ' ' . $class;
        } else {
            $options['class'] = $class;
        }

        if (\is_array($key)) {
            // Concaténation des clés de tri
            $key = implode('+', $key);
        }

        // Définition des paramètres de l'URL à générer
        $params = array_merge($this->params, $params, [
            self::CST_UrlParam_Sort      => $direction ? $key : null,
            self::CST_UrlParam_Direction => $direction,
            self::CST_UrlParam_Page      => 1, // Reset page à 1 au tri
        ]);

        $options['title'] = $title;
        $route            = $options['route'] ?? $this->route;
        $options['href']  = $this->router->generate($route, $params, $options['absolute']);

        // Suppression des options avant rendu HTML
        unset($options['absolute']);

        return [
            'options'   => $options,
            'title'     => $title,
            'direction' => $direction,
            'state'     => $class,
            'key'       => $key,
        ];
    }

    public function initRequestStack(RequestStack $requestStack): void
    {
        $request = $requestStack->getMainRequest();
        if ($request === null) {
            return;
        }

        $this->route  = $request->attributes->get('_route');
        $this->params = array_replace($request->query->all(), $request->attributes->get('_route_params', []));
        foreach ($this->params as $key => $param) {
            if (str_starts_with($key, '_')) {
                unset($this->params[$key]);
            }
        }
    }
}
