<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Repository\CustomerRepository;
use App\Service\RestPaginator;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Annotations as OA;

class CustomerController extends AbstractController
{
    private const CACHE_CUSTOMERS = "cacheCustomers";
    private SerializationContext $serializationContext;

    public function __construct()
    {
        $this->serializationContext = SerializationContext::create()->setGroups(['getCustomers']);
    }

    /**
     * Get your customers list (paginated).
     * @OA\Response(response=200, description="Your customers list",
     *     @OA\JsonContent(type="array", @OA\Items(ref=@Model(type=Customer::class, groups={"getCustomers"}))))
     * @OA\Parameter(name="page", in="query", description="The page of the result you want", @OA\Schema(type="int"))
     * @OA\Parameter(name="limit", in="query", description="Number of element per page you want", @OA\Schema(type="int"))
     * @OA\Tag(name="Customers")
     */
    #[Route('/api/customers/', name: 'api_customers', methods: ['GET'], format: 'json')]
    public function getAllSmartphones(
        Request                $request,
        Security                $security,
        CustomerRepository      $customerRepository,
        SerializerInterface    $serializer,
        TagAwareCacheInterface $cachePool,
        RestPaginator          $paginator
    ) : JsonResponse
    {
        $usrId = $security->getUser()->getId();

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $customerCountCacheId = "getCustomers-count-$usrId";
        $customerCount = $cachePool->get($customerCountCacheId, function (ItemInterface $item)
            use ($customerRepository, $usrId) {
                $item->tag(self::CACHE_CUSTOMERS)->expiresAfter(60);
                return $customerRepository->getCountOfUser($usrId);
        });

        $idCache = "getAllCustomers-$usrId-$page-$limit";
        $jsonCustomersList = $cachePool->get($idCache, function (ItemInterface $item)
            use ($customerRepository, $usrId, $serializer, $page, $limit, $customerCount, $paginator) {
                $item->tag(self::CACHE_CUSTOMERS)->expiresAfter(60);
                $list = $customerRepository->findOfUserWithPagination($usrId, $page, $limit);

                $pages = $paginator->asArray("api_customers", $page, $limit, $customerCount);
                return $serializer->serialize(array("_pages" => $pages, "items" => $list), 'json', $this->serializationContext);
        });

        return new JsonResponse(data: $jsonCustomersList, status: Response::HTTP_OK, json: true);
    }

    #[Route('/api/customers/{id}', name: 'api_customer_get', methods: ['GET'], format: 'json')]
    public function fetchCustomer(Customer $customer, SerializerInterface $serializer): JsonResponse
    {
        $jsonCustomer = $serializer->serialize($customer, 'json', $this->serializationContext);
        return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
    }

    #[Route('/api/customers/{id}', name: 'api_customer_delete', methods: ['DELETE'], format: 'json')]
    public function deleteCustomer(Customer $customer, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags([self::CACHE_CUSTOMERS]);
        $em->remove($customer);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}