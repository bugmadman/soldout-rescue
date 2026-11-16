<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\SoldOut\SoldOutService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class SoldOutController extends AbstractController
{
    public function __construct(
        private readonly SoldOutService $service,
    ) {
    }

    #[Route('/', name: 'sold_outs', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $requested = $request->query->getString('date');
        $date = $this->service->latestSoldOutDate();
        if ($requested !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $requested);
            if ($date === false || $date->format('Y-m-d') !== $requested) {
                throw new BadRequestHttpException('The date must look like 2026-01-31.');
            }
        }

        return $this->render('sold_out/index.html.twig', [
            'date' => $date,
            'items' => $date === null ? [] : $this->service->recommendationsOn($date),
        ]);
    }
}
