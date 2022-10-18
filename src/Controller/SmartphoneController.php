<?php

namespace App\Controller;

use App\Entity\Smartphone;
use App\Repository\BrandRepository;
use App\Repository\SmartphoneRepository;
use App\Service\RestPaginator;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

class SmartphoneController extends AbstractController
{
    private const CACHE_PHONES = "cachePhones";
    private SerializationContext $serializationContext;

    public function __construct()
    {
        $this->serializationContext = SerializationContext::create()->setGroups(['getPhones']);
    }

    /**
     * Get the BileMo smartphones list (paginated).
     * @OA\Response(response=200, description="The smartphones list",
     *     @OA\JsonContent(type="array", @OA\Items(ref=@Model(type=Smartphone::class, groups={"getPhones"}))))
     * @OA\Parameter(name="page", in="query", description="The page of the result you want", @OA\Schema(type="int"))
     * @OA\Parameter(name="limit", in="query", description="Number of element per page you want", @OA\Schema(type="int"))
     * @OA\Tag(name="Smartphones")
     */
    #[Route('/api/smartphones/', name: 'api_phones', methods: ['GET'], format: 'json')]
    public function getAllSmartphones(
        Request                $request,
        SmartphoneRepository   $smartphoneRepository,
        SerializerInterface    $serializer,
        TagAwareCacheInterface $cachePool,
        RestPaginator          $paginator
    ) : JsonResponse
    {
        //TODO
        // renvoyer un code d’erreur si on demande une page qui n’existe pas,
        // retourner directement dans la réponse des informations sur la pagination comme le nombre total de pages,
        // des liens vers les pages suivantes et précédentes

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $phoneCountCacheId = "getAllPhones-count";
        $phoneCount = $cachePool->get($phoneCountCacheId, function (ItemInterface $item) use ($smartphoneRepository) {
            $item->tag(self::CACHE_PHONES)->expiresAfter(60);
            return $smartphoneRepository->getCount();
        });

        $idCache = "getAllPhones-" . $page . "-" . $limit;
        $jsonPhonesList = $cachePool->get($idCache, function (ItemInterface $item)
            use ($smartphoneRepository, $serializer, $page, $limit, $phoneCount, $paginator) {
                $item->tag(self::CACHE_PHONES)->expiresAfter(60);
                $list = $smartphoneRepository->findAllWithPagination($page, $limit);

                $pages = $paginator->asArray("api_phones", $page, $limit, $phoneCount);
                return $serializer->serialize(array("_pages" => $pages, "items" => $list), 'json', $this->serializationContext);
        });

        return new JsonResponse(data: $jsonPhonesList, status: Response::HTTP_OK, json: true);
    }

    #[Route('/api/phones/{id}', name: 'api_phone_get', methods: ['GET'], format: 'json')]
    public function getphone(Smartphone $phone, SerializerInterface $serializer): JsonResponse
    {
        $jsonphone = $serializer->serialize($phone, 'json', $this->serializationContext);
        return new JsonResponse($jsonphone, Response::HTTP_OK, [], true);
    }

    #[IsGranted('ROLE_ADMIN', message: "You don't have access to phone deletion")]
    #[Route('/api/phones/{id}', name: 'api_phone_delete', methods: ['DELETE'], format: 'json')]
    public function deletephone(Smartphone $phone, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags([self::CACHE_PHONES]);
        $em->remove($phone);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @throws HttpException
     */
    #[IsGranted('ROLE_ADMIN', message: "You don't have access to phone creation")]
    #[Route('/api/phones/', name:"api_phone_create", methods: ['POST'], format: 'json')]
    public function createphone(
        Request                $request,
        SerializerInterface    $serializer,
        EntityManagerInterface $em,
        UrlGeneratorInterface  $urlGenerator,
        ValidatorInterface     $validator
    ) : JsonResponse
    {
        $phone = $serializer->deserialize($request->getContent(), Smartphone::class, 'json');

        $errors = $validator->validate($phone);
        if($errors->count() > 0) {
            // TODO create our own exceptions, enums to filter/handle on type ?
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, "BAD REQUEST");
        }

        $em->persist($phone);
        $em->flush();

        $jsonphone = $serializer->serialize($phone, 'json', $this->serializationContext);
        $location = $urlGenerator->generate('api_phone_get', ['id' => $phone->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonphone, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[IsGranted('ROLE_ADMIN', message: "You don't have access to phone modification")]
    #[Route('/api/phones/{id}', name:"api_phone_update", methods:['PUT'], format: 'json')]
    public function updatePhone(
        Request                $request,
        SerializerInterface    $serializer,
        Smartphone             $currentphone,
        EntityManagerInterface $em,
        TagAwareCacheInterface $cachePool,
        ValidatorInterface     $validator,
        BrandRepository $brandRepository
    ) : JsonResponse
    {
        $newphone = $serializer->deserialize($request->getContent(), Smartphone::class, 'json');
        $currentphone->setName($newphone->getName());
        $currentphone->setDescription($newphone->getDescription());

        // Check for validation error on Request phone json
        $errors = $validator->validate($currentphone);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

        $content = $request->toArray();
        $brandId = $content['brandId'] ?? -1;

        $currentphone->setBrand($brandRepository->find($brandId));

        $em->persist($currentphone);
        $em->flush();

        // We modified phone collection, invalidate cache
        $cachePool->invalidateTags([self::CACHE_PHONES]);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
