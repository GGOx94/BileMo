<?php

namespace App\Controller;

use App\Entity\Smartphone;
use App\Repository\SmartphoneRepository;
use App\Service\JsonEntityHelper;
use App\Service\RestPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;

use OpenApi\Attributes as OA;
use Nelmio\ApiDocBundle\Annotation as Nelmio;

class SmartphoneController extends AbstractController
{
    private const CACHE_PHONES = "cachePhones";

    public function __construct(
        private readonly EntityManagerInterface     $em,
        private readonly JsonEntityHelper           $jsonHelper
    ) {}

    /** Fetch all of our smartphones (paginated response) */
    #[Nelmio\Areas(["default"]), OA\Tag(name: "Smartphones")]
    #[OA\Parameter(name: "page", description: "The page of the result", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 1))]
    #[OA\Parameter(name: "limit", description: "Number of elements per page", in: "query", required: false, schema: new OA\Schema(type: "integer", default: 3))]
    #[OA\Response(response: 200, description: "The list of smartphones", content: new OA\JsonContent(type: "array", items: new OA\Items(ref: new Model(type: Smartphone::class, groups: ["getPhones"]))))]
    #[Route('/api/smartphones', name: 'api_phones', methods: ['GET'], format: 'json')]
    public function getAllSmartphones(
        Request                     $request,
        SmartphoneRepository        $smartphoneRepository,
        TagAwareCacheInterface      $cachePool,
        RestPaginator               $paginator
    ) : JsonResponse
    {
        // Create the smartphones count cache if needed
        $phoneCountCacheId = "getAllPhones-count";
        $phoneCount = $cachePool->get($phoneCountCacheId,
            function (ItemInterface $item) use ($smartphoneRepository) {
                $item->tag(self::CACHE_PHONES)->expiresAfter(60);
                return $smartphoneRepository->getCount();
        });

        // Create the smartphones list cache if needed, depending on the query parameters (page, limit)
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);
        $idCache = "getAllPhones-$page-$limit";
        $jsonPhonesList = $cachePool->get($idCache,
            function (ItemInterface $item) use ($smartphoneRepository, $page, $limit, $phoneCount, $paginator) {
                $item->tag(self::CACHE_PHONES)->expiresAfter(60);
                $list = $smartphoneRepository->findAllWithPagination($page, $limit);

                $pages = $paginator->asArray("api_phones", $page, $limit, $phoneCount);
                return $this->jsonHelper->serialize(array("_pages" => $pages, "items" => $list), ["getPhones"]);
        });

        return new JsonResponse(data: $jsonPhonesList, status: Response::HTTP_OK, json: true);
    }

    /** Fetch a specific smartphone */
    #[OA\Tag(name: "Smartphones")]
    #[OA\PathParameter(name: "id", description: "The id of the smartphone", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 200, description: "The smartphone data is in the response body", content: new OA\JsonContent(ref: new Model(type: Smartphone::class, groups: ["getPhones"])))]
    #[Route('/api/smartphones/{id}', name: 'api_phone_get', methods: ['GET'], format: 'json')]
    public function fetchPhone(Smartphone $phone): JsonResponse
    {
        $jsonphone = $this->jsonHelper->serialize($phone, ["getPhones"]);
        return new JsonResponse($jsonphone, Response::HTTP_OK, [], true);
    }

    /** Create a new smartphone */
    #[OA\Tag(name: "[Admin] Smartphones")]
    #[OA\PathParameter(name: "id", description: "The id of the smartphone", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 201, description: "The smartphone has been created and is present in the response body", content: new OA\JsonContent(ref: new Model(type: Smartphone::class, groups: ["getPhones"])))]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to phone creation")]
    #[Route('/api/smartphones/', name:"api_phone_create", methods: ['POST'], format: 'json')]
    public function createPhone(
        Request                 $request,
        UrlGeneratorInterface   $urlGenerator,
        JsonEntityHelper        $entityHelper
    ) : JsonResponse
    {
        $phone = $entityHelper->deserializeAndValidate($request->getContent(), Smartphone::class, ["modifyPhones"]);

        $this->em->persist($phone);
        $this->em->flush();

        $jsonphone = $this->jsonHelper->serialize($phone, ["getPhones"]);
        $location = $urlGenerator->generate('api_phone_get', ['id' => $phone->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonphone, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /** Update an existing smartphone */
    #[OA\Tag(name: "[Admin] Smartphones")]
    #[OA\PathParameter(name: "id", description: "The id of the smartphone", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 204, description: "The smartphone has been updated")]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to phone modification")]
    #[Route('/api/smartphones/{id}', name:"api_phone_update", methods:['PUT'], format: 'json')]
    public function updatePhone(
        Request                 $request,
        Smartphone              $smartphone,
        TagAwareCacheInterface  $cachePool,
        JsonEntityHelper        $entityHelper
    ) : JsonResponse
    {
        $smartphone = $entityHelper->updateEntity($request->getContent(), $smartphone, ["modifyPhones"]);

        $this->em->persist($smartphone);
        $this->em->flush();

        // We modified the phone collection, invalidate cache
        $cachePool->invalidateTags([self::CACHE_PHONES]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /** Delete a smartphone */
    #[OA\Tag(name: "[Admin] Smartphones")]
    #[OA\PathParameter(name: "id", description: "The id of the smartphone", required: true, schema: new OA\Schema(type: "integer"))]
    #[OA\Response(response: 204, description: "The smartphone has been deleted")]
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to phone deletion")]
    #[Route('/api/smartphones/{id}', name: 'api_phone_delete', methods: ['DELETE'], format: 'json')]
    public function deletePhone(Smartphone $phone, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $this->em->remove($phone);
        $this->em->flush();

        $cachePool->invalidateTags([self::CACHE_PHONES]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
