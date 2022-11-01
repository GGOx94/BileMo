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
use OpenApi\Attributes as OAA; //TODO OA

class CustomerController extends AbstractController
{
    private const CACHE_CUSTOMERS = "cacheCustomers";
    private SerializationContext $serializationContext;

    public function __construct(private readonly Security $security, private readonly CustomerRepository $customerRepo)
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
    public function getAllCustomers(
        Request                $request,
        SerializerInterface    $serializer,
        TagAwareCacheInterface $cachePool,
        RestPaginator          $paginator
    ) : JsonResponse
    {
        $usrId = $this->security->getUser()->getId();

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $customerCountCacheId = "getCustomers-count-$usrId";
        $customerCount = $cachePool->get($customerCountCacheId, function (ItemInterface $item)
            use ($usrId) {
                $item->tag(self::CACHE_CUSTOMERS)->expiresAfter(60);
                return $this->customerRepo->getCountOfUser($usrId);
        });

        $idCache = "getAllCustomers-$usrId-$page-$limit";
        $jsonCustomersList = $cachePool->get($idCache, function (ItemInterface $item)
            use ($usrId, $serializer, $page, $limit, $customerCount, $paginator) {
                $item->tag(self::CACHE_CUSTOMERS)->expiresAfter(60);
                $list = $this->customerRepo->findOfUserWithPagination($usrId, $page, $limit);

                $pages = $paginator->asArray("api_customers", $page, $limit, $customerCount);
                return $serializer->serialize(array("_pages" => $pages, "items" => $list), 'json', $this->serializationContext);
        });

        return new JsonResponse(data: $jsonCustomersList, status: Response::HTTP_OK, json: true);
    }

    /**
     * Create a new customer for the currently logged-in user.
     */
    #[OAA\RequestBody(description: "Create a new customer object", required: true,
        content: new OAA\JsonContent(ref: new Model(type: Customer::class, groups: ["createCustomers"])))]
    #[OAA\Response(response: 201, description: "Your customer has been created",
        content: new OAA\JsonContent(ref: new Model(type: Customer::class, groups: ["getCustomers"])))]
    #[OAA\Tag(name: "Customers")]
    #[Route('/api/customers/', name: 'api_customer_post', methods: ['POST'], format: 'json')]
    public function createCustomer(Request $request) : JsonResponse
    {
        $customer = new Customer();
        return new JsonResponse(data: $customer, status: Response::HTTP_CREATED, json:true);
    }

    //TODO : update customer (PUT/PATCH ?)

    #[Route('/api/customers/{id}', name: 'api_customer_get', methods: ['GET'], format: 'json')]
    public function fetchCustomer(Customer $customer, SerializerInterface $serializer): JsonResponse
    {
        $this->checkUserRightsOnCustomer($customer);

        if($customer->getUser()->getId() !== $this->security->getUser()->getId()) {
            throw new \LogicException("You don't have the rights to access this resource");
        }

        $jsonCustomer = $serializer->serialize($customer, 'json', $this->serializationContext);
        return new JsonResponse($jsonCustomer, Response::HTTP_OK, [], true);
    }

    #[Route('/api/customers/{id}', name: 'api_customer_delete', methods: ['DELETE'], format: 'json')]
    public function deleteCustomer(Customer $customer, EntityManagerInterface $em, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $this->checkUserRightsOnCustomer($customer);

        $cachePool->invalidateTags([self::CACHE_CUSTOMERS]);
        $em->remove($customer);
        $em->flush();

        return new JsonResponse(status: Response::HTTP_NO_CONTENT);
    }

    private function checkUserRightsOnCustomer(Customer $customer) : void
    {
        if($customer->getUser()->getId() !== $this->security->getUser()->getId()) {
            throw new \LogicException("You don't have the rights to access this resource.");
        }
    }
}