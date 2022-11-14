<?php

namespace App\Service;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class RestPaginator
{
    public function __construct(private readonly UrlGeneratorInterface $router) {}

    public function asArray(string $route, int $page, int $limit, int $totalCount) : array
    {
        $pagesCount = (int)ceil($totalCount / $limit);
        $pages["self"] = ["href" => $this->genRoute($route, $page, $limit)];

        if($page !== 1) {
            $pages["first"] = ["href" => $this->genRoute($route, 1, $limit)];
            $pages["previous"] = ["href" => $this->genRoute($route, $page - 1, $limit)];
        }

        if($page !== $pagesCount) {
            $pages["last"] = ["href" => $this->genRoute($route, $pagesCount, $limit)];
            $pages["next"] = ["href" => $this->genRoute($route, $page + 1, $limit)];
        }

        $pages["current_page"] = $page;
        $pages["pages_count"] = $pagesCount;
        $pages["items_count"] = $totalCount;

        return $pages;
    }

    private function genRoute(string $route, int $page, int $limit) : string
    {
        return $this->router->generate($route, ["page" => $page, "limit" => $limit], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}